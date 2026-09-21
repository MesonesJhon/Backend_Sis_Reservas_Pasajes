<?php

namespace App\Actions\Rutas;

use App\Models\Ruta;

class CrearRuta
{
    public function ejecutar(array $datos): Ruta
    {
        return Ruta::create([
            'codigo' => $datos['codigo'],
            'nombre' => $datos['nombre'],
            'duracion_estimada_minutos' =>
                $datos['duracion_estimada_minutos'],
            'activo' => true,
        ]);
    }
}
