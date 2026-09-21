<?php

namespace Database\Seeders;

use App\Models\Permiso;
use App\Models\Rol;
use Illuminate\Database\Seeder;

/**
 * Registra y actualiza los roles y permisos base del sistema.
 *
 * Este Seeder centraliza la matriz de autorización.
 * Los permisos se registran primero y posteriormente
 * se asignan a cada rol según sus responsabilidades.
 */
class RolesPermisosSeeder extends Seeder
{
    /**
     * Registra los permisos y configura los roles iniciales.
     */
    public function run(): void
    {
        $this->registrarPermisos();
        $this->configurarRoles();
    }

    /**
     * Registra o actualiza todos los permisos disponibles.
     *
     * Para agregar permisos de nuevos módulos solamente
     * debe añadirse su definición al arreglo correspondiente.
     */
    private function registrarPermisos(): void
    {
        $permisos = [

            /*
            |--------------------------------------------------------------------------
            | Usuarios y autorización
            |--------------------------------------------------------------------------
            */

            [
                'nombre' => 'usuarios.ver',
                'descripcion' => 'Permite consultar los usuarios registrados.',
            ],
            [
                'nombre' => 'usuarios.crear',
                'descripcion' => 'Permite registrar usuarios internos.',
            ],
            [
                'nombre' => 'usuarios.editar',
                'descripcion' => 'Permite modificar la información de los usuarios.',
            ],
            [
                'nombre' => 'usuarios.desactivar',
                'descripcion' => 'Permite activar o desactivar usuarios.',
            ],
            [
                'nombre' => 'roles.ver',
                'descripcion' => 'Permite consultar los roles y permisos del sistema.',
            ],
            [
                'nombre' => 'roles.asignar',
                'descripcion' => 'Permite asignar roles a los usuarios.',
            ],

            /*
            |--------------------------------------------------------------------------
            | Viajes
            |--------------------------------------------------------------------------
            */

            [
                'nombre' => 'viajes.ver',
                'descripcion' => 'Permite consultar los viajes.',
            ],
            [
                'nombre' => 'viajes.administrar',
                'descripcion' => 'Permite administrar los viajes.',
            ],
            [
                'nombre' => 'viaje.iniciar',
                'descripcion' => 'Permite iniciar un viaje asignado.',
            ],
            [
                'nombre' => 'viaje.finalizar',
                'descripcion' => 'Permite finalizar un viaje asignado.',
            ],

            /*
            |--------------------------------------------------------------------------
            | Reservas
            |--------------------------------------------------------------------------
            */

            [
                'nombre' => 'reservas.ver',
                'descripcion' => 'Permite consultar las reservas.',
            ],
            [
                'nombre' => 'reservas.crear',
                'descripcion' => 'Permite registrar nuevas reservas.',
            ],
            [
                'nombre' => 'reservas.cancelar',
                'descripcion' => 'Permite cancelar reservas.',
            ],

            /*
            |--------------------------------------------------------------------------
            | Tickets
            |--------------------------------------------------------------------------
            */

            [
                'nombre' => 'tickets.ver',
                'descripcion' => 'Permite consultar tickets.',
            ],
            [
                'nombre' => 'tickets.emitir',
                'descripcion' => 'Permite emitir tickets.',
            ],

            /*
            |--------------------------------------------------------------------------
            | Manifiesto
            |--------------------------------------------------------------------------
            */

            [
                'nombre' => 'manifiesto.ver',
                'descripcion' => 'Permite consultar el manifiesto de pasajeros.',
            ],

            /*
            |--------------------------------------------------------------------------
            | Vehículos - RF-02
            |--------------------------------------------------------------------------
            */

            [
                'nombre' => 'vehiculos.ver',
                'descripcion' => 'Permite consultar los vehículos registrados.',
            ],
            [
                'nombre' => 'vehiculos.crear',
                'descripcion' => 'Permite registrar nuevos vehículos.',
            ],
            [
                'nombre' => 'vehiculos.editar',
                'descripcion' => 'Permite modificar la información de los vehículos.',
            ],
            [
                'nombre' => 'vehiculos.cambiar_estado',
                'descripcion' => 'Permite modificar el estado operativo o administrativo de un vehículo.',
            ],

            /*
            |--------------------------------------------------------------------------
            | Tipos de vehículo - RF-02
            |--------------------------------------------------------------------------
            */

            [
                'nombre' => 'tipos_vehiculo.ver',
                'descripcion' => 'Permite consultar los tipos de vehículo.',
            ],
            [
                'nombre' => 'tipos_vehiculo.administrar',
                'descripcion' => 'Permite administrar los tipos de vehículo.',
            ],

            /*
            |--------------------------------------------------------------------------
            | Configuración de asientos - RF-02
            |--------------------------------------------------------------------------
            */

            [
                'nombre' => 'asientos.ver',
                'descripcion' => 'Permite consultar la configuración de asientos de un vehículo.',
            ],
            [
                'nombre' => 'asientos.configurar',
                'descripcion' => 'Permite modificar la configuración de asientos de un vehículo.',
            ],
        ];

        foreach ($permisos as $permiso) {
            Permiso::updateOrCreate(
                [
                    'nombre' => $permiso['nombre'],
                ],
                [
                    'descripcion' => $permiso['descripcion'],
                ]
            );
        }
    }

    /**
     * Crea los roles base y sincroniza sus permisos.
     *
     * sync() garantiza que cada rol conserve exactamente
     * los permisos definidos en este Seeder.
     */
    private function configurarRoles(): void
    {
        $administrador = Rol::firstOrCreate([
            'nombre' => 'ADMINISTRADOR',
        ]);

        $operador = Rol::firstOrCreate([
            'nombre' => 'OPERADOR',
        ]);

        $conductor = Rol::firstOrCreate([
            'nombre' => 'CONDUCTOR',
        ]);

        $cliente = Rol::firstOrCreate([
            'nombre' => 'CLIENTE',
        ]);

        /*
         * El administrador recibe todos los permisos
         * registrados actualmente en el sistema.
         */
        $administrador->permisos()->sync(
            Permiso::pluck('id')
        );

        /*
         * El operador puede realizar operaciones relacionadas
         * con ventas, reservas y consultas operativas.
         */
        $this->sincronizarPermisos(
            $operador,
            [
                'viajes.ver',

                'reservas.ver',
                'reservas.crear',
                'reservas.cancelar',

                'tickets.ver',
                'tickets.emitir',

                'manifiesto.ver',

                'vehiculos.ver',
                'tipos_vehiculo.ver',
                'asientos.ver',
            ]
        );

        /*
         * El conductor accede únicamente a información
         * necesaria para ejecutar los viajes asignados.
         */
        $this->sincronizarPermisos(
            $conductor,
            [
                'viajes.ver',

                'manifiesto.ver',
                'viaje.iniciar',
                'viaje.finalizar',

                'vehiculos.ver',
                'tipos_vehiculo.ver',
                'asientos.ver',
            ]
        );

        /*
         * El cliente accede únicamente a las operaciones
         * necesarias para utilizar el servicio de transporte.
         */
        $this->sincronizarPermisos(
            $cliente,
            [
                'viajes.ver',

                'reservas.crear',
                'reservas.cancelar',

                'tickets.ver',
            ]
        );
    }

    /**
     * Sincroniza los permisos de un rol utilizando
     * sus nombres como identificadores legibles.
     */
    private function sincronizarPermisos(
        Rol $rol,
        array $nombresPermisos
    ): void {
        $permisosIds = Permiso::query()
            ->whereIn('nombre', $nombresPermisos)
            ->pluck('id');

        $rol->permisos()->sync($permisosIds);
    }
}
