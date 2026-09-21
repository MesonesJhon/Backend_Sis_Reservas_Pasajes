<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tarifas_viaje', function (Blueprint $table) {
            $table->id();

            $table->foreignId('viaje_id')
                ->constrained('viajes')
                ->cascadeOnDelete();

            $table->foreignId('punto_origen_id')
                ->constrained('puntos')
                ->restrictOnDelete();

            $table->foreignId('punto_destino_id')
                ->constrained('puntos')
                ->restrictOnDelete();

            /*
             * Decimal es obligatorio para dinero.
             * Nunca float/double.
             */
            $table->decimal('precio', 10, 2);

            $table->boolean('activo')
                ->default(true);

            $table->timestamps();

            /*
             * Un segmento solamente puede tener una tarifa
             * dentro del mismo viaje.
             */
            $table->unique([
                'viaje_id',
                'punto_origen_id',
                'punto_destino_id',
            ], 'tarifa_segmento_viaje_unique');

            $table->index([
                'viaje_id',
                'activo',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tarifas_viaje');
    }
};
