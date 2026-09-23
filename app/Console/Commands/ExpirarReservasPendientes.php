<?php

namespace App\Console\Commands;

use App\Actions\Reservas\ExpirarReserva;
use App\Enums\EstadoReserva;
use App\Models\Reserva;
use Illuminate\Console\Command;

class ExpirarReservasPendientes extends Command
{
    /**
     * Nombre del comando Artisan.
     */
    protected $signature =
        'reservas:expirar-pendientes';


    /**
     * Descripción mostrada por Artisan.
     */
    protected $description =
        'Expira reservas pendientes de pago que superaron su fecha límite';


    public function handle(
        ExpirarReserva $expirarReserva
    ): int {

        $cantidadExpirada = 0;


        /*
        |--------------------------------------------------------------------------
        | Buscar únicamente candidatos vencidos
        |--------------------------------------------------------------------------
        |
        | La Action volverá a validar cada reserva
        | bajo lockForUpdate().
        |
        | Esto es importante porque el estado podría
        | cambiar entre esta consulta y la transacción.
        |
        */

        Reserva::query()

            ->select('id')

            ->where(
                'estado',
                EstadoReserva::PENDIENTE_PAGO
            )

            ->whereNotNull(
                'expira_en'
            )

            ->where(
                'expira_en',
                '<=',
                now()
            )

            ->orderBy('id')

            ->chunkById(
                100,
                function ($reservas) use (
                    $expirarReserva,
                    &$cantidadExpirada
                ): void {

                    foreach ($reservas as $reserva) {

                        if (
                            $expirarReserva
                                ->ejecutar(
                                    $reserva->id
                                )
                        ) {
                            $cantidadExpirada++;
                        }
                    }
                }
            );


        $this->info(
            "Reservas expiradas: {$cantidadExpirada}"
        );


        return Command::SUCCESS;
    }
}
