<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'reservas',
            function (Blueprint $table): void {

                /*
                |--------------------------------------------------------------------------
                | Pago que confirmó la reserva
                |--------------------------------------------------------------------------
                |
                | NULL:
                |
                | - reserva pendiente;
                | - cancelada;
                | - expirada;
                | - o confirmación manual del RF-07.
                |
                | Con valor:
                |
                | la reserva fue confirmada electrónicamente
                | mediante ese intento de pago.
                |
                */

                $table->foreignId(
                    'pago_confirmacion_id'
                )
                    ->nullable()
                    ->after('total')
                    ->constrained(
                        'pagos'
                    )
                    ->restrictOnDelete()
                    ->unique();
            }
        );
    }


    public function down(): void
    {
        Schema::table(
            'reservas',
            function (Blueprint $table): void {

                $table->dropConstrainedForeignId(
                    'pago_confirmacion_id'
                );
            }
        );
    }
};
