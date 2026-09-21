<?php

namespace App\Actions\Rutas;

use App\Models\Punto;

class ActualizarPunto
{
    /**
     * Actualiza los datos generales de un punto sin
     * modificar su estado administrativo.
     */
    public function ejecutar(
        Punto $punto,
        array $datos
    ): Punto {
        $punto->update([
            'nombre' => $datos['nombre'],
            'tipo' => $datos['tipo'],

            'departamento' => $datos['departamento'],
            'provincia' => $datos['provincia'],
            'distrito' => $datos['distrito'],

            'direccion' => $datos['direccion'] ?? null,
            'referencia' => $datos['referencia'] ?? null,

            'latitud' => $datos['latitud'] ?? null,
            'longitud' => $datos['longitud'] ?? null,
        ]);

        return $punto->refresh();
    }
}
