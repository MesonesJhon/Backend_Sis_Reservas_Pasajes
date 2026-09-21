<?php

namespace App\Actions\Vehiculos;

use App\Models\Vehiculo;

class CambiarActividadVehiculo
{
    /**
     * Activa o desactiva administrativamente un vehículo.
     *
     * La desactivación permite conservar el registro y su futuro
     * historial sin realizar una eliminación física.
     */
    public function ejecutar(
        Vehiculo $vehiculo,
        bool $activo
    ): Vehiculo {
        $vehiculo->update([
            'activo' => $activo,
        ]);

        return $vehiculo->load('tipoVehiculo');
    }
}
