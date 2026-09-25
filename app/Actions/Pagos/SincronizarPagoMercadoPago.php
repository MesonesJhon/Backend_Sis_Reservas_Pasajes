<?php

namespace App\Actions\Pagos;

use App\Contracts\Pagos\PasarelaPago;
use App\Data\Pagos\ResultadoConsultaOrdenPago;
use App\Enums\EstadoPago;
use App\Enums\ProveedorPago;
use App\Exceptions\PasarelaPagoException;
use App\Models\Pago;
use Illuminate\Support\Facades\DB;

/**
 * Sincroniza un intento de pago local
 * contra la fuente oficial de Mercado Pago.
 *
 * Esta Action NO depende de un Webhook.
 *
 * Puede ser utilizada por:
 *
 * - Webhooks.
 * - Reconciliación programada.
 * - Recuperación manual.
 *
 * Nunca confía en datos enviados por frontend
 * ni en parámetros de las URLs de retorno.
 */
class SincronizarPagoMercadoPago
{
    public function __construct(
        private readonly PasarelaPago $pasarela,
        private readonly AplicarPagoAReserva $aplicarPagoAReserva,
        private readonly AplicarReembolsoAReserva $aplicarReembolsoAReserva

    ) {
    }


    /**
     * Consulta la Order oficial, valida su integridad,
     * sincroniza el Pago y aplica su resultado comercial.
     */
    public function ejecutar(
        Pago $pago
    ): Pago {

        /*
        |--------------------------------------------------------------------------
        | 1. Validaciones locales previas
        |--------------------------------------------------------------------------
        */

        if (
            $pago->proveedor
            !== ProveedorPago::MERCADO_PAGO
        ) {
            throw new PasarelaPagoException(
                'El intento de pago no pertenece a Mercado Pago.'
            );
        }


        if (
            ! is_string(
                $pago->proveedor_order_id
            )
            || trim(
                $pago->proveedor_order_id
            ) === ''
        ) {
            throw new PasarelaPagoException(
                'El intento de pago no tiene una Order de Mercado Pago asociada.'
            );
        }


        $orderId =
            trim(
                $pago->proveedor_order_id
            );


        /*
        |--------------------------------------------------------------------------
        | 2. Consultar fuente oficial
        |--------------------------------------------------------------------------
        |
        | IMPORTANTE:
        |
        | Esta llamada se realiza ANTES de abrir
        | una transacción en PostgreSQL.
        |
        | No queremos mantener locks mientras
        | esperamos una respuesta HTTP externa.
        |
        */

        $orden =
            $this->pasarela
                ->consultarOrden(
                    $orderId
                );


        /*
        |--------------------------------------------------------------------------
        | 3. Sincronización local atómica
        |--------------------------------------------------------------------------
        */

        $pagoId =
            DB::transaction(
                function () use (
                    $pago,
                    $orden,
                    $orderId
                ): int {

                    /*
                     * Bloqueamos el Pago.
                     *
                     * Así Webhook y reconciliador
                     * pueden ejecutarse al mismo tiempo
                     * sin pisarse.
                     */
                    $pagoBloqueado =
                        Pago::query()

                            ->whereKey(
                                $pago->id
                            )

                            ->lockForUpdate()

                            ->firstOrFail();


                    /*
                    |--------------------------------------------------------------------------
                    | Integridad de la Order
                    |--------------------------------------------------------------------------
                    */

                    if (
                        $pagoBloqueado
                            ->proveedor_order_id
                        !== $orderId
                    ) {
                        throw new PasarelaPagoException(
                            'La Order asociada al pago cambió durante la sincronización.'
                        );
                    }


                    if (
                        ! hash_equals(
                            $orderId,
                            $orden->orderId
                        )
                    ) {
                        throw new PasarelaPagoException(
                            'Mercado Pago devolvió una Order diferente a la asociada localmente.'
                        );
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | 4. Traducir estado externo
                    |--------------------------------------------------------------------------
                    */

                    $traduccion =
                        $this->traducirEstado(
                            $orden
                        );


                    $nuevoEstado =
                        $traduccion[
                            'estado'
                        ];


                    /*
                    |--------------------------------------------------------------------------
                    | 5. Integridad financiera
                    |--------------------------------------------------------------------------
                    */

                    $problemas =
                        $this->detectarProblemasIntegridad(
                            $pagoBloqueado,
                            $orden,
                            $nuevoEstado
                        );


                    /*
                    |--------------------------------------------------------------------------
                    | 6. Evitar regresiones financieras
                    |--------------------------------------------------------------------------
                    |
                    | Un Webhook tardío o una lectura inconsistente
                    | no puede degradar:
                    |
                    | APROBADO -> PENDIENTE
                    | APROBADO -> RECHAZADO
                    | APROBADO -> CANCELADO
                    |
                    | Un APROBADO sí puede pasar a REEMBOLSADO.
                    |
                    */

                    $estadoPersistible =
                        $this->resolverEstadoPersistible(
                            $pagoBloqueado->estado,
                            $nuevoEstado,
                            $problemas
                        );


                    /*
                    |--------------------------------------------------------------------------
                    | 7. Payment ID
                    |--------------------------------------------------------------------------
                    */

                    $paymentIdPersistible =
                        $pagoBloqueado
                            ->proveedor_payment_id;


                    if (
                        $orden->paymentId !== null
                        && trim(
                            $orden->paymentId
                        ) !== ''
                    ) {

                        $paymentIdRemoto =
                            trim(
                                $orden->paymentId
                            );


                        /*
                         * Si nuestro Pago ya tenía un payment_id
                         * diferente, nunca lo sustituimos
                         * silenciosamente.
                         */
                        if (
                            $pagoBloqueado
                                ->proveedor_payment_id
                            !== null

                            &&

                            ! hash_equals(
                                $pagoBloqueado
                                    ->proveedor_payment_id,

                                $paymentIdRemoto
                            )
                        ) {

                            $problemas[] =
                                'Mercado Pago informó un payment_id diferente al que ya estaba registrado para este intento.';

                        } else {

                            /*
                             * Comprobar que el payment_id
                             * no pertenezca a otro Pago.
                             */
                            $otroPago =
                                Pago::query()

                                    ->where(
                                        'proveedor_payment_id',
                                        $paymentIdRemoto
                                    )

                                    ->whereKeyNot(
                                        $pagoBloqueado->id
                                    )

                                    ->exists();


                            if ($otroPago) {

                                $problemas[] =
                                    'El payment_id de Mercado Pago ya está asociado a otro intento de pago.';

                            } else {

                                $paymentIdPersistible =
                                    $paymentIdRemoto;
                            }
                        }
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | 8. Revisión manual
                    |--------------------------------------------------------------------------
                    |
                    | Una revisión ya detectada no desaparece
                    | automáticamente.
                    |
                    */

                    $requiereRevision =
                        $pagoBloqueado
                            ->requiere_revision

                        || $traduccion[
                            'requiere_revision'
                        ]

                        || count(
                            $problemas
                        ) > 0;


                    $motivos = [];


                    if (
                        $pagoBloqueado
                            ->motivo_revision
                    ) {
                        $motivos[] =
                            $pagoBloqueado
                                ->motivo_revision;
                    }


                    if (
                        $traduccion[
                            'motivo_revision'
                        ]
                    ) {
                        $motivos[] =
                            $traduccion[
                                'motivo_revision'
                            ];
                    }


                    foreach (
                        $problemas
                        as $problema
                    ) {
                        $motivos[] =
                            $problema;
                    }


                    $motivos =
                        array_values(
                            array_unique(
                                $motivos
                            )
                        );


                    /*
                    |--------------------------------------------------------------------------
                    | 9. Actualizar Pago
                    |--------------------------------------------------------------------------
                    */

                    $actualizacion = [

                        'estado' =>
                            $estadoPersistible,

                        'estado_proveedor' =>
                            $orden->estado,

                        'detalle_estado' =>
                            $orden->detalleEstado,

                        'proveedor_payment_id' =>
                            $paymentIdPersistible,

                        'requiere_revision' =>
                            $requiereRevision,

                        'motivo_revision' =>
                            $requiereRevision
                                ? implode(
                                    ' | ',
                                    $motivos
                                )
                                : null,

                        'ultima_verificacion_en' =>
                            now(),

                        /*
                         * Si logramos consultar correctamente
                         * al proveedor, eliminamos errores
                         * temporales anteriores.
                         */
                        'error_codigo' =>
                            null,

                        'error_mensaje' =>
                            null,
                    ];


                    /*
                    |--------------------------------------------------------------------------
                    | 10. Fechas históricas
                    |--------------------------------------------------------------------------
                    |
                    | Nunca reescribimos la primera vez
                    | que alcanzó un estado importante.
                    |
                    */

                    if (
                        $estadoPersistible
                        === EstadoPago::APROBADO

                        &&

                        $pagoBloqueado
                            ->aprobado_en
                        === null
                    ) {
                        $actualizacion[
                            'aprobado_en'
                        ] =
                            now();
                    }


                    if (
                        $estadoPersistible
                        === EstadoPago::RECHAZADO

                        &&

                        $pagoBloqueado
                            ->rechazado_en
                        === null
                    ) {
                        $actualizacion[
                            'rechazado_en'
                        ] =
                            now();
                    }


                    if (
                        $estadoPersistible
                        === EstadoPago::CANCELADO

                        &&

                        $pagoBloqueado
                            ->cancelado_en
                        === null
                    ) {
                        $actualizacion[
                            'cancelado_en'
                        ] =
                            now();
                    }


                    if (
                        $estadoPersistible
                        === EstadoPago::REEMBOLSADO

                        &&

                        $pagoBloqueado
                            ->reembolsado_en
                        === null
                    ) {
                        $actualizacion[
                            'reembolsado_en'
                        ] =
                            now();
                    }


                    $pagoBloqueado->update(
                        $actualizacion
                    );


                    return $pagoBloqueado->id;
                }
            );


        /*
        |--------------------------------------------------------------------------
        | 11. Aplicar resultado comercial
        |--------------------------------------------------------------------------
        |
        | Fuera de la transacción anterior.
        |
        | AplicarPagoAReserva ya decide si:
        |
        | - confirma la reserva;
        | - la deja intacta;
        | - detecta expiración;
        | - exige revisión.
        |
        */

        /*
        |--------------------------------------------------------------------------
        | Aplicar efecto comercial
        |--------------------------------------------------------------------------
        */

        $pagoSincronizado =
            Pago::query()
                ->findOrFail(
                    $pagoId
                );


        if (
            $pagoSincronizado->estado
            === EstadoPago::REEMBOLSADO
        ) {

            /*
            * El dinero ya fue devuelto.
            *
            * Aplicamos las consecuencias comerciales:
            * cancelar/liberar cuando sea seguro.
            */
            $this->aplicarReembolsoAReserva
                ->ejecutar(
                    $pagoId
                );

        } else {

            /*
            * Solamente APROBADO válido podrá
            * confirmar la reserva.
            */
            $this->aplicarPagoAReserva
                ->ejecutar(
                    $pagoId
                );
        }


        return Pago::query()
            ->findOrFail(
                $pagoId
            )
            ->fresh();
    }


    /**
     * Traduce estados externos de Mercado Pago
     * hacia nuestro dominio.
     */
    private function traducirEstado(
        ResultadoConsultaOrdenPago $orden
    ): array {

        /*
         * Pago acreditado.
         */
        if (
            $orden->estado === 'processed'
            && $orden->detalleEstado
            === 'accredited'
        ) {
            return [
                'estado' =>
                    EstadoPago::APROBADO,

                'requiere_revision' =>
                    false,

                'motivo_revision' =>
                    null,
            ];
        }


        /*
         * Reembolso completo.
         */
        if (
            $orden->estado === 'refunded'

            ||

            (
                $orden->estado
                === 'processed'

                &&

                $orden->detalleEstado
                === 'refunded'
            )
        ) {
            return [
                'estado' =>
                    EstadoPago::REEMBOLSADO,

                'requiere_revision' =>
                    false,

                'motivo_revision' =>
                    null,
            ];
        }


        /*
         * Reembolso parcial.
         */
        if (
            $orden->estado === 'processed'
            && $orden->detalleEstado
            === 'partially_refunded'
        ) {
            return [
                'estado' =>
                    EstadoPago::APROBADO,

                'requiere_revision' =>
                    true,

                'motivo_revision' =>
                    'Mercado Pago informó un reembolso parcial.',
            ];
        }


        /*
         * Fallo definitivo.
         */
        if (
            $orden->estado === 'failed'
        ) {
            return [
                'estado' =>
                    EstadoPago::RECHAZADO,

                'requiere_revision' =>
                    false,

                'motivo_revision' =>
                    null,
            ];
        }


        /*
         * Cancelación / expiración del proveedor.
         */
        if (
            in_array(
                $orden->estado,
                [
                    'canceled',
                    'expired',
                ],
                true
            )
        ) {
            return [
                'estado' =>
                    EstadoPago::CANCELADO,

                'requiere_revision' =>
                    false,

                'motivo_revision' =>
                    null,
            ];
        }


        /*
         * Contracargo.
         *
         * No revocamos automáticamente
         * una reserva/ticket aquí.
         */
        if (
            $orden->estado === 'charged_back'
        ) {
            return [
                'estado' =>
                    EstadoPago::APROBADO,

                'requiere_revision' =>
                    true,

                'motivo_revision' =>
                    'Mercado Pago informó un contracargo.',
            ];
        }


        /*
         * Estados todavía no definitivos.
         */
        if (
            in_array(
                $orden->estado,
                [
                    'created',
                    'processing',
                    'action_required',
                ],
                true
            )
        ) {
            return [
                'estado' =>
                    EstadoPago::PENDIENTE,

                'requiere_revision' =>
                    false,

                'motivo_revision' =>
                    null,
            ];
        }


        /*
         * Estado desconocido:
         *
         * jamás aprobamos automáticamente.
         */
        return [
            'estado' =>
                EstadoPago::PENDIENTE,

            'requiere_revision' =>
                true,

            'motivo_revision' =>
                'Mercado Pago informó un estado no reconocido: '
                .$orden->estado
                .'.',
        ];
    }


    /**
     * Valida que la Order oficial corresponda
     * exactamente al intento local.
     */
    private function detectarProblemasIntegridad(
        Pago $pago,
        ResultadoConsultaOrdenPago $orden,
        EstadoPago $nuevoEstado
    ): array {

        $problemas = [];


        if (
            $orden->externalReference
            !== $pago->external_reference
        ) {
            $problemas[] =
                'La referencia externa informada por Mercado Pago no coincide con el intento local.';
        }


        /*
        |--------------------------------------------------------------------------
        | Integridad de moneda
        |--------------------------------------------------------------------------
        */

        if (
            $nuevoEstado === EstadoPago::APROBADO

            &&

            (
                $orden->moneda === null
                || trim(
                    $orden->moneda
                ) === ''
            )
        ) {

            $problemas[] =
                'Mercado Pago no informó la moneda del pago aprobado.';

        } elseif (
            $orden->moneda !== null

            &&

            strtoupper(
                trim(
                    $orden->moneda
                )
            )
            !==
            strtoupper(
                trim(
                    $pago->moneda
                )
            )
        ) {

            $problemas[] =
                'La moneda informada por Mercado Pago no coincide con el intento local.';
        }


        if (
            ! $this->montosIguales(
                $orden->montoTotal,
                $pago->monto
            )
        ) {
            $problemas[] =
                'El monto de la Order no coincide con el monto registrado localmente.';
        }


        /*
         * Para aprobar exigimos que el dinero
         * efectivamente acreditado sea exactamente
         * el esperado.
         */
        if (
            $nuevoEstado
            === EstadoPago::APROBADO

            &&

            ! $this->montosIguales(
                $orden->montoPagado,
                $pago->monto
            )
        ) {
            $problemas[] =
                'El monto efectivamente pagado no coincide con el importe esperado.';
        }


        /*
         * Una aprobación sin payment_id
         * nunca confirma una reserva.
         */
        if (
            $nuevoEstado
            === EstadoPago::APROBADO

            &&

            (
                $orden->paymentId === null
                || trim(
                    $orden->paymentId
                ) === ''
            )
        ) {
            $problemas[] =
                'Mercado Pago informó el pago como aprobado pero no devolvió payment_id.';
        }


        return $problemas;
    }


    /**
     * Evita regresiones de estados financieros
     * provocadas por eventos tardíos.
     */
    private function resolverEstadoPersistible(
        EstadoPago $actual,
        EstadoPago $nuevo,
        array &$problemas
    ): EstadoPago {

        /*
         * APROBADO puede permanecer aprobado
         * o evolucionar a REEMBOLSADO.
         */
        if (
            $actual === EstadoPago::APROBADO
        ) {

            if (
                $nuevo === EstadoPago::APROBADO
                || $nuevo === EstadoPago::REEMBOLSADO
            ) {
                return $nuevo;
            }


            $problemas[] =
                'Se evitó una regresión de estado desde APROBADO hacia '
                .$nuevo->value
                .'.';


            return EstadoPago::APROBADO;
        }


        /*
         * Un reembolso completo es terminal
         * para nuestro estado financiero.
         */
        if (
            $actual === EstadoPago::REEMBOLSADO
            && $nuevo !== EstadoPago::REEMBOLSADO
        ) {

            $problemas[] =
                'Se evitó una regresión de estado desde REEMBOLSADO hacia '
                .$nuevo->value
                .'.';


            return EstadoPago::REEMBOLSADO;
        }


        return $nuevo;
    }


    /**
     * Compara dinero sin utilizar float.
     */
    private function montosIguales(
        string $primero,
        string $segundo
    ): bool {

        return $this->aCentavos(
            $primero
        )
        ===
        $this->aCentavos(
            $segundo
        );
    }


    /**
     * Convierte:
     *
     * 50
     * 50.0
     * 50.00
     *
     * a centavos enteros.
     */
    private function aCentavos(
        string $monto
    ): int {

        $monto =
            trim(
                $monto
            );


        if (
            ! preg_match(
                '/^\d+(?:\.\d{1,2})?$/',
                $monto
            )
        ) {
            throw new PasarelaPagoException(
                'Se recibió un monto inválido durante la conciliación del pago.'
            );
        }


        $partes =
            explode(
                '.',
                $monto,
                2
            );


        $entero =
            (int) $partes[0];


        $decimales =
            $partes[1]
            ?? '0';


        $decimales =
            str_pad(
                $decimales,
                2,
                '0'
            );


        return (
            $entero * 100
        )
        + (int) $decimales;
    }
}
