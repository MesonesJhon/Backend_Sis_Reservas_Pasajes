<?php

use App\Enums\OrigenOperacionPostventa;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'operaciones_postventa',
            function (
                Blueprint $table
            ): void {

                $table->id();


                /*
                |--------------------------------------------------------------------------
                | Tipo de operación
                |--------------------------------------------------------------------------
                */

                $table->string(
                    'tipo',
                    40
                );


                /*
                |--------------------------------------------------------------------------
                | Origen
                |--------------------------------------------------------------------------
                |
                | USUARIO:
                | una persona autenticada ejecutó la operación.
                |
                | SISTEMA:
                | la operación fue automática.
                |
                | PROVEEDOR:
                | provino de un sistema externo,
                | por ejemplo Mercado Pago.
                |
                */

                $table
                    ->string(
                        'origen',
                        20
                    )
                    ->default(
                        OrigenOperacionPostventa::SISTEMA->value
                    );


                /*
                |--------------------------------------------------------------------------
                | Reserva
                |--------------------------------------------------------------------------
                */

                $table
                    ->foreignId(
                        'reserva_id'
                    )
                    ->nullable()
                    ->constrained(
                        'reservas'
                    )
                    ->restrictOnDelete();


                /*
                |--------------------------------------------------------------------------
                | Viaje
                |--------------------------------------------------------------------------
                */

                $table
                    ->foreignId(
                        'viaje_id'
                    )
                    ->nullable()
                    ->constrained(
                        'viajes'
                    )
                    ->restrictOnDelete();


                /*
                |--------------------------------------------------------------------------
                | Pago
                |--------------------------------------------------------------------------
                */

                $table
                    ->foreignId(
                        'pago_id'
                    )
                    ->nullable()
                    ->constrained(
                        'pagos'
                    )
                    ->restrictOnDelete();


                /*
                |--------------------------------------------------------------------------
                | Ticket
                |--------------------------------------------------------------------------
                */

                $table
                    ->foreignId(
                        'ticket_id'
                    )
                    ->nullable()
                    ->constrained(
                        'tickets'
                    )
                    ->restrictOnDelete();


                /*
                |--------------------------------------------------------------------------
                | Pasajero
                |--------------------------------------------------------------------------
                |
                | Será especialmente útil para:
                |
                | - no-show;
                | - operaciones individuales;
                | - manifiesto.
                |
                */

                $table
                    ->foreignId(
                        'pasajero_reserva_id'
                    )
                    ->nullable()
                    ->constrained(
                        'pasajeros_reserva'
                    )
                    ->restrictOnDelete();


                /*
                |--------------------------------------------------------------------------
                | Usuario responsable
                |--------------------------------------------------------------------------
                |
                | Nullable porque las operaciones pueden
                | originarse en SISTEMA o PROVEEDOR.
                |
                */

                $table
                    ->foreignId(
                        'ejecutado_por_usuario_id'
                    )
                    ->nullable()
                    ->constrained(
                        'usuarios'
                    )
                    ->restrictOnDelete();


                /*
                |--------------------------------------------------------------------------
                | Motivo
                |--------------------------------------------------------------------------
                */

                $table
                    ->text(
                        'motivo'
                    )
                    ->nullable();


                /*
                |--------------------------------------------------------------------------
                | Importe relacionado
                |--------------------------------------------------------------------------
                |
                | Será utilizado posteriormente para:
                |
                | penalidades;
                | diferencias tarifarias;
                | reembolsos.
                |
                */

                $table
                    ->decimal(
                        'monto',
                        10,
                        2
                    )
                    ->nullable();


                $table
                    ->string(
                        'moneda',
                        3
                    )
                    ->nullable();


                /*
                |--------------------------------------------------------------------------
                | Datos adicionales
                |--------------------------------------------------------------------------
                |
                | No duplicaremos columnas para cada operación futura.
                |
                | Aquí podremos almacenar información contextual como:
                |
                | estado_anterior
                | estado_nuevo
                | reserva_destino_id
                | viaje_destino_id
                | penalidad_porcentaje
                |
                | SIN guardar secretos del proveedor.
                |
                */

                $table
                    ->json(
                        'datos'
                    )
                    ->nullable();


                /*
                |--------------------------------------------------------------------------
                | Idempotencia
                |--------------------------------------------------------------------------
                |
                | Permite que procesos externos o automáticos
                | no creen dos eventos históricos idénticos.
                |
                | Ejemplo futuro:
                |
                | mercadopago:reembolso:15
                |
                */

                $table
                    ->string(
                        'clave_idempotencia',
                        150
                    )
                    ->nullable()
                    ->unique();


                /*
                |--------------------------------------------------------------------------
                | Momento real de la operación
                |--------------------------------------------------------------------------
                */

                $table->timestamp(
                    'ocurrio_en'
                );


                /*
                |--------------------------------------------------------------------------
                | Auditoría técnica
                |--------------------------------------------------------------------------
                */

                $table->timestamps();


                /*
                |--------------------------------------------------------------------------
                | Índices
                |--------------------------------------------------------------------------
                */

                $table->index(
                    [
                        'reserva_id',
                        'ocurrio_en',
                    ],
                    'postventa_reserva_fecha_index'
                );


                $table->index(
                    [
                        'viaje_id',
                        'ocurrio_en',
                    ],
                    'postventa_viaje_fecha_index'
                );


                $table->index(
                    [
                        'tipo',
                        'ocurrio_en',
                    ],
                    'postventa_tipo_fecha_index'
                );


                $table->index(
                    [
                        'ejecutado_por_usuario_id',
                        'ocurrio_en',
                    ],
                    'postventa_usuario_fecha_index'
                );
            }
        );
    }


    public function down(): void
    {
        Schema::dropIfExists(
            'operaciones_postventa'
        );
    }
};
