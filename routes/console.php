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



/*
|--------------------------------------------------------------------------
| Reconciliación de pagos
|--------------------------------------------------------------------------
|
| Se ejecuta cada minuto.
|
| El propio comando solamente selecciona pagos
| que no hayan sido verificados recientemente.
|
| withoutOverlapping evita que dos ejecuciones
| del reconciliador se pisen entre sí.
|
*/

Schedule::command(
    'pagos:reconciliar-pendientes',
    [
        '--antiguedad' =>
            1,

        '--limite' =>
            100,
    ]
)
    ->everyMinute()

    ->withoutOverlapping(
        5
    );
