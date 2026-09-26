<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Representa una operación de ticket que no puede
 * completarse debido a una regla de negocio.
 */
class OperacionTicketInvalidaException extends RuntimeException
{
}
