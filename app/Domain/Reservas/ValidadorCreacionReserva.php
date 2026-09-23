<?php

namespace App\Domain\Reservas;

use App\Enums\EstadoOcupacionAsiento;
use App\Enums\EstadoViaje;
use App\Exceptions\OperacionReservaInvalidaException;
use App\Exceptions\OperacionUsuarioNoPermitidaException;
use App\Models\Usuario;
use App\Models\Viaje;
use Illuminate\Support\Collection;

/**
 * Centraliza las reglas de negocio necesarias
 * antes de crear una reserva.
 *
 * No crea registros ni modifica estados.
 *
 * Su única responsabilidad es determinar
 * si la operación solicitada es válida.
 */
class ValidadorCreacionReserva
{
    public function validar(
        Usuario $usuario,
        Viaje $viaje,
        Collection $ocupaciones,
        array $pasajeros,
        ?string $correoContacto,
        ?string $telefonoContacto
    ): void {

        /*
        |--------------------------------------------------------------------------
        | 1. Viaje comercializable
        |--------------------------------------------------------------------------
        */

        if (
            $viaje->estado !==
            EstadoViaje::PROGRAMADO
        ) {
            throw new OperacionReservaInvalidaException(
                'El viaje no se encuentra disponible para generar reservas.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | 2. Debe existir al menos un pasajero
        |--------------------------------------------------------------------------
        */

        if (count($pasajeros) === 0) {
            throw new OperacionReservaInvalidaException(
                'La reserva debe contener al menos un pasajero.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | 3. Contacto principal
        |--------------------------------------------------------------------------
        */

        $sinCorreo =
            $correoContacto === null
            || trim($correoContacto) === '';

        $sinTelefono =
            $telefonoContacto === null
            || trim($telefonoContacto) === '';

        if ($sinCorreo && $sinTelefono) {
            throw new OperacionReservaInvalidaException(
                'La reserva debe registrar al menos un medio de contacto.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | 4. Ocupaciones duplicadas
        |--------------------------------------------------------------------------
        |
        | Una misma ocupación no puede representar
        | a dos pasajeros dentro de la reserva.
        |
        */

        $ocupacionIds = collect($pasajeros)
            ->pluck('ocupacion_id')
            ->map(
                fn ($id) => (int) $id
            );

        if (
            $ocupacionIds
                ->duplicates()
                ->isNotEmpty()
        ) {
            throw new OperacionReservaInvalidaException(
                'Una ocupación de asiento no puede asignarse a más de un pasajero.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | 5. Todas las ocupaciones deben existir
        |--------------------------------------------------------------------------
        */

        if (
            $ocupaciones->count()
            !== $ocupacionIds->count()
        ) {
            throw new OperacionReservaInvalidaException(
                'Una o más ocupaciones seleccionadas no existen.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | 6. Documento no repetido
        |--------------------------------------------------------------------------
        */

        $documentos = collect($pasajeros)
            ->map(function (array $pasajero): string {

                $tipo = strtoupper(
                    trim(
                        $pasajero['tipo_documento']
                    )
                );

                $numero = trim(
                    $pasajero['numero_documento']
                );

                return "{$tipo}|{$numero}";
            });


        if (
            $documentos
                ->duplicates()
                ->isNotEmpty()
        ) {
            throw new OperacionReservaInvalidaException(
                'Un mismo pasajero no puede registrarse más de una vez en la reserva.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | 7. Validar cada ocupación
        |--------------------------------------------------------------------------
        */

        foreach ($ocupaciones as $ocupacion) {

            /*
             * Todas las ocupaciones deben corresponder
             * al mismo viaje de la reserva.
             */
            if (
                (int) $ocupacion->viaje_id
                !== (int) $viaje->id
            ) {
                throw new OperacionReservaInvalidaException(
                    'Todos los asientos de la reserva deben pertenecer al mismo viaje.'
                );
            }


            /*
             * Una persona solamente puede utilizar los
             * bloqueos que ella misma creó.
             *
             * Esta regla funciona tanto para CLIENTE
             * como para OPERADOR.
             */
            if (
                (int) $ocupacion->usuario_id
                !== (int) $usuario->id
            ) {
                throw new OperacionUsuarioNoPermitidaException(
                    'Uno de los asientos seleccionados pertenece a otro usuario.'
                );
            }


            /*
             * No podemos utilizar nuevamente una
             * ocupación que ya esté ligada a una reserva.
             */
            if ($ocupacion->reserva_id !== null) {
                throw new OperacionReservaInvalidaException(
                    'Uno de los asientos seleccionados ya pertenece a una reserva.'
                );
            }


            /*
             * RF-07 solamente acepta ocupaciones
             * que todavía estén BLOQUEADAS.
             */
            if (
                $ocupacion->estado
                !== EstadoOcupacionAsiento::BLOQUEADO
            ) {
                throw new OperacionReservaInvalidaException(
                    'Todos los asientos deben encontrarse bloqueados antes de crear la reserva.'
                );
            }


            /*
             * Un bloqueo sin expiración se considera
             * inconsistente y no se utiliza.
             */
            if ($ocupacion->expira_en === null) {
                throw new OperacionReservaInvalidaException(
                    'Uno de los bloqueos no tiene una fecha de expiración válida.'
                );
            }


            /*
             * El cliente no puede convertir en reserva
             * un bloqueo que ya venció.
             */
            if ($ocupacion->expira_en->lte(now())) {
                throw new OperacionReservaInvalidaException(
                    'Uno de los bloqueos seleccionados ya expiró.'
                );
            }
        }
    }
}
