<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;


class DatabaseSeeder extends Seeder
{

    public function run(): void
    {

        $this->call([

            /*
            |--------------------------------------------------------------------------
            | Seguridad y usuarios
            |--------------------------------------------------------------------------
            */

            RolesPermisosSeeder::class,

            AdministradorSeeder::class,


            /*
            |--------------------------------------------------------------------------
            | Catálogos base
            |--------------------------------------------------------------------------
            */

            TiposVehiculoSeeder::class,

            // PuntosSeeder::class,


            /*
            |--------------------------------------------------------------------------
            | Datos demo del sistema
            |--------------------------------------------------------------------------
            |
            | Crea:
            | - Ruta
            | - Vehículo
            | - Asientos
            | - Viaje
            | - Tarifas
            |
            */

            // ViajesDemoSeeder::class,

        ]);

    }

}
