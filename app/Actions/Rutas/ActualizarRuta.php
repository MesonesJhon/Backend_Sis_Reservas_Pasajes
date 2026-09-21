<?php

namespace App\Actions\Rutas;

use App\Exceptions\RecorridoRutaInvalidoException;
use App\Models\Ruta;

class ActualizarRuta
{
    public function ejecutar(
        Ruta $ruta,
        array $datos
    ): Ruta {
        $this->validarDuracion(
            $ruta,
            $datos['duracion_estimada_minutos']
        );

        $ruta->update([
            'codigo' => $datos['codigo'],
            'nombre' => $datos['nombre'],
            'duracion_estimada_minutos' =>
                $datos['duracion_estimada_minutos'],
        ]);

        return $ruta->refresh();
    }

    private function validarDuracion(
        Ruta $ruta,
        int $nuevaDuracion
    ): void {
        $ultimoPunto = $ruta->puntosRuta()
            ->orderByDesc('orden')
            ->first();

        if ($ultimoPunto === null) {
            return;
        }

        if (
            $ultimoPunto->minutos_desde_origen
            !== $nuevaDuracion
        ) {
            throw new RecorridoRutaInvalidoException(
                'La duración estimada debe coincidir con los minutos '
                . 'acumulados del último punto del recorrido.'
            );
        }
    }
}
