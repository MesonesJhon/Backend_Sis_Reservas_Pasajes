<?php

namespace App\Domain\Viajes;

use App\Enums\EstadoVehiculo;
use App\Enums\FuncionPersonalViaje;
use App\Exceptions\ProgramacionViajeInvalidaException;
use App\Models\Viaje;

class ValidadorProgramacionViaje
{
    public function __construct(
        private readonly ValidadorDisponibilidadVehiculo $disponibilidadVehiculo,
        private readonly ValidadorDisponibilidadPersonal $disponibilidadPersonal,
    ) {
    }

    /**
     * Comprueba que un viaje BORRADOR posea toda la
     * información necesaria para convertirse en PROGRAMADO.
     */
    public function validar(Viaje $viaje): void
    {
        $viaje->load([
            'ruta.puntosRuta.punto',
            'vehiculo.asientos',
            'personal.usuario.roles',
            'tarifas',
        ]);

        $this->validarRuta($viaje);
        $this->validarVehiculo($viaje);
        $this->validarHorario($viaje);
        $this->validarPersonal($viaje);
        $this->validarTarifas($viaje);
    }

    private function validarRuta(Viaje $viaje): void
    {
        if (! $viaje->ruta->activo) {
            throw new ProgramacionViajeInvalidaException(
                'La ruta seleccionada se encuentra inactiva.'
            );
        }

        if ($viaje->ruta->puntosRuta->count() < 2) {
            throw new ProgramacionViajeInvalidaException(
                'La ruta debe tener un recorrido configurado con al menos dos puntos.'
            );
        }
    }

    private function validarVehiculo(Viaje $viaje): void
    {
        if (! $viaje->vehiculo->activo) {
            throw new ProgramacionViajeInvalidaException(
                'El vehículo seleccionado se encuentra inactivo.'
            );
        }

        if (
            $viaje->vehiculo->estado
            !== EstadoVehiculo::OPERATIVO
        ) {
            throw new ProgramacionViajeInvalidaException(
                'El vehículo debe encontrarse en estado OPERATIVO.'
            );
        }

        if ($viaje->vehiculo->asientos
                ->where('activo', true)
                ->isEmpty()
        ) {
            throw new ProgramacionViajeInvalidaException(
                'El vehículo no tiene asientos activos configurados.'
            );
        }

        if (
            ! $this->disponibilidadVehiculo
                ->estaDisponible($viaje)
        ) {
            throw new ProgramacionViajeInvalidaException(
                'El vehículo ya está asignado a otro viaje en un horario que se superpone.'
            );
        }
    }

    private function validarHorario(Viaje $viaje): void
    {
        if (
            $viaje->llegada_estimada
                ->lessThanOrEqualTo(
                    $viaje->salida_programada
                )
        ) {
            throw new ProgramacionViajeInvalidaException(
                'La llegada estimada debe ser posterior a la salida programada.'
            );
        }
    }

    private function validarPersonal(Viaje $viaje): void
    {
        $conductores = $viaje->personal
            ->filter(
                fn ($asignacion) =>
                    in_array(
                        $asignacion->funcion,
                        [
                            FuncionPersonalViaje::CONDUCTOR,
                            FuncionPersonalViaje::CONDUCTOR_AUXILIAR,
                        ],
                        true
                    )
            );

        if ($conductores->isEmpty()) {
            throw new ProgramacionViajeInvalidaException(
                'El viaje debe tener al menos un conductor asignado.'
            );
        }

        foreach ($viaje->personal as $asignacion) {
            $usuario = $asignacion->usuario;

            if (! $usuario->activo) {
                throw new ProgramacionViajeInvalidaException(
                    "El usuario {$usuario->nombres} {$usuario->apellidos} se encuentra inactivo."
                );
            }

            if (
                in_array(
                    $asignacion->funcion,
                    [
                        FuncionPersonalViaje::CONDUCTOR,
                        FuncionPersonalViaje::CONDUCTOR_AUXILIAR,
                    ],
                    true
                )
                && ! $usuario->tieneRol('CONDUCTOR')
            ) {
                throw new ProgramacionViajeInvalidaException(
                    "El usuario {$usuario->nombres} {$usuario->apellidos} no posee el rol CONDUCTOR."
                );
            }

            if (
                ! $this->disponibilidadPersonal
                    ->estaDisponible(
                        $viaje,
                        $usuario->id
                    )
            ) {
                throw new ProgramacionViajeInvalidaException(
                    "El usuario {$usuario->nombres} {$usuario->apellidos} ya está asignado a otro viaje en un horario que se superpone."
                );
            }
        }
    }

    private function validarTarifas(Viaje $viaje): void
    {
        if (
            ! $viaje->tarifas
                ->where('activo', true)
                ->count()
        ) {
            throw new ProgramacionViajeInvalidaException(
                'El viaje debe tener al menos una tarifa activa.'
            );
        }
    }
}
