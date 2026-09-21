<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crea el catálogo de puntos físicos que posteriormente
     * podrán utilizarse en una o varias rutas.
     */
    public function up(): void
    {
        Schema::create('puntos', function (Blueprint $table) {
            $table->id();

            $table->string('nombre', 150);

            /*
             * TERMINAL
             * AGENCIA
             * PARADERO
             * PUNTO_AUTORIZADO
             *
             * Los valores válidos se controlarán mediante TipoPunto.
             */
            $table->string('tipo', 30);

            /*
             * Información geográfica administrativa.
             */
            $table->string('departamento', 100);
            $table->string('provincia', 100);
            $table->string('distrito', 100);

            /*
             * La dirección puede ser nula porque algunos paraderos
             * pueden identificarse mediante una referencia o
             * coordenadas en lugar de una dirección formal.
             */
            $table->string('direccion', 255)->nullable();
            $table->string('referencia', 255)->nullable();

            /*
             * Coordenadas opcionales para futura integración
             * con mapas, aplicaciones móviles y geolocalización.
             */
            $table->decimal('latitud', 10, 7)->nullable();
            $table->decimal('longitud', 10, 7)->nullable();

            /*
             * Permite retirar administrativamente un punto sin
             * eliminar físicamente su información.
             */
            $table->boolean('activo')->default(true);

            $table->timestamps();

            $table->index('tipo');
            $table->index('activo');

            /*
             * Facilita búsquedas geográficas de puntos.
             */
            $table->index([
                'departamento',
                'provincia',
                'distrito',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('puntos');
    }
};
