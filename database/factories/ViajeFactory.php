<?php

namespace Database\Factories;

use App\Enums\EstadoViaje;
use App\Models\Ruta;
use App\Models\Vehiculo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Viaje>
 */
class ViajeFactory extends Factory
{
    public function definition(): array
    {
        $salida = now()->addDays(
            fake()->numberBetween(1, 30)
        )->startOfHour();

        return [
            'codigo' => strtoupper(
                fake()->unique()->bothify('VIA-####')
            ),

            'ruta_id' => Ruta::factory(),

            'vehiculo_id' => Vehiculo::factory(),

            'salida_programada' => $salida,

            'llegada_estimada' =>
                $salida->copy()->addHours(4),

            'estado' => EstadoViaje::BORRADOR,

            'observaciones' => null,
        ];
    }
}
