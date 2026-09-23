<?php

namespace App\Console\Commands;


use App\Enums\EstadoOcupacionAsiento;
use App\Models\OcupacionAsiento;
use Illuminate\Console\Command;
use Carbon\Carbon;


class LiberarBloqueosExpirados extends Command
{


    protected $signature = 'asientos:liberar-bloqueos';


    protected $description =
        'Libera bloqueos temporales de asientos expirados';



    public function handle(): int
    {

        $cantidad =
            OcupacionAsiento::query()
                ->where(
                    'estado',
                    EstadoOcupacionAsiento::BLOQUEADO
                )
                ->where(
                    'expira_en',
                    '<=',
                    Carbon::now()
                )
                ->update([
                    'estado' =>
                        EstadoOcupacionAsiento::LIBERADO,

                    'expira_en' =>
                        null,
                ]);


        $this->info(
            "Bloqueos liberados: {$cantidad}"
        );


        return Command::SUCCESS;
    }

}
