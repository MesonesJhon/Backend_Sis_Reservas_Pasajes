<?php

namespace App\Enums;

/**
 * Indica desde qué aplicación
 * se inició el pago.
 *
 * El procesamiento financiero sigue ocurriendo
 * en el backend independientemente del canal.
 */
enum CanalPago: string
{
    case WEB = 'WEB';

    case MOVIL = 'MOVIL';
}
