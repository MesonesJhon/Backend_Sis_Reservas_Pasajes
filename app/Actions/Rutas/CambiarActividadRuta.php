<?php

namespace App\Actions\Rutas;

use App\Models\Ruta;

class CambiarActividadRuta
{
    public function ejecutar(
        Ruta $ruta,
        bool $activo
    ): Ruta {
        $ruta->update([
            'activo' => $activo,
        ]);

        return $ruta->refresh();
    }
}
