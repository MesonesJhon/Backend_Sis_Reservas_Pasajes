<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crea el catálogo de tipos de vehículo utilizados
     * para clasificar las unidades de transporte.
     */
    public function up(): void
    {
        Schema::create('tipos_vehiculo', function (Blueprint $table) {
            $table->id();

            $table->string('nombre', 50)->unique();

            $table->string('descripcion', 255)->nullable();

            $table->boolean('activo')->default(true);

            $table->timestamps();
        });
    }

    /**
     * Elimina la tabla de tipos de vehículo.
     */
    public function down(): void
    {
        Schema::dropIfExists('tipos_vehiculo');
    }
};
