<?php

namespace App\Contracts\Pagos;

use App\Data\Pagos\ResultadoOrdenPago;
use App\Data\Pagos\ResultadoConsultaOrdenPago;
use App\Models\Pago;

/**
 * Contrato que debe implementar cualquier
 * proveedor externo de pagos.
 *
 * RF-08 no dependerá directamente
 * de Mercado Pago.
 */
interface PasarelaPago
{
    /**
     * Crea la operación externa correspondiente
     * al intento de pago interno.
     */
    public function crearOrden(
        Pago $pago
    ): ResultadoOrdenPago;

    /**
     * Consulta directamente al proveedor
     * el estado oficial de una operación.
     */
    public function consultarOrden(
        string $orderId
    ): ResultadoConsultaOrdenPago;
}
