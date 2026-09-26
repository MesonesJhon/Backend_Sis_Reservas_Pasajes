<?php

use App\Enums\EstadoTicket;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'tickets',
            function (
                Blueprint $table
            ): void {

                $table->id();


                /*
                |--------------------------------------------------------------------------
                | Código comercial
                |--------------------------------------------------------------------------
                |
                | No utilizamos el ID interno como
                | identificador público.
                |
                | Ejemplo:
                |
                | TKT-01K7...
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
                | Reserva
                |--------------------------------------------------------------------------
                */

                $table
                    ->foreignId(
                        'reserva_id'
                    )
                    ->constrained(
                        'reservas'
                    )
                    ->restrictOnDelete();


                /*
                |--------------------------------------------------------------------------
                | Pasajero
                |--------------------------------------------------------------------------
                |
                | Un pasajero de una reserva posee
                | exactamente un ticket.
                |
                */

                $table
                    ->foreignId(
                        'pasajero_reserva_id'
                    )
                    ->constrained(
                        'pasajeros_reserva'
                    )
                    ->restrictOnDelete();


                /*
                |--------------------------------------------------------------------------
                | Estado
                |--------------------------------------------------------------------------
                */

                $table
                    ->string(
                        'estado',
                        20
                    )
                    ->default(
                        EstadoTicket::VIGENTE->value
                    );


                /*
                |--------------------------------------------------------------------------
                | Emisión
                |--------------------------------------------------------------------------
                */

                $table
                    ->timestamp(
                        'emitido_en'
                    );


                /*
                |--------------------------------------------------------------------------
                | Validación de embarque
                |--------------------------------------------------------------------------
                */

                $table
                    ->timestamp(
                        'validado_en'
                    )
                    ->nullable();


                $table
                    ->foreignId(
                        'validado_por_usuario_id'
                    )
                    ->nullable()
                    ->constrained(
                        'usuarios'
                    )
                    ->restrictOnDelete();


                /*
                |--------------------------------------------------------------------------
                | Anulación
                |--------------------------------------------------------------------------
                */

                $table
                    ->timestamp(
                        'anulado_en'
                    )
                    ->nullable();


                $table
                    ->string(
                        'motivo_anulacion',
                        255
                    )
                    ->nullable();


                $table->timestamps();


                /*
                |--------------------------------------------------------------------------
                | Integridad
                |--------------------------------------------------------------------------
                |
                | Un pasajero jamás puede recibir
                | dos tickets diferentes por accidente.
                |
                */

                $table->unique(
                    'pasajero_reserva_id',
                    'tickets_pasajero_unique'
                );


                /*
                |--------------------------------------------------------------------------
                | Índices operativos
                |--------------------------------------------------------------------------
                */

                $table->index(
                    [
                        'reserva_id',
                        'estado',
                    ],
                    'tickets_reserva_estado_index'
                );


                $table->index(
                    [
                        'estado',
                        'emitido_en',
                    ],
                    'tickets_estado_emision_index'
                );
            }
        );
    }


    public function down(): void
    {
        Schema::dropIfExists(
            'tickets'
        );
    }
};
