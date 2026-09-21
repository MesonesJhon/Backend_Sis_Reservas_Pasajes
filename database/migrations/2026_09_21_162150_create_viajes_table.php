<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('viajes', function (Blueprint $table) {
            $table->id();

            $table->string('codigo', 30)
                ->unique();

            $table->foreignId('ruta_id')
                ->constrained('rutas')
                ->restrictOnDelete();

            $table->foreignId('vehiculo_id')
                ->constrained('vehiculos')
                ->restrictOnDelete();

            $table->timestamp('salida_programada');

            $table->timestamp('llegada_estimada');

            $table->string('estado', 30)
                ->default('BORRADOR');

            $table->text('observaciones')
                ->nullable();

            $table->timestamps();

            $table->index([
                'ruta_id',
                'salida_programada',
            ]);

            $table->index([
                'vehiculo_id',
                'salida_programada',
            ]);

            $table->index('estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('viajes');
    }
};
