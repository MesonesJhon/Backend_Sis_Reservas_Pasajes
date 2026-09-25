<?php

namespace App\Console\Commands;

use App\Actions\Pagos\SincronizarPagoMercadoPago;
use App\Enums\EstadoPago;
use App\Enums\ProveedorPago;
use App\Exceptions\PasarelaPagoException;
use App\Models\Pago;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Reconciliación de pagos pendientes.
 *
 * Este comando funciona como mecanismo de recuperación
 * cuando el Webhook de Mercado Pago:
 *
 * - no llegó;
 * - llegó tarde;
 * - falló temporalmente;
 * - el servidor estuvo apagado;
 * - Cloudflare estuvo fuera de servicio.
 *
 * Nunca modifica el estado financiero basándose
 * únicamente en datos locales.
 *
 * Cada intento se vuelve a verificar directamente
 * contra Mercado Pago.
 */
class ReconciliarPagosPendientes extends Command
{
    /**
     * --antiguedad:
     *
     * Evita consultar inmediatamente un pago
     * que acaba de crearse y que probablemente
     * todavía recibirá su Webhook.
     *
     * --limite:
     *
     * Evita realizar cientos o miles de llamadas
     * HTTP en una sola ejecución.
     */
    protected $signature =
        'pagos:reconciliar-pendientes
        {--antiguedad=1 : Minutos mínimos desde la última verificación}
        {--limite=100 : Máximo de pagos a procesar}';


    protected $description =
        'Reconcilia pagos pendientes consultando su estado oficial en Mercado Pago.';


    public function __construct(
        private readonly
        SincronizarPagoMercadoPago $sincronizarPago
    ) {
        parent::__construct();
    }


    public function handle(): int
    {
        /*
        |--------------------------------------------------------------------------
        | 1. Opciones
        |--------------------------------------------------------------------------
        */

        $antiguedad =
            max(
                1,
                (int) $this->option(
                    'antiguedad'
                )
            );


        /*
         * Ponemos además un máximo defensivo.
         *
         * Aunque alguien ejecute:
         *
         * --limite=999999
         *
         * una sola ejecución nunca procesará
         * más de 500 pagos.
         */
        $limite =
            min(
                500,
                max(
                    1,
                    (int) $this->option(
                        'limite'
                    )
                )
            );


        $corte =
            now()->subMinutes(
                $antiguedad
            );


        /*
        |--------------------------------------------------------------------------
        | 2. Buscar candidatos
        |--------------------------------------------------------------------------
        |
        | Solamente:
        |
        | - Mercado Pago;
        | - PENDIENTE;
        | - con Order real;
        | - suficientemente antiguos;
        | - no verificados recientemente.
        |
        */

        $pagos =
            Pago::query()

                ->where(
                    'proveedor',
                    ProveedorPago::MERCADO_PAGO->value
                )

                ->where(
                    'estado',
                    EstadoPago::PENDIENTE->value
                )

                ->whereNotNull(
                    'proveedor_order_id'
                )

                /*
                 * Evitamos consultar una Order
                 * que acaba de crearse.
                 */
                ->where(
                    'created_at',
                    '<=',
                    $corte
                )

                /*
                 * Si ya fue consultada recientemente,
                 * esperamos otra ejecución.
                 */
                ->where(
                    function ($query) use (
                        $corte
                    ): void {

                        $query
                            ->whereNull(
                                'ultima_verificacion_en'
                            )

                            ->orWhere(
                                'ultima_verificacion_en',
                                '<=',
                                $corte
                            );
                    }
                )

                ->orderBy(
                    'id'
                )

                ->limit(
                    $limite
                )

                ->get();


        /*
        |--------------------------------------------------------------------------
        | 3. Nada que hacer
        |--------------------------------------------------------------------------
        */

        if (
            $pagos->isEmpty()
        ) {

            $this->info(
                'No existen pagos pendientes que requieran reconciliación.'
            );


            return self::SUCCESS;
        }


        $this->info(
            'Pagos pendientes encontrados: '
            .$pagos->count()
        );


        /*
        |--------------------------------------------------------------------------
        | Contadores
        |--------------------------------------------------------------------------
        */

        $procesados = 0;

        $resueltos = 0;

        $pendientes = 0;

        $errores = 0;


        /*
        |--------------------------------------------------------------------------
        | 4. Reconciliar uno por uno
        |--------------------------------------------------------------------------
        |
        | No hacemos una única transacción global.
        |
        | Un error en el Pago 10 no debe impedir
        | reconciliar los Pagos 11, 12, 13...
        |
        */

        foreach (
            $pagos
            as $pago
        ) {

            $this->line(
                'Reconciliando Pago #'
                .$pago->id
                .' - Order '
                .$pago->proveedor_order_id
            );


            try {

                /*
                 * Toda la lógica crítica vive aquí:
                 *
                 * - GET Order;
                 * - integridad;
                 * - monto;
                 * - moneda;
                 * - payment_id;
                 * - estado;
                 * - reserva;
                 * - asiento.
                 */
                $actualizado =
                    $this->sincronizarPago
                        ->ejecutar(
                            $pago
                        );


                $procesados++;


                if (
                    $actualizado->estado
                    === EstadoPago::PENDIENTE
                ) {

                    $pendientes++;


                    $this->line(
                        'Pago #'
                        .$actualizado->id
                        .' continúa PENDIENTE.'
                    );

                } else {

                    $resueltos++;


                    $this->info(
                        'Pago #'
                        .$actualizado->id
                        .' sincronizado como '
                        .$actualizado
                            ->estado
                            ->value
                        .'.'
                    );
                }

            } catch (
                PasarelaPagoException $exception
            ) {

                $errores++;


                /*
                 * El Pago NO cambia a rechazado.
                 *
                 * Un timeout, 500 o fallo temporal
                 * jamás significa que el cliente
                 * no haya pagado.
                 */
                $this->registrarErrorTemporal(
                    $pago,
                    $this->codigoPasarela(
                        $exception
                    ),
                    $exception->getMessage()
                );


                Log::warning(
                    'No fue posible reconciliar un pago con Mercado Pago.',
                    [
                        'pago_id' =>
                            $pago->id,

                        'order_id' =>
                            $pago
                                ->proveedor_order_id,

                        'codigo_proveedor' =>
                            $exception
                                ->codigoProveedor,

                        'status_proveedor' =>
                            $exception
                                ->statusProveedor,

                        'mensaje' =>
                            $exception
                                ->getMessage(),
                    ]
                );


                $this->warn(
                    'Pago #'
                    .$pago->id
                    .' no pudo reconciliarse: '
                    .$exception->getMessage()
                );

            } catch (
                Throwable $exception
            ) {

                $errores++;


                /*
                 * Error interno inesperado.
                 *
                 * Tampoco cambiamos el estado financiero.
                 */
                $this->registrarErrorTemporal(
                    $pago,
                    'RECONCILIACION_INTERNA',
                    $exception->getMessage()
                );


                Log::error(
                    'Error inesperado reconciliando un pago.',
                    [
                        'pago_id' =>
                            $pago->id,

                        'order_id' =>
                            $pago
                                ->proveedor_order_id,

                        'excepcion' =>
                            $exception::class,

                        'mensaje' =>
                            $exception
                                ->getMessage(),
                    ]
                );


                $this->error(
                    'Pago #'
                    .$pago->id
                    .' produjo un error inesperado.'
                );
            }
        }


        /*
        |--------------------------------------------------------------------------
        | 5. Resumen
        |--------------------------------------------------------------------------
        */

        $this->newLine();


        $this->table(
            [
                'Concepto',
                'Cantidad',
            ],
            [
                [
                    'Encontrados',
                    $pagos->count(),
                ],
                [
                    'Sincronizados',
                    $procesados,
                ],
                [
                    'Resueltos',
                    $resueltos,
                ],
                [
                    'Aún pendientes',
                    $pendientes,
                ],
                [
                    'Errores',
                    $errores,
                ],
            ]
        );


        /*
         * Si hubo errores retornamos FAILURE.
         *
         * El scheduler podrá registrar que hubo
         * una ejecución problemática, pero
         * el siguiente ciclo volverá a intentarlo.
         */
        return $errores > 0
            ? self::FAILURE
            : self::SUCCESS;
    }


    /**
     * Guarda información diagnóstica sin modificar
     * un Pago que ya haya sido actualizado
     * concurrentemente por el Webhook.
     */
    private function registrarErrorTemporal(
        Pago $pago,
        string $codigo,
        string $mensaje
    ): void {

        Pago::query()

            ->whereKey(
                $pago->id
            )

            /*
             * Protección de concurrencia:
             *
             * Si mientras fallaba esta consulta
             * llegó el Webhook y aprobó el Pago,
             * NO escribimos encima.
             */
            ->where(
                'estado',
                EstadoPago::PENDIENTE->value
            )

            ->update([
                'ultima_verificacion_en' =>
                    now(),

                'error_codigo' =>
                    Str::limit(
                        $codigo,
                        100,
                        ''
                    ),

                'error_mensaje' =>
                    Str::limit(
                        $mensaje,
                        1000,
                        ''
                    ),
            ]);
    }


    /**
     * Produce un código legible para diagnóstico.
     */
    private function codigoPasarela(
        PasarelaPagoException $exception
    ): string {

        if (
            is_string(
                $exception->codigoProveedor
            )
            && trim(
                $exception->codigoProveedor
            ) !== ''
        ) {
            return trim(
                $exception->codigoProveedor
            );
        }


        if (
            $exception->statusProveedor
            !== null
        ) {
            return
                'MERCADOPAGO_HTTP_'
                .$exception->statusProveedor;
        }


        return
            'MERCADOPAGO_COMUNICACION';
    }
}
