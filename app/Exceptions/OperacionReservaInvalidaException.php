<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Representa una operación de reserva que no puede
 * completarse debido a una regla de negocio.
 */
class OperacionReservaInvalidaException extends RuntimeException
{
}
