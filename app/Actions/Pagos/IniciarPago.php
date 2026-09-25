<?php

namespace App\Actions\Pagos;

use App\Actions\Reservas\ExpirarReserva;
use App\Contracts\Pagos\PasarelaPago;
use App\Data\Pagos\ResultadoInicioPago;
use App\Domain\Pagos\AutorizacionPago;
use App\Enums\CanalPago;
use App\Enums\EstadoPago;
use App\Enums\EstadoReserva;
use App\Enums\ProveedorPago;
use App\Exceptions\ConflictoPagoException;
use App\Exceptions\OperacionPagoInvalidaException;
use App\Exceptions\OperacionUsuarioNoPermitidaException;
use App\Exceptions\PasarelaPagoException;
use App\Models\Pago;
use App\Models\Reserva;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Inicia un intento de pago para una reserva.
 *
 * Responsabilidades:
 *
 * - validar la reserva;
 * - controlar idempotencia;
 * - crear el intento interno;
 * - crear la Order en Mercado Pago;
 * - guardar checkout_url y order_id.
 */
class IniciarPago
{
    public function __construct(

        private readonly AutorizacionPago $autorizacion,

        private readonly PasarelaPago $pasarela,

        private readonly ExpirarReserva $expirarReserva

    ) {
    }


    public function ejecutar(
        Usuario $usuario,
        Reserva $reserva,
        CanalPago $canal,
        string $idempotencyKey
    ): ResultadoInicioPago {

        /*
        |--------------------------------------------------------------------------
        | Fase 1: preparar intento interno
        |--------------------------------------------------------------------------
        |
        | Aquí NO llamamos todavía a Mercado Pago.
        |
        | Primero dejamos persistida la intención.
        |
        */

        $preparacion =
            DB::transaction(
                function () use (
                    $usuario,
                    $reserva,
                    $canal,
                    $idempotencyKey
                ): array {

                    /*
                     * Serializamos operaciones para
                     * esta reserva.
                     */
                    $reservaBloqueada =
                        Reserva::query()

                            ->whereKey(
                                $reserva->id
                            )

                            ->lockForUpdate()

                            ->firstOrFail();


                    /*
                    |--------------------------------------------------------------------------
                    | Autorización
                    |--------------------------------------------------------------------------
                    */

                    if (
                        ! $this
                            ->autorizacion
                            ->puedeIniciar(
                                $usuario,
                                $reservaBloqueada
                            )
                    ) {
                        throw new OperacionUsuarioNoPermitidaException(
                            'No puedes iniciar un pago para esta reserva.'
                        );
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | Estado
                    |--------------------------------------------------------------------------
                    */

                    if (
                        $reservaBloqueada->estado
                        !== EstadoReserva::PENDIENTE_PAGO
                    ) {
                        throw new OperacionPagoInvalidaException(
                            'Solamente una reserva PENDIENTE_PAGO puede iniciar un pago.'
                        );
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | Vigencia
                    |--------------------------------------------------------------------------
                    */

                    if (
                        $reservaBloqueada->expira_en === null
                        || $reservaBloqueada
                            ->expira_en
                            ->lte(
                                now()
                            )
                    ) {
                        return [
                            'expirada' =>
                                true,

                            'creado' =>
                                false,

                            'pago' =>
                                null,
                        ];
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | Idempotencia local
                    |--------------------------------------------------------------------------
                    |
                    | Si la misma clave ya existe,
                    | devolvemos EL MISMO intento.
                    |
                    */

                    $pagoExistente =
                        Pago::query()

                            ->where(
                                'idempotency_key',
                                $idempotencyKey
                            )

                            ->lockForUpdate()

                            ->first();


                    if ($pagoExistente) {

                        /*
                         * Una misma clave nunca puede
                         * representar operaciones diferentes.
                         */
                        if (
                            (int)
                            $pagoExistente->reserva_id
                            !== (int)
                            $reservaBloqueada->id

                            ||

                            (int)
                            $pagoExistente
                                ->iniciado_por_usuario_id
                            !== (int)
                            $usuario->id

                            ||

                            $pagoExistente->canal
                            !== $canal
                        ) {
                            throw new ConflictoPagoException(
                                'La clave de idempotencia ya fue utilizada en otra operación.'
                            );
                        }


                        return [
                            'expirada' =>
                                false,

                            'creado' =>
                                false,

                            'pago' =>
                                $pagoExistente,
                        ];
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | Evitar dos intentos activos
                    |--------------------------------------------------------------------------
                    |
                    | Incluso si llegan dos UUID diferentes,
                    | una reserva no debe mantener dos
                    | checkouts activos simultáneamente.
                    |
                    */

                    $intentoActivo =
                        Pago::query()

                            ->where(
                                'reserva_id',
                                $reservaBloqueada->id
                            )

                            ->whereIn(
                                'estado',
                                [
                                    EstadoPago::CREADO->value,
                                    EstadoPago::PENDIENTE->value,
                                ]
                            )

                            ->lockForUpdate()

                            ->first();


                    if ($intentoActivo) {
                        throw new ConflictoPagoException(
                            'La reserva ya tiene un intento de pago activo.'
                        );
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | Crear intento
                    |--------------------------------------------------------------------------
                    */

                    $pago =
                        Pago::create([

                            'reserva_id' =>
                                $reservaBloqueada->id,

                            'iniciado_por_usuario_id' =>
                                $usuario->id,

                            'proveedor' =>
                                ProveedorPago::MERCADO_PAGO,

                            'canal' =>
                                $canal,

                            'idempotency_key' =>
                                $idempotencyKey,

                            'external_reference' =>
                                'PAY-'
                                .Str::upper(
                                    (string)
                                    Str::ulid()
                                ),

                            /*
                             * Snapshot del total.
                             */
                            'monto' =>
                                $reservaBloqueada->total,

                            'moneda' =>
                                'PEN',

                            'estado' =>
                                EstadoPago::CREADO,

                            'requiere_revision' =>
                                false,
                        ]);


                    return [
                        'expirada' =>
                            false,

                        'creado' =>
                            true,

                        'pago' =>
                            $pago,
                    ];
                }
            );


        /*
        |--------------------------------------------------------------------------
        | Reserva vencida
        |--------------------------------------------------------------------------
        |
        | Fuera de la transacción anterior.
        |
        | ExpirarReserva ejecutará su propio COMMIT.
        |
        */

        if ($preparacion['expirada']) {

            $this->expirarReserva
                ->ejecutar(
                    $reserva->id
                );


            throw new OperacionPagoInvalidaException(
                'La reserva ya expiró y no puede iniciar un pago.'
            );
        }


        /** @var Pago $pago */
        $pago =
            $preparacion['pago'];


        /*
        |--------------------------------------------------------------------------
        | Reintento idempotente ya completado
        |--------------------------------------------------------------------------
        */

        if (
            $pago->proveedor_order_id
            && $pago->checkout_url
        ) {
            return new ResultadoInicioPago(
                pago:
                    $pago->fresh(),

                creado:
                    false
            );
        }


        /*
         * Si este intento terminó de forma definitiva
         * con ERROR, la clave no debe reciclarse.
         */
        if (
            $pago->estado
            === EstadoPago::ERROR
        ) {
            throw new OperacionPagoInvalidaException(
                'El intento de pago terminó con error. Inicia un nuevo intento con una nueva clave de idempotencia.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Fase 2: proveedor externo
        |--------------------------------------------------------------------------
        |
        | La llamada HTTP queda fuera de una
        | transacción larga de PostgreSQL.
        |
        */

        try {

            $resultadoOrden =
                $this->pasarela
                    ->crearOrden(
                        $pago
                    );

        } catch (
            PasarelaPagoException $exception
        ) {

            /*
            |--------------------------------------------------------------------------
            | Guardar error
            |--------------------------------------------------------------------------
            */

            DB::transaction(
                function () use (
                    $pago,
                    $exception
                ): void {

                    $pagoBloqueado =
                        Pago::query()

                            ->whereKey(
                                $pago->id
                            )

                            ->lockForUpdate()

                            ->firstOrFail();


                    /*
                     * Cuando el resultado es incierto,
                     * dejamos CREADO.
                     *
                     * Así solamente puede reintentarse
                     * con LA MISMA idempotency_key.
                     */
                    $nuevoEstado =
                        $exception
                            ->reintentarMismaClave
                            ? EstadoPago::CREADO
                            : EstadoPago::ERROR;


                    $pagoBloqueado->update([

                        'estado' =>
                            $nuevoEstado,

                        'error_codigo' =>
                            $exception
                                ->codigoProveedor,

                        'error_mensaje' =>
                            $exception
                                ->getMessage(),
                    ]);
                }
            );


            throw $exception;
        }


        /*
        |--------------------------------------------------------------------------
        | Fase 3: persistir Order
        |--------------------------------------------------------------------------
        */

        $pagoActualizado =
            DB::transaction(
                function () use (
                    $pago,
                    $resultadoOrden
                ): Pago {

                    $pagoBloqueado =
                        Pago::query()

                            ->whereKey(
                                $pago->id
                            )

                            ->lockForUpdate()

                            ->firstOrFail();


                    /*
                     * Si otra ejecución idempotente
                     * ya guardó la misma Order,
                     * simplemente la reutilizamos.
                     */
                    if (
                        $pagoBloqueado
                            ->proveedor_order_id
                        !== null
                    ) {

                        if (
                            $pagoBloqueado
                                ->proveedor_order_id
                            !== $resultadoOrden
                                ->orderId
                        ) {
                            throw new ConflictoPagoException(
                                'El proveedor devolvió una orden diferente para la misma clave de idempotencia.'
                            );
                        }


                        return $pagoBloqueado;
                    }


                    $pagoBloqueado->update([

                        'proveedor_order_id' =>
                            $resultadoOrden
                                ->orderId,

                        'checkout_url' =>
                            $resultadoOrden
                                ->checkoutUrl,

                        /*
                         * La Order ya existe.
                         *
                         * Todavía NO significa
                         * pago aprobado.
                         */
                        'estado' =>
                            EstadoPago::PENDIENTE,

                        'estado_proveedor' =>
                            $resultadoOrden
                                ->estadoProveedor,

                        'detalle_estado' =>
                            $resultadoOrden
                                ->detalleEstado,

                        'error_codigo' =>
                            null,

                        'error_mensaje' =>
                            null,
                    ]);


                    return $pagoBloqueado
                        ->fresh();
                }
            );


        return new ResultadoInicioPago(

            pago:
                $pagoActualizado,

            creado:
                $preparacion[
                    'creado'
                ]
        );
    }
}
