<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crea la tabla principal de vehículos de la empresa.
     *
     * Almacena la identificación de la unidad, sus características
     * generales y su situación operativa.
     */
    public function up(): void
    {
        Schema::create('vehiculos', function (Blueprint $table) {
            $table->id();

            /*
             * Clasificación del vehículo.
             * Ejemplos: BUS, COMBI o COLECTIVO.
             */
            $table->foreignId('tipo_vehiculo_id')
                ->constrained('tipos_vehiculo')
                ->restrictOnDelete();

            /*
             * Identificadores únicos del vehículo.
             */
            $table->string('placa', 15)->unique();
            $table->string('codigo_interno', 30)->unique();

            /*
             * Información comercial de la unidad.
             */
            $table->string('marca', 80);
            $table->string('modelo', 80);

            /*
             * Configuración física general.
             */
            $table->unsignedSmallInteger('capacidad');
            $table->unsignedSmallInteger('numero_pisos')->default(1);

            /*
             * Permite almacenar características variables sin
             * modificar la estructura de la tabla cada vez que
             * aparezca una nueva característica.
             */
            $table->jsonb('caracteristicas')->nullable();

            /*
             * Situación operativa del vehículo.
             *
             * Los valores serán controlados desde EstadoVehiculo.
             */
            $table->string('estado', 30)
                ->default('OPERATIVO');

            /*
             * Indica si la unidad continúa habilitada
             * administrativamente en el sistema.
             */
            $table->boolean('activo')->default(true);

            $table->timestamps();

            /*
             * Índices utilizados frecuentemente en filtros
             * administrativos y operacionales.
             */
            $table->index('estado');
            $table->index('activo');
        });
    }

    /**
     * Elimina la tabla de vehículos.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehiculos');
    }
};
