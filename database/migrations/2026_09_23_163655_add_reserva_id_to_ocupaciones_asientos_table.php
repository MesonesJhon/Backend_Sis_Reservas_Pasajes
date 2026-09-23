<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'ocupaciones_asientos',
            function (Blueprint $table): void {

                /*
                |--------------------------------------------------------------------------
                | Reserva propietaria de la ocupación
                |--------------------------------------------------------------------------
                |
                | Durante RF-06 una ocupación puede existir
                | todavía sin reserva:
                |
                | BLOQUEADO
                | reserva_id = NULL
                |
                | Cuando RF-07 genere la reserva:
                |
                | RESERVADO
                | reserva_id = X
                |
                */

                $table->foreignId(
                    'reserva_id'
                )
                    ->nullable()
                    ->after(
                        'usuario_id'
                    )
                    ->constrained(
                        'reservas'
                    )
                    ->restrictOnDelete();


                /*
                 * Facilita buscar todas las ocupaciones
                 * pertenecientes a una reserva.
                 */
                $table->index(
                    [
                        'reserva_id',
                        'estado',
                    ],
                    'ocupaciones_asientos_reserva_estado_index'
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
                    'ocupaciones_asientos_reserva_estado_index'
                );

                $table->dropConstrainedForeignId(
                    'reserva_id'
                );
            }
        );
    }
};
