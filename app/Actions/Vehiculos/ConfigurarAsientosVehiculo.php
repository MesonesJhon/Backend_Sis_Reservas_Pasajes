<?php

namespace App\Actions\Vehiculos;

use App\Exceptions\ConfiguracionAsientosInvalidaException;
use App\Models\Vehiculo;
use Illuminate\Support\Facades\DB;

class ConfigurarAsientosVehiculo
{
    /**
     * Reemplaza la configuración completa de asientos
     * perteneciente a un vehículo.
     *
     * Toda la operación se ejecuta dentro de una transacción
     * para evitar configuraciones parcialmente guardadas.
     */
    public function ejecutar(
        Vehiculo $vehiculo,
        array $asientos
    ): Vehiculo {
        $this->validarConfiguracion(
            $vehiculo,
            $asientos
        );

        return DB::transaction(function () use (
            $vehiculo,
            $asientos
        ) {
            /*
             * RF-02 todavía no posee reservas ni historial asociado
             * a los asientos. Por ello la configuración actual puede
             * reemplazarse completamente.
             *
             * Cuando existan viajes/reservas, esta operación deberá
             * proteger configuraciones que ya posean historial.
             */
            $vehiculo->asientos()->delete();

            foreach ($asientos as $datosAsiento) {
                $vehiculo->asientos()->create([
                    'codigo' => strtoupper(
                        trim($datosAsiento['codigo'])
                    ),

                    'numero' => $datosAsiento['numero'] ?? null,
                    'piso' => $datosAsiento['piso'],
                    'fila' => $datosAsiento['fila'],
                    'columna' => $datosAsiento['columna'],
                    'tipo' => $datosAsiento['tipo'],

                    'caracteristicas' =>
                        $datosAsiento['caracteristicas'] ?? null,

                    'activo' =>
                        $datosAsiento['activo'] ?? true,
                ]);
            }

            return $vehiculo->load([
                'tipoVehiculo',
                'asientos',
            ]);
        });
    }

    /**
     * Comprueba las reglas físicas de la distribución
     * antes de modificar información en la base de datos.
     */
    private function validarConfiguracion(
        Vehiculo $vehiculo,
        array $asientos
    ): void {
        $this->validarCapacidad(
            $vehiculo,
            $asientos
        );

        $this->validarPisos(
            $vehiculo,
            $asientos
        );

        $this->validarPosiciones(
            $asientos
        );
    }

    /**
     * La cantidad de asientos configurados no puede
     * superar la capacidad declarada del vehículo.
     */
    private function validarCapacidad(
        Vehiculo $vehiculo,
        array $asientos
    ): void {
        $cantidadAsientos = count($asientos);

        if ($cantidadAsientos > $vehiculo->capacidad) {
            throw new ConfiguracionAsientosInvalidaException(
                "La configuración contiene {$cantidadAsientos} asientos, pero la capacidad máxima del vehículo es {$vehiculo->capacidad}."
            );
        }
    }

    /**
     * Ningún asiento puede ubicarse en un piso superior
     * a la cantidad de pisos declarada por el vehículo.
     */
    private function validarPisos(
        Vehiculo $vehiculo,
        array $asientos
    ): void {
        foreach ($asientos as $asiento) {
            if ($asiento['piso'] > $vehiculo->numero_pisos) {
                throw new ConfiguracionAsientosInvalidaException(
                    "El asiento {$asiento['codigo']} pertenece al piso {$asiento['piso']}, pero el vehículo solamente tiene {$vehiculo->numero_pisos} piso(s)."
                );
            }
        }
    }

    /**
     * Evita que dos asientos ocupen exactamente
     * la misma posición física.
     */
    private function validarPosiciones(array $asientos): void
    {
        $posiciones = [];

        foreach ($asientos as $asiento) {
            $posicion = implode(':', [
                $asiento['piso'],
                $asiento['fila'],
                $asiento['columna'],
            ]);

            if (isset($posiciones[$posicion])) {
                throw new ConfiguracionAsientosInvalidaException(
                    "Existen dos asientos en la posición piso {$asiento['piso']}, fila {$asiento['fila']}, columna {$asiento['columna']}."
                );
            }

            $posiciones[$posicion] = true;
        }
    }

    /**
     * Mantiene legible la construcción del mensaje
     * de validación de capacidad.
     */
    // private function cantidad(array $asientos): int
    // {
    //     return count($asientos);
    // }
}
