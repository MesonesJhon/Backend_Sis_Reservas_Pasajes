<?php

use App\Enums\EstadoPago;
use App\Enums\ProveedorPago;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'pagos',
            function (Blueprint $table): void {

                $table->id();


                /*
                |--------------------------------------------------------------------------
                | Reserva
                |--------------------------------------------------------------------------
                |
                | Una reserva puede tener varios intentos
                | de pago.
                |
                */

                $table->foreignId(
                    'reserva_id'
                )
                    ->constrained(
                        'reservas'
                    )
                    ->restrictOnDelete();


                /*
                |--------------------------------------------------------------------------
                | Usuario que inició el intento
                |--------------------------------------------------------------------------
                |
                | Es información de auditoría.
                |
                */

                $table->foreignId(
                    'iniciado_por_usuario_id'
                )
                    ->constrained(
                        'usuarios'
                    )
                    ->restrictOnDelete();


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
                | Canal
                |--------------------------------------------------------------------------
                |
                | WEB o MOVIL.
                |
                */

                $table->string(
                    'canal',
                    20
                );


                /*
                |--------------------------------------------------------------------------
                | Idempotencia
                |--------------------------------------------------------------------------
                |
                | Esta clave identifica una intención
                | concreta de pago.
                |
                | Además de ser UNIQUE en nuestra BD,
                | posteriormente enviaremos exactamente
                | la misma clave a Mercado Pago mediante:
                |
                | X-Idempotency-Key
                |
                */

                $table->uuid(
                    'idempotency_key'
                )->unique();


                /*
                |--------------------------------------------------------------------------
                | Referencia externa
                |--------------------------------------------------------------------------
                |
                | Identificador nuestro que será enviado
                | al proveedor.
                |
                | Ejemplo:
                |
                | PAY-01K...
                |
                */

                $table->string(
                    'external_reference',
                    40
                )->unique();


                /*
                |--------------------------------------------------------------------------
                | Identificadores del proveedor
                |--------------------------------------------------------------------------
                |
                | proveedor_order_id:
                | identificador de la Order de Mercado Pago.
                |
                | proveedor_payment_id:
                | identificador de la transacción/pago,
                | cuando el proveedor lo proporcione.
                |
                */

                $table->string(
                    'proveedor_order_id',
                    100
                )
                    ->nullable()
                    ->unique();


                $table->string(
                    'proveedor_payment_id',
                    100
                )
                    ->nullable()
                    ->unique();


                /*
                |--------------------------------------------------------------------------
                | Importe
                |--------------------------------------------------------------------------
                |
                | Es un snapshot de Reserva.total.
                |
                | NUNCA será tomado desde Vue o la app móvil.
                |
                */

                $table->decimal(
                    'monto',
                    10,
                    2
                );


                /*
                 * ISO 4217.
                 *
                 * Para nuestro sistema inicialmente:
                 *
                 * PEN = Sol peruano.
                 */
                $table->char(
                    'moneda',
                    3
                )->default(
                    'PEN'
                );


                /*
                |--------------------------------------------------------------------------
                | Estado interno
                |--------------------------------------------------------------------------
                */

                $table->string(
                    'estado',
                    30
                )->default(
                    EstadoPago::CREADO->value
                );


                /*
                |--------------------------------------------------------------------------
                | Estado informado por Mercado Pago
                |--------------------------------------------------------------------------
                |
                | No sustituye nuestro EstadoPago.
                |
                | Nos sirve para auditoría y diagnóstico.
                |
                */

                $table->string(
                    'estado_proveedor',
                    50
                )->nullable();


                $table->string(
                    'detalle_estado',
                    150
                )->nullable();


                /*
                |--------------------------------------------------------------------------
                | URL del Checkout
                |--------------------------------------------------------------------------
                |
                | Mercado Pago devolverá esta URL al crear
                | la Order.
                |
                | Vue o la aplicación móvil utilizarán
                | esta dirección para abrir Checkout Pro.
                |
                */

                $table->text(
                    'checkout_url'
                )->nullable();


                /*
                |--------------------------------------------------------------------------
                | Casos que necesitan intervención
                |--------------------------------------------------------------------------
                |
                | Ejemplo:
                |
                | Mercado Pago aprobó el pago cuando la
                | reserva ya había expirado.
                |
                */

                $table->boolean(
                    'requiere_revision'
                )->default(
                    false
                );


                $table->string(
                    'motivo_revision',
                    255
                )->nullable();


                /*
                |--------------------------------------------------------------------------
                | Errores de integración
                |--------------------------------------------------------------------------
                |
                | Guardamos información útil sin almacenar
                | respuestas completas potencialmente sensibles.
                |
                */

                $table->string(
                    'error_codigo',
                    100
                )->nullable();


                $table->text(
                    'error_mensaje'
                )->nullable();


                /*
                |--------------------------------------------------------------------------
                | Fechas financieras
                |--------------------------------------------------------------------------
                */

                $table->timestamp(
                    'aprobado_en'
                )->nullable();


                $table->timestamp(
                    'rechazado_en'
                )->nullable();


                $table->timestamp(
                    'cancelado_en'
                )->nullable();


                $table->timestamp(
                    'reembolsado_en'
                )->nullable();


                /*
                 * Última vez que nuestro backend consultó
                 * el estado directamente al proveedor.
                 */
                $table->timestamp(
                    'ultima_verificacion_en'
                )->nullable();


                $table->timestamps();


                /*
                |--------------------------------------------------------------------------
                | Índices
                |--------------------------------------------------------------------------
                */

                $table->index(
                    [
                        'reserva_id',
                        'estado',
                    ],
                    'pagos_reserva_estado_index'
                );


                $table->index(
                    [
                        'proveedor',
                        'estado',
                    ],
                    'pagos_proveedor_estado_index'
                );


                $table->index(
                    [
                        'iniciado_por_usuario_id',
                        'created_at',
                    ],
                    'pagos_usuario_fecha_index'
                );


                $table->index(
                    'requiere_revision',
                    'pagos_revision_index'
                );
            }
        );
    }


    public function down(): void
    {
        Schema::dropIfExists(
            'pagos'
        );
    }
};
