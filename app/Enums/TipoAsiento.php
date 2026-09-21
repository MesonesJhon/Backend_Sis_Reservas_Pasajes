<?php

namespace App\Enums;

/**
 * Representa las categorías de asiento que puede
 * tener un vehículo de transporte.
 *
 * El tipo permitirá posteriormente aplicar características,
 * servicios o tarifas diferentes según el asiento.
 */
enum TipoAsiento: string
{
    case NORMAL = 'NORMAL';
    case VIP = 'VIP';
    case SEMI_CAMA = 'SEMI_CAMA';
    case CAMA = 'CAMA';
}
