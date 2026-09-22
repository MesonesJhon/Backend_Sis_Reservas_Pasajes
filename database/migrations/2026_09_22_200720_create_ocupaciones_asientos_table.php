<?php

use App\Enums\EstadoOcupacionAsiento;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::create('ocupaciones_asientos', function (Blueprint $table) {

            $table->id();


            /*
             * Viaje donde pertenece el asiento.
             */
            $table->foreignId('viaje_id')
                ->constrained('viajes')
                ->cascadeOnDelete();


            /*
             * Asiento congelado del viaje.
             */
            $table->foreignId('asiento_viaje_id')
                ->constrained('asientos_viaje')
                ->cascadeOnDelete();



            /*
             * Segmento ocupado.
             *
             * Ejemplo:
             *
             * Chiclayo -> Olmos
             */
            $table->foreignId('punto_origen_id')
                ->constrained('puntos');


            $table->foreignId('punto_destino_id')
                ->constrained('puntos');



            /*
             * Estado de la ocupación.
             */
            $table->string('estado')
                ->default(
                    EstadoOcupacionAsiento::BLOQUEADO->value
                );



            /*
             * Cuando es un bloqueo temporal.
             *
             * Ejemplo:
             * Usuario selecciona asiento,
             * tiene 10 minutos para pagar.
             */
            $table->timestamp('expira_en')
                ->nullable();



            /*
             * Más adelante RF-06:
             *
             * reserva_id
             *
             * todavía no existe.
             */


            $table->timestamps();



            /*
             * Evita duplicar la misma ocupación.
             */
            $table->unique([
                'asiento_viaje_id',
                'punto_origen_id',
                'punto_destino_id',
            ]);



            /*
             * Índices para búsqueda rápida.
             */
            $table->index([
                'viaje_id',
                'estado'
            ]);


            $table->index([
                'asiento_viaje_id',
            ]);

        });
    }



    public function down(): void
    {
        Schema::dropIfExists('ocupaciones_asientos');
    }
};
