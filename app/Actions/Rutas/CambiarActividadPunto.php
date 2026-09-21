<?php

namespace App\Actions\Rutas;

use App\Models\Punto;

class CambiarActividadPunto
{
    /**
     * Activa o desactiva administrativamente un punto.
     *
     * No se elimina físicamente para conservar
     * posteriormente la integridad del historial.
     */
    public function ejecutar(
        Punto $punto,
        bool $activo
    ): Punto {
        $punto->update([
            'activo' => $activo,
        ]);

        return $punto->refresh();
    }
}
