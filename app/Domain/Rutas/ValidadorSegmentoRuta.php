<?php

namespace App\Domain\Rutas;

use App\Models\PuntoRuta;
use App\Models\Ruta;

class ValidadorSegmentoRuta
{
    /**
     * Determina si dos puntos representan un segmento
     * comercialmente válido dentro de una ruta.
     *
     * Para que sea válido:
     *
     * 1. Ambos puntos deben pertenecer a la ruta.
     * 2. El origen debe permitir embarque.
     * 3. El destino debe permitir desembarque.
     * 4. El origen debe encontrarse antes del destino.
     */
    public function esValido(
        Ruta $ruta,
        int $puntoOrigenId,
        int $puntoDestinoId
    ): bool {
        if ($puntoOrigenId === $puntoDestinoId) {
            return false;
        }

        $origen = $this->buscarPuntoRuta(
            $ruta,
            $puntoOrigenId
        );

        $destino = $this->buscarPuntoRuta(
            $ruta,
            $puntoDestinoId
        );

        if ($origen === null || $destino === null) {
            return false;
        }

        if (!$origen->permite_embarque) {
            return false;
        }

        if (!$destino->permite_desembarque) {
            return false;
        }

        return $origen->orden < $destino->orden;
    }

    /**
     * Calcula la duración estimada de un segmento.
     *
     * Devuelve null cuando el segmento no es válido.
     */
    public function calcularDuracion(
        Ruta $ruta,
        int $puntoOrigenId,
        int $puntoDestinoId
    ): ?int {
        if (
            !$this->esValido(
                $ruta,
                $puntoOrigenId,
                $puntoDestinoId
            )
        ) {
            return null;
        }

        $origen = $this->buscarPuntoRuta(
            $ruta,
            $puntoOrigenId
        );

        $destino = $this->buscarPuntoRuta(
            $ruta,
            $puntoDestinoId
        );

        return $destino->minutos_desde_origen
            - $origen->minutos_desde_origen;
    }

    private function buscarPuntoRuta(
        Ruta $ruta,
        int $puntoId
    ): ?PuntoRuta {
        return $ruta->puntosRuta()
            ->where('punto_id', $puntoId)
            ->first();
    }
}
