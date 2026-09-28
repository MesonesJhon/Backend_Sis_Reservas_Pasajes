<?php

namespace App\Enums;

/**
 * Identifica las operaciones de postventa
 * y operación del viaje que requieren
 * trazabilidad consolidada.
 */
enum TipoOperacionPostventa: string
{
    /*
    |--------------------------------------------------------------------------
    | Postventa
    |--------------------------------------------------------------------------
    */

    case CANCELACION =
        'CANCELACION';


    case REPROGRAMACION =
        'REPROGRAMACION';


    case PENALIDAD =
        'PENALIDAD';


    case REEMBOLSO =
        'REEMBOLSO';


    /*
    |--------------------------------------------------------------------------
    | Operación del viaje
    |--------------------------------------------------------------------------
    */

    case NO_SHOW =
        'NO_SHOW';


    case INICIO_EMBARQUE =
        'INICIO_EMBARQUE';


    case INICIO_VIAJE =
        'INICIO_VIAJE';


    case CIERRE_VIAJE =
        'CIERRE_VIAJE';
}
