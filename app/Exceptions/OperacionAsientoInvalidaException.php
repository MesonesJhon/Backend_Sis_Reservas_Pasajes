<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Representa una operación de asiento
 * que no puede ejecutarse debido a
 * las reglas de negocio del RF-06.
 */
class OperacionAsientoInvalidaException extends RuntimeException
{
}
