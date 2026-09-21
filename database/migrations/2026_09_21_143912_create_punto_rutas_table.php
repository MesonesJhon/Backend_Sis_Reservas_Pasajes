<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Relaciona una ruta con los puntos que forman su recorrido.
     *
     * Además de relacionarlos, almacena el orden y las reglas
     * operativas de embarque/desembarque de cada punto.
     */
    public function up(): void
    {
        Schema::create('puntos_ruta', function (Blueprint $table) {
            $table->id();

            $table->foreignId('ruta_id')
                ->constrained('rutas')
                ->cascadeOnDelete();

            $table->foreignId('punto_id')
                ->constrained('puntos')
                ->restrictOnDelete();

            /*
             * Posición del punto dentro del recorrido.
             *
             * 1 = origen
             * último = destino
             */
            $table->unsignedSmallInteger('orden');

            /*
             * Indica qué operaciones están permitidas
             * en este punto para esta ruta específica.
             */
            $table->boolean(
                'permite_embarque'
            )->default(true);

            $table->boolean(
                'permite_desembarque'
            )->default(true);

            /*
             * Tiempo acumulado desde el origen.
             *
             * Ejemplo:
             *
             * Chiclayo      0
             * Lambayeque   30
             * Olmos       120
             * Chota       360
             */
            $table->unsignedInteger(
                'minutos_desde_origen'
            );

            $table->timestamps();

            /*
             * Una posición solamente puede pertenecer
             * a un punto dentro de una misma ruta.
             */
            $table->unique([
                'ruta_id',
                'orden',
            ]);

            /*
             * Un mismo punto no puede aparecer dos veces
             * dentro de la misma ruta.
             */
            $table->unique([
                'ruta_id',
                'punto_id',
            ]);

            /*
             * Facilita consultas por punto.
             */
            $table->index('punto_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('puntos_ruta');
    }
};
