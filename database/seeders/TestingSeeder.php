<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;


class TestingSeeder extends Seeder
{

    public function run(): void
    {

        $this->call([

            /*
             * Roles y permisos
             *
             * Necesarios para:
             * - autenticación
             * - autorización
             * - middleware permisos
             */
            RolesPermisosSeeder::class,


            /*
             * Catálogos vehículos
             */
            TiposVehiculoSeeder::class,


            /*
             * Datos geográficos
             */
            PuntosSeeder::class,

        ]);

    }

}
