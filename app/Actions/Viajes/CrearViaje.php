<?php

namespace App\Actions\Viajes;

use App\Enums\EstadoViaje;
use App\Models\Ruta;
use App\Models\Viaje;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CrearViaje
{
    /**
     * Crea un nuevo viaje en estado BORRADOR.
     *
     * La llegada estimada se calcula desde el backend
     * utilizando la duración configurada en la ruta.
     */
    public function ejecutar(array $datos): Viaje
    {
        return DB::transaction(function () use ($datos) {
            $ruta = Ruta::query()
                ->findOrFail($datos['ruta_id']);

            $salida = Carbon::parse(
                $datos['salida_programada']
            );

            $llegada = $salida
                ->copy()
                ->addMinutes(
                    $ruta->duracion_estimada_minutos
                );

            $viaje = Viaje::create([
                'codigo' => $datos['codigo'],
                'ruta_id' => $ruta->id,
                'vehiculo_id' => $datos['vehiculo_id'],
                'salida_programada' => $salida,
                'llegada_estimada' => $llegada,
                'estado' => EstadoViaje::BORRADOR,
                'observaciones' =>
                    $datos['observaciones'] ?? null,
            ]);

            return $viaje->load([
                'ruta',
                'vehiculo',
            ]);
        });
    }
}
