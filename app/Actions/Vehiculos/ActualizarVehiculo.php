<?php

namespace App\Actions\Vehiculos;

use App\Exceptions\ConfiguracionAsientosInvalidaException;
use App\Models\Vehiculo;

class ActualizarVehiculo
{
    /**
     * Actualiza la información general del vehículo
     * protegiendo su configuración física existente.
     */
    public function ejecutar(
        Vehiculo $vehiculo,
        array $datos
    ): Vehiculo {
        $this->validarCapacidad(
            $vehiculo,
            $datos['capacidad']
        );

        $this->validarNumeroPisos(
            $vehiculo,
            $datos['numero_pisos']
        );

        $vehiculo->update([
            'tipo_vehiculo_id' => $datos['tipo_vehiculo_id'],
            'placa' => $datos['placa'],
            'codigo_interno' => $datos['codigo_interno'],
            'marca' => $datos['marca'],
            'modelo' => $datos['modelo'],
            'capacidad' => $datos['capacidad'],
            'numero_pisos' => $datos['numero_pisos'],
            'caracteristicas' =>
                $datos['caracteristicas'] ?? null,
        ]);

        return $vehiculo->load('tipoVehiculo');
    }

    /**
     * Impide reducir la capacidad por debajo de la cantidad
     * de asientos que ya se encuentran configurados.
     */
    private function validarCapacidad(
        Vehiculo $vehiculo,
        int $nuevaCapacidad
    ): void {
        $cantidadAsientos = $vehiculo->asientos()->count();

        if ($nuevaCapacidad < $cantidadAsientos) {
            throw new ConfiguracionAsientosInvalidaException(
                "La capacidad no puede reducirse a {$nuevaCapacidad} porque el vehículo tiene {$cantidadAsientos} asientos configurados."
            );
        }
    }

    /**
     * Impide eliminar pisos que todavía contienen
     * asientos configurados.
     */
    private function validarNumeroPisos(
        Vehiculo $vehiculo,
        int $nuevoNumeroPisos
    ): void {
        $pisoMaximoConfigurado = $vehiculo->asientos()
            ->max('piso');

        if (
            $pisoMaximoConfigurado !== null
            && $nuevoNumeroPisos < $pisoMaximoConfigurado
        ) {
            throw new ConfiguracionAsientosInvalidaException(
                "El vehículo tiene asientos configurados hasta el piso {$pisoMaximoConfigurado}. No puede reducirse a {$nuevoNumeroPisos} piso(s)."
            );
        }
    }
}
