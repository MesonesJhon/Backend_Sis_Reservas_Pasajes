<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('puntos_viaje', function (Blueprint $table) {
            $table->id();

            $table->foreignId('viaje_id')
                ->constrained('viajes')
                ->cascadeOnDelete();

            $table->foreignId('punto_id')
                ->constrained('puntos')
                ->restrictOnDelete();

            $table->unsignedSmallInteger('orden');

            $table->boolean('permite_embarque');

            $table->boolean('permite_desembarque');

            $table->unsignedInteger(
                'minutos_desde_origen'
            );

            $table->timestamps();

            $table->unique([
                'viaje_id',
                'orden',
            ]);

            $table->unique([
                'viaje_id',
                'punto_id',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('puntos_viaje');
    }
};
