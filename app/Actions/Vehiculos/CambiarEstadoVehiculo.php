<?php

namespace App\Actions\Vehiculos;

use App\Enums\EstadoVehiculo;
use App\Models\Vehiculo;

class CambiarEstadoVehiculo
{
    /**
     * Modifica la situación operativa de una unidad.
     */
    public function ejecutar(
        Vehiculo $vehiculo,
        EstadoVehiculo $estado
    ): Vehiculo {
        $vehiculo->update([
            'estado' => $estado,
        ]);

        return $vehiculo->load('tipoVehiculo');
    }
}
