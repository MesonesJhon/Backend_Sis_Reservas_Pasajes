<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Representa una configuración de asientos que incumple
 * las reglas físicas definidas para un vehículo.
 */
class ConfiguracionAsientosInvalidaException extends RuntimeException
{
}
