<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command(
    'inspire',
    function () {
        $this->comment(
            Inspiring::quote()
        );
    }
)->purpose(
    'Display an inspiring quote'
);


/*
|--------------------------------------------------------------------------
| RF-06 - Liberación de bloqueos temporales
|--------------------------------------------------------------------------
*/

Schedule::command(
    'asientos:liberar-bloqueos'
)
    ->everyMinute()
    ->withoutOverlapping();


/*
|--------------------------------------------------------------------------
| RF-07 - Expiración de reservas pendientes
|--------------------------------------------------------------------------
*/

Schedule::command(
    'reservas:expirar-pendientes'
)
    ->everyMinute()
    ->withoutOverlapping();
