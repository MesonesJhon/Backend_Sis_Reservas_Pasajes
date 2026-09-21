<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Representa una operación administrativa que no puede ejecutarse
 * debido a las reglas de protección de usuarios del sistema.
 */
class OperacionUsuarioNoPermitidaException extends RuntimeException
{
}
