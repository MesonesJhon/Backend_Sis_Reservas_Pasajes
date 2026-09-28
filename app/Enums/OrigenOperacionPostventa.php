<?php

namespace App\Enums;

/**
 * Identifica quién originó conceptualmente
 * una operación de postventa.
 *
 * No siempre existirá un usuario autenticado.
 *
 * Ejemplo:
 *
 * - cancelación manual: USUARIO;
 * - tarea automática: SISTEMA;
 * - webhook Mercado Pago: PROVEEDOR.
 */
enum OrigenOperacionPostventa: string
{
    case USUARIO =
        'USUARIO';


    case SISTEMA =
        'SISTEMA';


    case PROVEEDOR =
        'PROVEEDOR';
}
