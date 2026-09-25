<?php

namespace App\Data\Pagos;

use App\Models\Pago;

/**
 * Resultado generado al iniciar un intento de pago.
 *
 * Permite distinguir si:
 *
 * - se creó un nuevo intento;
 * - se reutilizó un intento existente debido
 *   a una solicitud idempotente.
 */
final readonly class ResultadoInicioPago
{
    public function __construct(

        /**
         * Intento de pago asociado
         * a la operación.
         */
        public Pago $pago,


        /**
         * true:
         * se creó un nuevo intento.
         *
         * false:
         * se recuperó un intento existente
         * utilizando la misma Idempotency-Key.
         */
        public bool $creado

    ) {
    }
}
