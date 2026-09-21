<?php

namespace Database\Seeders;

use App\Models\TipoVehiculo;
use Illuminate\Database\Seeder;

/**
 * Registra los tipos iniciales de vehículos
 * soportados por el sistema.
 */
class TiposVehiculoSeeder extends Seeder
{
    /**
     * Crea o actualiza el catálogo inicial
     * de tipos de vehículo.
     */
    public function run(): void
    {
        $tiposVehiculo = [
            [
                'nombre' => 'BUS',
                'descripcion' => 'Vehículo de mediana o gran capacidad para transporte de pasajeros.',
            ],
            [
                'nombre' => 'COMBI',
                'descripcion' => 'Vehículo tipo van o minibús utilizado para transporte de pasajeros.',
            ],
            [
                'nombre' => 'COLECTIVO',
                'descripcion' => 'Vehículo de menor capacidad destinado al transporte colectivo de pasajeros.',
            ],
        ];

        foreach ($tiposVehiculo as $tipoVehiculo) {
            TipoVehiculo::updateOrCreate(
                [
                    'nombre' => $tipoVehiculo['nombre'],
                ],
                [
                    'descripcion' => $tipoVehiculo['descripcion'],
                    'activo' => true,
                ]
            );
        }
    }
}
