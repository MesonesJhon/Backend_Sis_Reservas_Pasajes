<?php

use App\Enums\ProveedorPago;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'eventos_webhook_pago',
            function (Blueprint $table): void {

                $table->id();


                /*
                |--------------------------------------------------------------------------
                | Proveedor
                |--------------------------------------------------------------------------
                */

                $table->string(
                    'proveedor',
                    30
                )->default(
                    ProveedorPago::MERCADO_PAGO->value
                );


                /*
                |--------------------------------------------------------------------------
                | Identificador de la notificación
                |--------------------------------------------------------------------------
                |
                | Mercado Pago envía un id propio
                | para cada evento.
                |
                | La combinación proveedor + evento_id
                | será UNIQUE para soportar reintentos.
                |
                */

                $table->string(
                    'evento_id',
                    100
                );


                /*
                 * order, payment, etc.
                 */
                $table->string(
                    'tipo',
                    50
                );


                /*
                 * Ejemplo:
                 *
                 * order.processed
                 */
                $table->string(
                    'accion',
                    100
                )->nullable();


                /*
                 * En nuestro caso será normalmente
                 * el ORD... de Mercado Pago.
                 */
                $table->string(
                    'data_id',
                    100
                );


                /*
                 * Header x-request-id.
                 *
                 * Muy útil para auditoría con MP.
                 */
                $table->string(
                    'request_id',
                    150
                )->nullable();


                /*
                |--------------------------------------------------------------------------
                | Resultado del procesamiento
                |--------------------------------------------------------------------------
                */

                $table->boolean(
                    'procesado'
                )->default(false);


                $table->timestamp(
                    'procesado_en'
                )->nullable();


                $table->text(
                    'error_mensaje'
                )->nullable();


                $table->timestamps();


                $table->unique(
                    [
                        'proveedor',
                        'evento_id',
                    ],
                    'webhook_pago_proveedor_evento_unique'
                );


                $table->index(
                    [
                        'proveedor',
                        'data_id',
                    ],
                    'webhook_pago_proveedor_data_index'
                );


                $table->index(
                    [
                        'procesado',
                        'created_at',
                    ],
                    'webhook_pago_procesado_fecha_index'
                );
            }
        );
    }


    public function down(): void
    {
        Schema::dropIfExists(
            'eventos_webhook_pago'
        );
    }
};
