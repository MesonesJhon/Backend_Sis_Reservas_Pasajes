<?php

namespace Database\Factories;

use App\Enums\TipoPunto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Punto>
 */
class PuntoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nombre' => fake()->company(),
            'tipo' => fake()->randomElement(
                TipoPunto::cases()
            ),
            'departamento' => 'Lambayeque',
            'provincia' => 'Chiclayo',
            'distrito' => 'Chiclayo',
            'direccion' => fake()->address(),
            'referencia' => null,
            'latitud' => null,
            'longitud' => null,
            'activo' => true,
        ];
    }
}
