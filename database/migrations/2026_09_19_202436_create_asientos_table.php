<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crea los asientos físicos pertenecientes a cada vehículo.
     *
     * La disponibilidad de un asiento para un viaje no se almacena
     * aquí. Esta tabla representa únicamente la configuración física.
     */
    public function up(): void
    {
        Schema::create('asientos', function (Blueprint $table) {
            $table->id();

            $table->foreignId('vehiculo_id')
                ->constrained('vehiculos')
                ->cascadeOnDelete();

            /*
             * Código visible utilizado para identificar el asiento.
             * Ejemplos: 01, 02, A1, VIP-01.
             */
            $table->string('codigo', 20);

            /*
             * Número de asiento cuando la configuración utiliza
             * numeración secuencial.
             */
            $table->unsignedSmallInteger('numero')->nullable();

            /*
             * Posición física dentro del vehículo.
             */
            $table->unsignedSmallInteger('piso')->default(1);
            $table->unsignedSmallInteger('fila');
            $table->unsignedSmallInteger('columna');

            /*
             * Categoría física o comercial del asiento.
             */
            $table->string('tipo', 30)
                ->default('NORMAL');

            /*
             * Características particulares del asiento.
             *
             * Ejemplo:
             * {
             *   "cargador_usb": true,
             *   "ventana": true
             * }
             */
            $table->jsonb('caracteristicas')->nullable();

            /*
             * Permite deshabilitar físicamente un asiento
             * sin eliminar su registro histórico.
             */
            $table->boolean('activo')->default(true);

            $table->timestamps();

            /*
             * Un vehículo no puede tener dos asientos
             * con el mismo código.
             */
            $table->unique([
                'vehiculo_id',
                'codigo',
            ]);

            /*
             * Dos asientos no pueden ocupar exactamente
             * la misma posición física.
             */
            $table->unique([
                'vehiculo_id',
                'piso',
                'fila',
                'columna',
            ]);

            $table->index([
                'vehiculo_id',
                'activo',
            ]);
        });
    }

    /**
     * Elimina la configuración física de asientos.
     */
    public function down(): void
    {
        Schema::dropIfExists('asientos');
    }
};
