<?php

namespace Database\Seeders;


use App\Actions\Viajes\CambiarEstadoViaje;

use App\Enums\EstadoVehiculo;
use App\Enums\EstadoViaje;

use App\Models\Asiento;
use App\Models\Punto;
use App\Models\PuntoRuta;
use App\Models\Ruta;
use App\Models\TarifaViaje;
use App\Models\TipoVehiculo;
use App\Models\Vehiculo;
use App\Models\Viaje;

use App\Models\Usuario;
use App\Models\Rol;
use App\Models\PersonalViaje;

use App\Enums\FuncionPersonalViaje;
use Illuminate\Database\Seeder;



class ViajesDemoSeeder extends Seeder
{


    /**
     * Ejecuta la creación de datos demo.
     *
     * Este Seeder respeta el flujo real del sistema:
     *
     * BORRADOR
     *
     *      |
     *      |
     *      ▼
     *
     * PROGRAMADO
     *
     * Durante la programación se generan:
     *
     * - puntos_viaje
     * - asientos_viaje
     *
     */
    public function run(
        CambiarEstadoViaje $cambiarEstadoViaje
    ): void
    {


        /*
        |--------------------------------------------------------------------------
        | 1. CREACIÓN DE PUNTOS
        |--------------------------------------------------------------------------
        */

        $chiclayo = Punto::create([

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



        $lambayeque = Punto::create([

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



        $olmos = Punto::create([

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



        $chota = Punto::create([

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





        /*
        |--------------------------------------------------------------------------
        | 2. CREACIÓN DE RUTA
        |--------------------------------------------------------------------------
        */


        $ruta = Ruta::create([

            'codigo' => 'RUT-001',

            'nombre' => 'Chiclayo - Chota',

            'duracion_estimada_minutos' => 360,

            'activo' => true,

        ]);





        /*
        |--------------------------------------------------------------------------
        | 3. CONFIGURACIÓN DEL RECORRIDO
        |--------------------------------------------------------------------------
        */


        PuntoRuta::create([

            'ruta_id' => $ruta->id,

            'punto_id' => $chiclayo->id,

            'orden' => 1,

            'permite_embarque' => true,

            'permite_desembarque' => false,

            'minutos_desde_origen' => 0,

        ]);



        PuntoRuta::create([

            'ruta_id' => $ruta->id,

            'punto_id' => $lambayeque->id,

            'orden' => 2,

            'permite_embarque' => true,

            'permite_desembarque' => true,

            'minutos_desde_origen' => 30,

        ]);



        PuntoRuta::create([

            'ruta_id' => $ruta->id,

            'punto_id' => $olmos->id,

            'orden' => 3,

            'permite_embarque' => true,

            'permite_desembarque' => true,

            'minutos_desde_origen' => 120,

        ]);



        PuntoRuta::create([

            'ruta_id' => $ruta->id,

            'punto_id' => $chota->id,

            'orden' => 4,

            'permite_embarque' => false,

            'permite_desembarque' => true,

            'minutos_desde_origen' => 360,

        ]);







        /*
        |--------------------------------------------------------------------------
        | 4. VEHÍCULO
        |--------------------------------------------------------------------------
        */


        $tipoVehiculo = TipoVehiculo::firstOrFail();



        $vehiculo = Vehiculo::create([

            'tipo_vehiculo_id' => $tipoVehiculo->id,

            'placa' => 'ABC-123',

            'codigo_interno' => 'VEH-001',

            'marca' => 'Mercedes Benz',

            'modelo' => 'O500',

            'capacidad' => 40,

            'numero_pisos' => 1,

            'caracteristicas' => [

                'wifi' => true,

                'usb' => true,

                'aire_acondicionado' => true,

            ],

            'estado' => EstadoVehiculo::OPERATIVO,

            'activo' => true,

        ]);







        /*
        |--------------------------------------------------------------------------
        | 5. ASIENTOS DEL VEHÍCULO
        |--------------------------------------------------------------------------
        */


        for ($i = 1; $i <= 40; $i++) {


            Asiento::create([

                'vehiculo_id' => $vehiculo->id,

                'codigo' => 'A'.$i,

                'numero' => $i,

                'piso' => 1,

                'fila' => ceil($i / 4),

                'columna' => (($i - 1) % 4) + 1,

                'tipo' => 'NORMAL',

                'caracteristicas' => [],

                'activo' => true,

            ]);

        }

        /*
        |--------------------------------------------------------------------------
        | CREACIÓN DEL CONDUCTOR
        |--------------------------------------------------------------------------
        |
        | El viaje necesita un conductor activo
        | para poder pasar a PROGRAMADO.
        |
        */


        $rolConductor = Rol::where(
            'nombre',
            'CONDUCTOR'
        )->firstOrFail();



        $conductor = Usuario::create([

            'nombres' => 'Carlos',

            'apellidos' => 'Conductor',

            'correo' => 'conductor.demo@test.com',

            'contrasena' => bcrypt('password'),

            'activo' => true,

        ]);



        $conductor->roles()->attach(
            $rolConductor->id
        );






        /*
        |--------------------------------------------------------------------------
        | 6. CREACIÓN DEL VIAJE
        |--------------------------------------------------------------------------
        |
        | Todo viaje nace como BORRADOR.
        |
        */


        $viaje = Viaje::create([

            'codigo' => 'VIA-001',

            'ruta_id' => $ruta->id,

            'vehiculo_id' => $vehiculo->id,

            'salida_programada' => '2026-10-10 08:00:00',

            'llegada_estimada' => '2026-10-10 14:00:00',

            'estado' => EstadoViaje::BORRADOR,

        ]);

        /*
        |--------------------------------------------------------------------------
        | ASIGNACIÓN DE PERSONAL
        |--------------------------------------------------------------------------
        |
        | Se asigna el conductor antes de programar.
        |
        */


        PersonalViaje::create([

            'viaje_id' => $viaje->id,

            'usuario_id' => $conductor->id,

            'funcion' => FuncionPersonalViaje::CONDUCTOR,

        ]);





        /*
        |--------------------------------------------------------------------------
        | 7. TARIFAS POR SEGMENTO
        |--------------------------------------------------------------------------
        */


        TarifaViaje::create([

            'viaje_id' => $viaje->id,

            'punto_origen_id' => $chiclayo->id,

            'punto_destino_id' => $chota->id,

            'precio' => 50,

            'activo' => true,

        ]);



        TarifaViaje::create([

            'viaje_id' => $viaje->id,

            'punto_origen_id' => $lambayeque->id,

            'punto_destino_id' => $chota->id,

            'precio' => 40,

            'activo' => true,

        ]);







        /*
        |--------------------------------------------------------------------------
        | 8. PROGRAMAR VIAJE
        |--------------------------------------------------------------------------
        |
        | Aquí se ejecuta:
        |
        | - Validación de negocio
        | - Consolidación del recorrido
        | - Consolidación de asientos
        |
        */


        $cambiarEstadoViaje->ejecutar(

            $viaje,

            EstadoViaje::PROGRAMADO

        );


    }


}
