<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Representa una operación de pago que no puede
 * realizarse debido a una regla de negocio.
 */
class OperacionPagoInvalidaException extends RuntimeException
{
}
