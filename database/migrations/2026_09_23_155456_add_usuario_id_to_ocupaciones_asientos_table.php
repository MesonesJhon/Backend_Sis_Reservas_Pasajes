<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * Eliminamos la restricción UNIQUE antigua.
         *
         * Esa restricción impedía volver a usar
         * exactamente el mismo asiento y segmento
         * después de que una ocupación quedara LIBERADA.
         */
        Schema::table(
            'ocupaciones_asientos',
            function (Blueprint $table): void {

                $table->dropUnique([
                    'asiento_viaje_id',
                    'punto_origen_id',
                    'punto_destino_id',
                ]);
            }
        );


        /*
         * Guardamos quién creó la ocupación.
         *
         * Se deja nullable por compatibilidad con
         * registros existentes y tests antiguos.
         *
         * Todas las nuevas ocupaciones del RF-06
         * sí tendrán usuario_id.
         */
        Schema::table(
            'ocupaciones_asientos',
            function (Blueprint $table): void {

                $table->foreignId('usuario_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('usuarios')
                    ->restrictOnDelete();


                $table->index(
                    [
                        'usuario_id',
                        'estado',
                    ],
                    'ocupaciones_asientos_usuario_estado_index'
                );
            }
        );
    }


    public function down(): void
    {
        Schema::table(
            'ocupaciones_asientos',
            function (Blueprint $table): void {

                $table->dropIndex(
                    'ocupaciones_asientos_usuario_estado_index'
                );

                $table->dropConstrainedForeignId(
                    'usuario_id'
                );
            }
        );


        Schema::table(
            'ocupaciones_asientos',
            function (Blueprint $table): void {

                $table->unique([
                    'asiento_viaje_id',
                    'punto_origen_id',
                    'punto_destino_id',
                ]);
            }
        );
    }
};
