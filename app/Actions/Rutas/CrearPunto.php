<?php

namespace App\Actions\Rutas;

use App\Models\Punto;

class CrearPunto
{
    /**
     * Registra un nuevo punto físico disponible
     * para formar parte de recorridos.
     */
    public function ejecutar(array $datos): Punto
    {
        return Punto::create([
            'nombre' => $datos['nombre'],
            'tipo' => $datos['tipo'],

            'departamento' => $datos['departamento'],
            'provincia' => $datos['provincia'],
            'distrito' => $datos['distrito'],

            'direccion' => $datos['direccion'] ?? null,
            'referencia' => $datos['referencia'] ?? null,

            'latitud' => $datos['latitud'] ?? null,
            'longitud' => $datos['longitud'] ?? null,

            /*
             * Todo punto nuevo comienza habilitado.
             */
            'activo' => true,
        ]);
    }
}
