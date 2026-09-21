<?php

namespace App\Enums;

/**
 * Define los tipos de puntos físicos que pueden formar
 * parte del recorrido de una ruta.
 */
enum TipoPunto: string
{
    case TERMINAL = 'TERMINAL';
    case AGENCIA = 'AGENCIA';
    case PARADERO = 'PARADERO';
    case PUNTO_AUTORIZADO = 'PUNTO_AUTORIZADO';
}
