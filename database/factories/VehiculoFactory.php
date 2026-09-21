<?php

namespace Database\Factories;

use App\Enums\EstadoVehiculo;
use App\Models\TipoVehiculo;
use App\Models\Vehiculo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vehiculo>
 */
class VehiculoFactory extends Factory
{
    protected $model = Vehiculo::class;

    public function definition(): array
    {
        $tipoVehiculo = TipoVehiculo::query()
            ->firstOrFail();

        return [
            'tipo_vehiculo_id' => $tipoVehiculo->id,

            'placa' => strtoupper(
                fake()->unique()->bothify('???-###')
            ),

            'codigo_interno' => strtoupper(
                fake()->unique()->bothify('VEH-####')
            ),

            'marca' => 'Toyota',
            'modelo' => 'Coaster',
            'capacidad' => 30,
            'numero_pisos' => 1,
            'caracteristicas' => [],
            'estado' => EstadoVehiculo::OPERATIVO,
            'activo' => true,
        ];
    }
}
