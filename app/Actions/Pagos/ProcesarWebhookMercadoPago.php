<?php

namespace App\Actions\Pagos;

use App\Enums\ProveedorPago;
use App\Exceptions\PasarelaPagoException;
use App\Models\EventoWebhookPago;
use App\Models\Pago;
use Illuminate\Support\Facades\DB;

/**
 * Procesa la recepción de un Webhook
 * de Mercado Pago.
 *
 * Esta clase controla:
 *
 * - idempotencia del evento;
 * - asociación Order -> Pago;
 * - ejecución de la sincronización;
 * - marcado definitivo del evento.
 *
 * La lógica financiera vive en:
 *
 * SincronizarPagoMercadoPago.
 */
class ProcesarWebhookMercadoPago
{
    public function __construct(
        private readonly
        SincronizarPagoMercadoPago $sincronizarPago
    ) {
    }


    public function ejecutar(
        string $eventoId,
        string $tipo,
        ?string $accion,
        string $orderId,
        ?string $requestId
    ): void {

        /*
        |--------------------------------------------------------------------------
        | 1. Registrar o recuperar evento
        |--------------------------------------------------------------------------
        */

        $evento =
            EventoWebhookPago::query()
                ->firstOrCreate(
                    [
                        'proveedor' =>
                            ProveedorPago::MERCADO_PAGO,

                        'evento_id' =>
                            $eventoId,
                    ],
                    [
                        'tipo' =>
                            $tipo,

                        'accion' =>
                            $accion,

                        'data_id' =>
                            $orderId,

                        'request_id' =>
                            $requestId,

                        'procesado' =>
                            false,
                    ]
                );


        /*
        |--------------------------------------------------------------------------
        | 2. Proteger contra colisión de evento_id
        |--------------------------------------------------------------------------
        |
        | Un evento existente nunca debe cambiar
        | silenciosamente de Order.
        |
        */

        if (
            $evento->data_id
            !== $orderId
        ) {
            throw new PasarelaPagoException(
                'El identificador del Webhook ya está asociado a otra Order.'
            );
        }


        /*
         * Evento procesado anteriormente.
         */
        if (
            $evento->procesado
        ) {
            return;
        }


        /*
        |--------------------------------------------------------------------------
        | 3. Buscar Pago local
        |--------------------------------------------------------------------------
        |
        | No hacemos todavía la llamada HTTP.
        |
        | Si Mercado Pago notificó demasiado rápido
        | y Laravel aún no guardó proveedor_order_id,
        | devolvemos error para provocar reintento.
        |
        */

        $pago =
            Pago::query()

                ->where(
                    'proveedor_order_id',
                    $orderId
                )

                ->first();


        if (! $pago) {

            DB::transaction(
                function () use (
                    $evento
                ): void {

                    $eventoBloqueado =
                        EventoWebhookPago::query()

                            ->whereKey(
                                $evento->id
                            )

                            ->lockForUpdate()

                            ->firstOrFail();


                    $eventoBloqueado->update([
                        'error_mensaje' =>
                            'No existe un intento de pago local asociado a la Order notificada.',
                    ]);
                }
            );


            throw new PasarelaPagoException(
                'No existe un intento de pago local asociado a la Order notificada.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | 4. Sincronización oficial
        |--------------------------------------------------------------------------
        |
        | SincronizarPagoMercadoPago:
        |
        | - consulta GET /v1/orders/{id};
        | - valida referencia;
        | - valida moneda;
        | - valida montos;
        | - valida payment_id;
        | - sincroniza el estado;
        | - aplica el resultado a la reserva.
        |
        */

        $this->sincronizarPago
            ->ejecutar(
                $pago
            );


        /*
        |--------------------------------------------------------------------------
        | 5. Marcar evento procesado
        |--------------------------------------------------------------------------
        |
        | Solamente después de que TODA la
        | sincronización haya terminado.
        |
        */

        DB::transaction(
            function () use (
                $evento
            ): void {

                $eventoBloqueado =
                    EventoWebhookPago::query()

                        ->whereKey(
                            $evento->id
                        )

                        ->lockForUpdate()

                        ->firstOrFail();


                if (
                    ! $eventoBloqueado
                        ->procesado
                ) {
                    $eventoBloqueado->update([

                        'procesado' =>
                            true,

                        'procesado_en' =>
                            now(),

                        'error_mensaje' =>
                            null,
                    ]);
                }
            }
        );
    }
}
