<?php

namespace App\Actions\Viajes;

use App\Exceptions\ProgramacionViajeInvalidaException;
use App\Models\Ruta;
use App\Models\Viaje;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ActualizarViaje
{
    /**
     * Actualiza la configuración principal de un viaje.
     *
     * Solo se permite modificar esta información mientras
     * el viaje permanezca en estado BORRADOR.
     */
    public function ejecutar(
        Viaje $viaje,
        array $datos
    ): Viaje {
        if (! $viaje->permiteEdicion()) {
            throw new ProgramacionViajeInvalidaException(
                'Solo los viajes en estado BORRADOR pueden modificar su configuración.'
            );
        }

        return DB::transaction(function () use ($viaje, $datos) {
            $ruta = Ruta::query()
                ->findOrFail($datos['ruta_id']);

            $salida = Carbon::parse(
                $datos['salida_programada']
            );

            /*
             * La llegada no es enviada por el cliente.
             * Se recalcula utilizando la duración actual
             * de la ruta seleccionada.
             */
            $llegada = $salida
                ->copy()
                ->addMinutes(
                    $ruta->duracion_estimada_minutos
                );

            $viaje->update([
                'codigo' => $datos['codigo'],
                'ruta_id' => $ruta->id,
                'vehiculo_id' => $datos['vehiculo_id'],
                'salida_programada' => $salida,
                'llegada_estimada' => $llegada,
                'observaciones' =>
                    $datos['observaciones'] ?? null,
            ]);

            return $viaje
                ->refresh()
                ->load([
                    'ruta',
                    'vehiculo',
                ]);
        });
    }
}
