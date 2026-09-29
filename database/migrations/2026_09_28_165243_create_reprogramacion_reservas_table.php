<?php

use App\Enums\EstadoReprogramacion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'reprogramaciones_reserva',
            function (
                Blueprint $table
            ): void {

                $table->id();


                /*
                |--------------------------------------------------------------------------
                | Código público
                |--------------------------------------------------------------------------
                |
                | RPG- + ULID
                |
                | 4 + 26 = 30 caracteres.
                |
                */

                $table
                    ->string(
                        'codigo',
                        30
                    )
                    ->unique();


                /*
                |--------------------------------------------------------------------------
                | Reserva origen
                |--------------------------------------------------------------------------
                |
                | Nunca será modificada para convertirla
                | artificialmente en la nueva reserva.
                |
                */

                $table
                    ->foreignId(
                        'reserva_origen_id'
                    )
                    ->constrained(
                        'reservas'
                    )
                    ->restrictOnDelete();


                /*
                |--------------------------------------------------------------------------
                | Reserva destino
                |--------------------------------------------------------------------------
                |
                | Nullable porque inicialmente puede existir
                | una solicitud de reprogramación antes de
                | consolidar la nueva reserva.
                |
                */

                $table
                    ->foreignId(
                        'reserva_destino_id'
                    )
                    ->nullable()
                    ->constrained(
                        'reservas'
                    )
                    ->restrictOnDelete();


                /*
                 * Una misma reserva destino no puede
                 * pertenecer a dos reprogramaciones.
                 */
                $table->unique(
                    'reserva_destino_id',
                    'reprogramacion_reserva_destino_unique'
                );


                /*
                |--------------------------------------------------------------------------
                | Estado
                |--------------------------------------------------------------------------
                */

                $table
                    ->string(
                        'estado',
                        30
                    )
                    ->default(
                        EstadoReprogramacion::SOLICITADA->value
                    );


                /*
                |--------------------------------------------------------------------------
                | Usuario solicitante
                |--------------------------------------------------------------------------
                */

                $table
                    ->foreignId(
                        'solicitada_por_usuario_id'
                    )
                    ->constrained(
                        'usuarios'
                    )
                    ->restrictOnDelete();


                /*
                |--------------------------------------------------------------------------
                | Usuario que completó
                |--------------------------------------------------------------------------
                */

                $table
                    ->foreignId(
                        'completada_por_usuario_id'
                    )
                    ->nullable()
                    ->constrained(
                        'usuarios'
                    )
                    ->restrictOnDelete();


                /*
                |--------------------------------------------------------------------------
                | Usuario que canceló
                |--------------------------------------------------------------------------
                */

                $table
                    ->foreignId(
                        'cancelada_por_usuario_id'
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
                    ->string(
                        'motivo',
                        255
                    )
                    ->nullable();


                $table
                    ->string(
                        'motivo_cancelacion',
                        255
                    )
                    ->nullable();


                /*
                |--------------------------------------------------------------------------
                | Fechas del proceso
                |--------------------------------------------------------------------------
                */

                $table
                    ->timestamp(
                        'solicitada_en'
                    );


                $table
                    ->timestamp(
                        'completada_en'
                    )
                    ->nullable();


                $table
                    ->timestamp(
                        'cancelada_en'
                    )
                    ->nullable();


                $table->timestamps();


                /*
                |--------------------------------------------------------------------------
                | Índices
                |--------------------------------------------------------------------------
                */

                $table->index(
                    [
                        'reserva_origen_id',
                        'estado',
                    ],
                    'reprogramacion_origen_estado_index'
                );


                $table->index(
                    [
                        'estado',
                        'solicitada_en',
                    ],
                    'reprogramacion_estado_fecha_index'
                );


                $table->index(
                    'solicitada_por_usuario_id',
                    'reprogramacion_solicitante_index'
                );
            }
        );
    }


    public function down(): void
    {
        Schema::dropIfExists(
            'reprogramaciones_reserva'
        );
    }
};
