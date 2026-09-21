<?php

namespace App\Actions\Rutas;

use App\Exceptions\RecorridoRutaInvalidoException;
use App\Models\Ruta;
use Illuminate\Support\Facades\DB;

class ConfigurarRecorridoRuta
{
    /**
     * Reemplaza completamente el recorrido actual de una ruta.
     *
     * La operación se realiza dentro de una transacción para
     * evitar dejar recorridos parcialmente configurados.
     */
    public function ejecutar(
        Ruta $ruta,
        array $puntos
    ): Ruta {
        return DB::transaction(function () use ($ruta, $puntos) {
            /*
             * Bloqueamos la ruta mientras se modifica su recorrido.
             * Así evitamos dos configuraciones simultáneas sobre
             * la misma ruta.
             */
            $rutaBloqueada = Ruta::query()
                ->whereKey($ruta->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $puntosOrdenados = collect($puntos)
                ->sortBy('orden')
                ->values();

            $this->validarRecorrido(
                $rutaBloqueada,
                $puntosOrdenados->all()
            );

            /*
             * En RF-03 todavía no existen viajes asociados.
             * Por ello podemos reemplazar la configuración.
             *
             * Cuando exista historial de viajes esta operación
             * deberá restringirse o versionarse.
             */
            $rutaBloqueada->puntosRuta()->delete();

            foreach ($puntosOrdenados as $punto) {
                $rutaBloqueada->puntosRuta()->create([
                    'punto_id' => $punto['punto_id'],
                    'orden' => $punto['orden'],
                    'permite_embarque' =>
                        $punto['permite_embarque'],
                    'permite_desembarque' =>
                        $punto['permite_desembarque'],
                    'minutos_desde_origen' =>
                        $punto['minutos_desde_origen'],
                ]);
            }

            return $rutaBloqueada->load([
                'puntosRuta' => fn ($query) =>
                    $query
                        ->with('punto')
                        ->orderBy('orden'),
            ]);
        });
    }

    private function validarRecorrido(
        Ruta $ruta,
        array $puntos
    ): void {
        $this->validarOrdenConsecutivo($puntos);
        $this->validarOperacionesPermitidas($puntos);
        $this->validarOrigen($puntos);
        $this->validarDestino($puntos);
        $this->validarTiempos($ruta, $puntos);
    }

    /**
     * Exigimos 1, 2, 3... N.
     *
     * Evitamos configuraciones como:
     * 1, 3, 8, 10.
     */
    private function validarOrdenConsecutivo(
        array $puntos
    ): void {
        foreach ($puntos as $indice => $punto) {
            $ordenEsperado = $indice + 1;

            if ($punto['orden'] !== $ordenEsperado) {
                throw new RecorridoRutaInvalidoException(
                    "El recorrido debe utilizar órdenes consecutivos "
                    . "comenzando en 1. Se esperaba el orden "
                    . "{$ordenEsperado}."
                );
            }
        }
    }

    /**
     * Todo punto debe tener al menos una función operativa.
     */
    private function validarOperacionesPermitidas(
        array $puntos
    ): void {
        foreach ($puntos as $punto) {
            if (
                !$punto['permite_embarque']
                && !$punto['permite_desembarque']
            ) {
                throw new RecorridoRutaInvalidoException(
                    "El punto ubicado en el orden "
                    . "{$punto['orden']} debe permitir embarque, "
                    . "desembarque o ambas operaciones."
                );
            }
        }
    }

    /**
     * El primer punto representa el origen.
     */
    private function validarOrigen(array $puntos): void
    {
        $origen = $puntos[0];

        if (!$origen['permite_embarque']) {
            throw new RecorridoRutaInvalidoException(
                'El punto de origen debe permitir embarque.'
            );
        }

        if ($origen['minutos_desde_origen'] !== 0) {
            throw new RecorridoRutaInvalidoException(
                'El punto de origen debe tener '
                . 'minutos_desde_origen igual a 0.'
            );
        }
    }

    /**
     * El último punto representa el destino.
     */
    private function validarDestino(array $puntos): void
    {
        $destino = $puntos[count($puntos) - 1];

        if (!$destino['permite_desembarque']) {
            throw new RecorridoRutaInvalidoException(
                'El punto de destino debe permitir desembarque.'
            );
        }
    }

    /**
     * Los tiempos acumulados deben aumentar estrictamente.
     * Además, el último debe coincidir con la duración total.
     */
    private function validarTiempos(
        Ruta $ruta,
        array $puntos
    ): void {
        $minutosAnteriores = null;

        foreach ($puntos as $punto) {
            $minutosActuales =
                $punto['minutos_desde_origen'];

            if (
                $minutosAnteriores !== null
                && $minutosActuales <= $minutosAnteriores
            ) {
                throw new RecorridoRutaInvalidoException(
                    'Los minutos desde el origen deben aumentar '
                    . 'progresivamente durante el recorrido.'
                );
            }

            $minutosAnteriores = $minutosActuales;
        }

        $destino = $puntos[count($puntos) - 1];

        if (
            $destino['minutos_desde_origen']
            !== $ruta->duracion_estimada_minutos
        ) {
            throw new RecorridoRutaInvalidoException(
                'Los minutos acumulados del destino deben coincidir '
                . 'con la duración estimada de la ruta.'
            );
        }
    }
}
