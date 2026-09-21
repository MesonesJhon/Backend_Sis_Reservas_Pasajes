<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Ruta>
 */
class RutaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'codigo' => strtoupper(
                fake()->unique()->bothify('RUT-###??')
            ),
            'nombre' => fake()->city()
                . ' - '
                . fake()->city(),
            'duracion_estimada_minutos' =>
                fake()->numberBetween(60, 600),
            'activo' => true,
        ];
    }
}
