<?php

namespace App\Enums;

/**
 * Proveedor externo utilizado
 * para procesar el pago.
 *
 * Mantenerlo como enum facilita incorporar
 * otros proveedores posteriormente sin
 * acoplar la tabla pagos a Mercado Pago.
 */
enum ProveedorPago: string
{
    case MERCADO_PAGO = 'MERCADO_PAGO';
}
