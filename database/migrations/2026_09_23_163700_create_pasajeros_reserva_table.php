<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'pasajeros_reserva',
            function (Blueprint $table): void {

                $table->id();


                /*
                |--------------------------------------------------------------------------
                | Reserva
                |--------------------------------------------------------------------------
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
                | Ocupación asignada
                |--------------------------------------------------------------------------
                |
                | Esta relación determina:
                |
                | - viaje
                | - asiento
                | - punto de embarque
                | - punto de desembarque
                |
                | Esos datos ya existen en OcupacionAsiento,
                | por lo tanto no los duplicaremos aquí.
                |
                */

                $table->foreignId(
                    'ocupacion_asiento_id'
                )
                    ->constrained(
                        'ocupaciones_asientos'
                    )
                    ->restrictOnDelete();


                /*
                |--------------------------------------------------------------------------
                | Identificación del pasajero
                |--------------------------------------------------------------------------
                */

                $table->string(
                    'tipo_documento',
                    20
                );

                $table->string(
                    'numero_documento',
                    30
                );


                /*
                |--------------------------------------------------------------------------
                | Datos personales
                |--------------------------------------------------------------------------
                */

                $table->string(
                    'nombres',
                    100
                );

                $table->string(
                    'apellidos',
                    100
                );


                /*
                |--------------------------------------------------------------------------
                | Contacto del pasajero
                |--------------------------------------------------------------------------
                |
                | El contacto principal también existe
                | en la reserva.
                |
                | Estos datos son opcionales porque algunos
                | pasajeros pueden ser acompañantes.
                |
                */

                $table->string(
                    'telefono',
                    30
                )->nullable();

                $table->string(
                    'correo',
                    150
                )->nullable();


                /*
                |--------------------------------------------------------------------------
                | Precio histórico
                |--------------------------------------------------------------------------
                |
                | Aquí congelamos el precio utilizado
                | al momento de crear la reserva.
                |
                | Si posteriormente cambia TarifaViaje,
                | la reserva histórica no se modifica.
                |
                */

                $table->decimal(
                    'precio',
                    10,
                    2
                );


                $table->timestamps();


                /*
                |--------------------------------------------------------------------------
                | Integridad
                |--------------------------------------------------------------------------
                |
                | Una ocupación solamente puede corresponder
                | a un pasajero.
                |
                */

                $table->unique(
                    'ocupacion_asiento_id',
                    'pasajeros_reserva_ocupacion_unique'
                );


                /*
                 * La misma persona no debe aparecer dos veces
                 * dentro de una misma reserva utilizando
                 * el mismo documento.
                 */
                $table->unique(
                    [
                        'reserva_id',
                        'tipo_documento',
                        'numero_documento',
                    ],
                    'pasajero_documento_reserva_unique'
                );


                $table->index(
                    'reserva_id',
                    'pasajeros_reserva_reserva_index'
                );
            }
        );
    }


    public function down(): void
    {
        Schema::dropIfExists(
            'pasajeros_reserva'
        );
    }
};
