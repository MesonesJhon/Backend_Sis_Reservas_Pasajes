<?php

namespace App\Actions\Vehiculos;

use App\Enums\EstadoVehiculo;
use App\Models\Vehiculo;

class CrearVehiculo
{
    /**
     * Registra una nueva unidad de transporte.
     *
     * El vehículo inicia activo administrativamente.
     * Si no se especifica un estado, comienza como OPERATIVO.
     */
    public function ejecutar(array $datos): Vehiculo
    {
        $vehiculo = Vehiculo::create([
            'tipo_vehiculo_id' => $datos['tipo_vehiculo_id'],
            'placa' => $datos['placa'],
            'codigo_interno' => $datos['codigo_interno'],
            'marca' => $datos['marca'],
            'modelo' => $datos['modelo'],
            'capacidad' => $datos['capacidad'],
            'numero_pisos' => $datos['numero_pisos'],
            'caracteristicas' => $datos['caracteristicas'] ?? null,
            'estado' => $datos['estado']
                ?? EstadoVehiculo::OPERATIVO,
            'activo' => true,
        ]);

        return $vehiculo->load('tipoVehiculo');
    }
}
