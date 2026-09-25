<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Representa un conflicto con otro intento
 * de pago que ya se encuentra activo.
 */
class ConflictoPagoException extends RuntimeException
{
}
