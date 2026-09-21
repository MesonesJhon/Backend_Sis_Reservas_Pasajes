<?php

namespace App\Actions\Viajes;

use App\Domain\Rutas\ValidadorSegmentoRuta;
use App\Exceptions\ProgramacionViajeInvalidaException;
use App\Models\Viaje;
use Illuminate\Support\Facades\DB;

class ConfigurarTarifasViaje
{
    public function __construct(
        private readonly ValidadorSegmentoRuta $validadorSegmento
    ) {
    }

    public function ejecutar(
        Viaje $viaje,
        array $tarifas
    ): Viaje {
        if (! $viaje->permiteEdicion()) {
            throw new ProgramacionViajeInvalidaException(
                'Las tarifas solo pueden modificarse mientras el viaje esté en BORRADOR.'
            );
        }

        /*
         * Evitamos que el mismo segmento aparezca dos veces
         * dentro del payload.
         */
        $segmentos = [];

        foreach ($tarifas as $tarifa) {
            $clave = $tarifa['punto_origen_id']
                .'-'
                .$tarifa['punto_destino_id'];

            if (isset($segmentos[$clave])) {
                throw new ProgramacionViajeInvalidaException(
                    'No se puede registrar dos veces la tarifa del mismo segmento.'
                );
            }

            $segmentos[$clave] = true;

            if (
                ! $this->validadorSegmento->esValido(
                    $viaje->ruta,
                    $tarifa['punto_origen_id'],
                    $tarifa['punto_destino_id']
                )
            ) {
                throw new ProgramacionViajeInvalidaException(
                    'Una de las tarifas corresponde a un segmento inválido de la ruta.'
                );
            }
        }

        return DB::transaction(function () use ($viaje, $tarifas) {
            /*
             * Solo reemplazamos las tarifas después de que
             * todo el nuevo conjunto haya sido validado.
             */
            $viaje->tarifas()->delete();

            foreach ($tarifas as $tarifa) {
                $viaje->tarifas()->create([
                    'punto_origen_id' =>
                        $tarifa['punto_origen_id'],

                    'punto_destino_id' =>
                        $tarifa['punto_destino_id'],

                    'precio' => $tarifa['precio'],

                    'activo' => true,
                ]);
            }

            return $viaje->load([
                'tarifas.puntoOrigen',
                'tarifas.puntoDestino',
            ]);
        });
    }
}
