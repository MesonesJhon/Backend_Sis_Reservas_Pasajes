<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personal_viaje', function (Blueprint $table) {
            $table->id();

            $table->foreignId('viaje_id')
                ->constrained('viajes')
                ->cascadeOnDelete();

            $table->foreignId('usuario_id')
                ->constrained('usuarios')
                ->restrictOnDelete();

            $table->string('funcion', 30);

            $table->timestamps();

            /*
             * Una misma persona no debe aparecer dos veces
             * dentro del mismo viaje.
             */
            $table->unique([
                'viaje_id',
                'usuario_id',
            ]);

            $table->index([
                'usuario_id',
                'viaje_id',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_viaje');
    }
};
