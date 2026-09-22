<?php

namespace Database\Seeders;

use App\Models\Punto;
use Illuminate\Database\Seeder;

class PuntosSeeder extends Seeder
{
    public function run(): void
    {

        Punto::create([
            'nombre' => 'Terminal Chiclayo',
            'tipo' => 'TERMINAL',
            'departamento' => 'Lambayeque',
            'provincia' => 'Chiclayo',
            'distrito' => 'Chiclayo',
            'direccion' => 'Terminal terrestre Chiclayo',
            'referencia' => 'Terminal principal',
            'latitud' => -6.7714,
            'longitud' => -79.8409,
            'activo' => true,
        ]);


        Punto::create([
            'nombre' => 'Lambayeque Centro',
            'tipo' => 'PARADERO',
            'departamento' => 'Lambayeque',
            'provincia' => 'Lambayeque',
            'distrito' => 'Lambayeque',
            'direccion' => 'Centro de Lambayeque',
            'referencia' => 'Paradero autorizado',
            'latitud' => -6.7011,
            'longitud' => -79.9061,
            'activo' => true,
        ]);


        Punto::create([
            'nombre' => 'Olmos',
            'tipo' => 'PARADERO',
            'departamento' => 'Lambayeque',
            'provincia' => 'Lambayeque',
            'distrito' => 'Olmos',
            'direccion' => 'Zona urbana Olmos',
            'referencia' => 'Paradero principal',
            'latitud' => -5.9848,
            'longitud' => -79.7458,
            'activo' => true,
        ]);


        Punto::create([
            'nombre' => 'Terminal Chota',
            'tipo' => 'TERMINAL',
            'departamento' => 'Cajamarca',
            'provincia' => 'Chota',
            'distrito' => 'Chota',
            'direccion' => 'Terminal terrestre Chota',
            'referencia' => 'Terminal principal',
            'latitud' => -6.5589,
            'longitud' => -78.6486,
            'activo' => true,
        ]);
    }
}
