<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asientos_viaje', function (Blueprint $table) {
            $table->id();

            /*
             * Viaje al que pertenece este asiento.
             *
             * Si se elimina físicamente un viaje durante desarrollo,
             * su inventario también se elimina.
             */
            $table->foreignId('viaje_id')
                ->constrained('viajes')
                ->cascadeOnDelete();

            /*
             * Referencia al asiento físico original.
             *
             * Se conserva para conocer de qué asiento del vehículo
             * nació este registro, pero los datos importantes también
             * se copian como snapshot.
             */
            $table->foreignId('asiento_id')
                ->constrained('asientos')
                ->restrictOnDelete();

            /*
             * Snapshot de la configuración física del asiento.
             *
             * Aunque posteriormente cambie la configuración del
             * vehículo, el viaje conservará estos valores.
             */
            $table->string('codigo', 20);

            $table->unsignedSmallInteger('numero')
                ->nullable();

            $table->unsignedTinyInteger('piso');

            $table->unsignedSmallInteger('fila')
                ->nullable();

            $table->unsignedSmallInteger('columna')
                ->nullable();

            $table->string('tipo', 30);

            $table->jsonb('caracteristicas')
                ->nullable();

            /*
             * Indica si el asiento formó parte del inventario
             * comercial del viaje al momento de programarlo.
             */
            $table->boolean('activo')
                ->default(true);

            $table->timestamps();

            /*
             * Un asiento físico solamente puede aparecer una vez
             * dentro del mismo viaje.
             */
            $table->unique([
                'viaje_id',
                'asiento_id',
            ]);

            /*
             * El código también debe ser único dentro del viaje.
             */
            $table->unique([
                'viaje_id',
                'codigo',
            ]);

            $table->index([
                'viaje_id',
                'activo',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asientos_viaje');
    }
};
