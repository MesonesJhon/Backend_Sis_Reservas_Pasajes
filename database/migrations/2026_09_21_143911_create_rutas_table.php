<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crea las rutas comerciales/operativas utilizadas
     * posteriormente para programar viajes.
     */
    public function up(): void
    {
        Schema::create('rutas', function (Blueprint $table) {
            $table->id();

            /*
             * Identificador interno legible.
             *
             * Ejemplo:
             * RUT-001
             */
            $table->string('codigo', 30)->unique();

            /*
             * Nombre descriptivo.
             *
             * Ejemplo:
             * Chiclayo - Chota
             */
            $table->string('nombre', 150);

            /*
             * Duración total estimada del recorrido expresada
             * en minutos.
             *
             * Ejemplo:
             * 360 = 6 horas.
             */
            $table->unsignedInteger(
                'duracion_estimada_minutos'
            );

            /*
             * Permite desactivar una ruta sin eliminarla.
             */
            $table->boolean('activo')->default(true);

            $table->timestamps();

            $table->index('activo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rutas');
    }
};
