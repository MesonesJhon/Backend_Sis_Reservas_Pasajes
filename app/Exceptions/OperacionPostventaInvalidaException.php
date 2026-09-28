<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Representa una operación de postventa
 * que viola una regla de integridad
 * o trazabilidad.
 */
class OperacionPostventaInvalidaException extends RuntimeException
{
}
