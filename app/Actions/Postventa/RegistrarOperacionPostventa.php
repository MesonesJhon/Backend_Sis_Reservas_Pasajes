<?php

namespace App\Actions\Postventa;

use App\Enums\OrigenOperacionPostventa;
use App\Enums\TipoOperacionPostventa;
use App\Exceptions\OperacionPostventaInvalidaException;
use App\Models\OperacionPostventa;
use App\Models\Pago;
use App\Models\PasajeroReserva;
use App\Models\Reserva;
use App\Models\Ticket;
use App\Models\Usuario;
use App\Models\Viaje;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Registra de forma centralizada una operación
 * de postventa u operación del viaje.
 *
 * Responsabilidades:
 *
 * - validar relaciones entre entidades;
 * - inferir reserva/viaje cuando sea posible;
 * - validar responsable;
 * - validar importes;
 * - controlar idempotencia;
 * - evitar eventos históricos incoherentes.
 */
class RegistrarOperacionPostventa
{
    public function ejecutar(
        TipoOperacionPostventa $tipo,
        OrigenOperacionPostventa $origen,

        ?int $reservaId = null,
        ?int $viajeId = null,
        ?int $pagoId = null,
        ?int $ticketId = null,
        ?int $pasajeroReservaId = null,

        ?int $ejecutadoPorUsuarioId = null,

        ?string $motivo = null,

        ?string $monto = null,
        ?string $moneda = null,

        ?array $datos = null,

        ?string $claveIdempotencia = null,

        ?CarbonInterface $ocurrioEn = null
    ): OperacionPostventa {

        return DB::transaction(
            function () use (
                $tipo,
                $origen,
                $reservaId,
                $viajeId,
                $pagoId,
                $ticketId,
                $pasajeroReservaId,
                $ejecutadoPorUsuarioId,
                $motivo,
                $monto,
                $moneda,
                $datos,
                $claveIdempotencia,
                $ocurrioEn
            ): OperacionPostventa {

                /*
                |--------------------------------------------------------------------------
                | 1. Recuperar entidades explícitas
                |--------------------------------------------------------------------------
                */

                $reserva =
                    $this->obtenerReserva(
                        $reservaId
                    );


                $viaje =
                    $this->obtenerViaje(
                        $viajeId
                    );


                $pago =
                    $this->obtenerPago(
                        $pagoId
                    );


                $ticket =
                    $this->obtenerTicket(
                        $ticketId
                    );


                $pasajero =
                    $this->obtenerPasajero(
                        $pasajeroReservaId
                    );


                $usuario =
                    $this->obtenerUsuario(
                        $ejecutadoPorUsuarioId
                    );


                /*
                |--------------------------------------------------------------------------
                | 2. Inferir y validar Reserva
                |--------------------------------------------------------------------------
                */

                $reserva =
                    $this->resolverReserva(
                        $reserva,
                        $pago,
                        $ticket,
                        $pasajero
                    );


                /*
                |--------------------------------------------------------------------------
                | 3. Inferir y validar Viaje
                |--------------------------------------------------------------------------
                */

                $viaje =
                    $this->resolverViaje(
                        $reserva,
                        $viaje
                    );


                /*
                |--------------------------------------------------------------------------
                | 4. Resolver pasajero a partir del ticket
                |--------------------------------------------------------------------------
                */

                $pasajero =
                    $this->resolverPasajero(
                        $ticket,
                        $pasajero
                    );


                /*
                |--------------------------------------------------------------------------
                | 5. Contexto obligatorio
                |--------------------------------------------------------------------------
                */

                if (
                    $reserva === null
                    &&
                    $viaje === null
                    &&
                    $pago === null
                    &&
                    $ticket === null
                    &&
                    $pasajero === null
                ) {
                    throw new OperacionPostventaInvalidaException(
                        'La operación postventa debe estar relacionada con al menos una entidad del negocio.'
                    );
                }


                /*
                |--------------------------------------------------------------------------
                | 6. Responsable
                |--------------------------------------------------------------------------
                */

                if (
                    $origen
                    === OrigenOperacionPostventa::USUARIO

                    &&

                    $usuario === null
                ) {
                    throw new OperacionPostventaInvalidaException(
                        'Una operación originada por un usuario debe registrar al usuario responsable.'
                    );
                }


                /*
                |--------------------------------------------------------------------------
                | 7. Motivo
                |--------------------------------------------------------------------------
                */

                $motivo =
                    $this->normalizarTexto(
                        $motivo
                    );


                /*
                |--------------------------------------------------------------------------
                | 8. Importe
                |--------------------------------------------------------------------------
                */

                [
                    $monto,
                    $moneda,
                ] =
                    $this->validarImporte(
                        $monto,
                        $moneda
                    );


                /*
                |--------------------------------------------------------------------------
                | 9. Clave de idempotencia
                |--------------------------------------------------------------------------
                */

                $claveIdempotencia =
                    $this->normalizarClaveIdempotencia(
                        $claveIdempotencia
                    );


                /*
                |--------------------------------------------------------------------------
                | 10. Payload normalizado
                |--------------------------------------------------------------------------
                */

                $atributos = [

                    'tipo' =>
                        $tipo,

                    'origen' =>
                        $origen,

                    'reserva_id' =>
                        $reserva?->id,

                    'viaje_id' =>
                        $viaje?->id,

                    'pago_id' =>
                        $pago?->id,

                    'ticket_id' =>
                        $ticket?->id,

                    'pasajero_reserva_id' =>
                        $pasajero?->id,

                    'ejecutado_por_usuario_id' =>
                        $usuario?->id,

                    'motivo' =>
                        $motivo,

                    'monto' =>
                        $monto,

                    'moneda' =>
                        $moneda,

                    'datos' =>
                        $datos,

                    'clave_idempotencia' =>
                        $claveIdempotencia,

                    'ocurrio_en' =>
                        $ocurrioEn
                        ?? now(),
                ];


                /*
                |--------------------------------------------------------------------------
                | 11. Idempotencia previa
                |--------------------------------------------------------------------------
                */

                if (
                    $claveIdempotencia
                    !== null
                ) {

                    $existente =
                        OperacionPostventa::query()

                            ->where(
                                'clave_idempotencia',
                                $claveIdempotencia
                            )

                            ->first();


                    if (
                        $existente
                        !== null
                    ) {

                        $this
                            ->validarMismaOperacion(
                                $existente,
                                $atributos
                            );


                        return $existente;
                    }
                }


                /*
                |--------------------------------------------------------------------------
                | 12. Crear evento
                |--------------------------------------------------------------------------
                |
                | También capturamos la posible carrera:
                |
                | proceso A -> SELECT no existe
                | proceso B -> SELECT no existe
                | proceso A -> INSERT
                | proceso B -> INSERT UNIQUE violation
                |
                */

                try {

                    return OperacionPostventa::query()
                        ->create(
                            $atributos
                        );

                } catch (
                    QueryException $exception
                ) {

                    if (
                        $claveIdempotencia
                        === null
                    ) {
                        throw $exception;
                    }


                    $existente =
                        OperacionPostventa::query()

                            ->where(
                                'clave_idempotencia',
                                $claveIdempotencia
                            )

                            ->first();


                    if (
                        $existente
                        === null
                    ) {
                        throw $exception;
                    }


                    $this
                        ->validarMismaOperacion(
                            $existente,
                            $atributos
                        );


                    return $existente;
                }
            }
        );
    }


    private function obtenerReserva(
        ?int $id
    ): ?Reserva {

        if ($id === null) {
            return null;
        }


        $modelo =
            Reserva::query()
                ->find(
                    $id
                );


        if ($modelo === null) {
            throw new OperacionPostventaInvalidaException(
                'La reserva indicada no existe.'
            );
        }


        return $modelo;
    }


    private function obtenerViaje(
        ?int $id
    ): ?Viaje {

        if ($id === null) {
            return null;
        }


        $modelo =
            Viaje::query()
                ->find(
                    $id
                );


        if ($modelo === null) {
            throw new OperacionPostventaInvalidaException(
                'El viaje indicado no existe.'
            );
        }


        return $modelo;
    }


    private function obtenerPago(
        ?int $id
    ): ?Pago {

        if ($id === null) {
            return null;
        }


        $modelo =
            Pago::query()
                ->find(
                    $id
                );


        if ($modelo === null) {
            throw new OperacionPostventaInvalidaException(
                'El pago indicado no existe.'
            );
        }


        return $modelo;
    }


    private function obtenerTicket(
        ?int $id
    ): ?Ticket {

        if ($id === null) {
            return null;
        }


        $modelo =
            Ticket::query()
                ->find(
                    $id
                );


        if ($modelo === null) {
            throw new OperacionPostventaInvalidaException(
                'El ticket indicado no existe.'
            );
        }


        return $modelo;
    }


    private function obtenerPasajero(
        ?int $id
    ): ?PasajeroReserva {

        if ($id === null) {
            return null;
        }


        $modelo =
            PasajeroReserva::query()
                ->find(
                    $id
                );


        if ($modelo === null) {
            throw new OperacionPostventaInvalidaException(
                'El pasajero indicado no existe.'
            );
        }


        return $modelo;
    }


    private function obtenerUsuario(
        ?int $id
    ): ?Usuario {

        if ($id === null) {
            return null;
        }


        $modelo =
            Usuario::query()
                ->find(
                    $id
                );


        if ($modelo === null) {
            throw new OperacionPostventaInvalidaException(
                'El usuario responsable indicado no existe.'
            );
        }


        return $modelo;
    }


    /**
     * Determina una única reserva real
     * a partir de todas las referencias.
     */
    private function resolverReserva(
        ?Reserva $reserva,
        ?Pago $pago,
        ?Ticket $ticket,
        ?PasajeroReserva $pasajero
    ): ?Reserva {

        $ids = [];


        if ($reserva !== null) {
            $ids[] =
                (int) $reserva->id;
        }


        if ($pago !== null) {
            $ids[] =
                (int) $pago->reserva_id;
        }


        if ($ticket !== null) {
            $ids[] =
                (int) $ticket->reserva_id;
        }


        if ($pasajero !== null) {
            $ids[] =
                (int) $pasajero->reserva_id;
        }


        $ids =
            array_values(
                array_unique(
                    $ids
                )
            );


        if (
            count(
                $ids
            ) > 1
        ) {
            throw new OperacionPostventaInvalidaException(
                'Las entidades indicadas pertenecen a reservas diferentes.'
            );
        }


        if (
            count(
                $ids
            ) === 0
        ) {
            return null;
        }


        $reservaId =
            $ids[0];


        if (
            $reserva !== null
        ) {
            return $reserva;
        }


        return Reserva::query()
            ->findOrFail(
                $reservaId
            );
    }


    /**
     * Una reserva pertenece a un único viaje.
     */
    private function resolverViaje(
        ?Reserva $reserva,
        ?Viaje $viaje
    ): ?Viaje {

        if (
            $reserva === null
        ) {
            return $viaje;
        }


        if (
            $viaje !== null

            &&

            (int) $viaje->id
            !==
            (int) $reserva->viaje_id
        ) {
            throw new OperacionPostventaInvalidaException(
                'El viaje indicado no corresponde a la reserva.'
            );
        }


        if (
            $viaje !== null
        ) {
            return $viaje;
        }


        return Viaje::query()
            ->findOrFail(
                $reserva->viaje_id
            );
    }


    /**
     * Cuando existe ticket, el pasajero relacionado
     * debe ser exactamente el propietario del ticket.
     */
    private function resolverPasajero(
        ?Ticket $ticket,
        ?PasajeroReserva $pasajero
    ): ?PasajeroReserva {

        if (
            $ticket === null
        ) {
            return $pasajero;
        }


        if (
            $pasajero !== null

            &&

            (int) $ticket->pasajero_reserva_id
            !==
            (int) $pasajero->id
        ) {
            throw new OperacionPostventaInvalidaException(
                'El pasajero indicado no corresponde al ticket.'
            );
        }


        if (
            $pasajero !== null
        ) {
            return $pasajero;
        }


        return PasajeroReserva::query()
            ->findOrFail(
                $ticket->pasajero_reserva_id
            );
    }


    /**
     * Monto y moneda deben viajar juntos.
     */
    private function validarImporte(
        ?string $monto,
        ?string $moneda
    ): array {

        $monto =
            $this->normalizarTexto(
                $monto
            );


        $moneda =
            $this->normalizarTexto(
                $moneda
            );


        if (
            $monto === null
            &&
            $moneda === null
        ) {
            return [
                null,
                null,
            ];
        }


        if (
            $monto === null
            ||
            $moneda === null
        ) {
            throw new OperacionPostventaInvalidaException(
                'El monto y la moneda deben registrarse juntos.'
            );
        }


        /*
         * DECIMAL(10,2):
         *
         * hasta ocho dígitos enteros
         * y hasta dos decimales.
         */
        if (
            ! preg_match(
                '/^\d{1,8}(?:\.\d{1,2})?$/',
                $monto
            )
        ) {
            throw new OperacionPostventaInvalidaException(
                'El monto de la operación postventa no es válido.'
            );
        }


        $moneda =
            strtoupper(
                $moneda
            );


        if (
            ! preg_match(
                '/^[A-Z]{3}$/',
                $moneda
            )
        ) {
            throw new OperacionPostventaInvalidaException(
                'La moneda debe utilizar un código de tres letras.'
            );
        }


        /*
         * Normalizamos 50 -> 50.00.
         */
        $monto =
            number_format(
                (float) $monto,
                2,
                '.',
                ''
            );


        return [
            $monto,
            $moneda,
        ];
    }


    private function normalizarClaveIdempotencia(
        ?string $clave
    ): ?string {

        $clave =
            $this->normalizarTexto(
                $clave
            );


        if ($clave === null) {
            return null;
        }


        if (
            strlen(
                $clave
            ) > 150
        ) {
            throw new OperacionPostventaInvalidaException(
                'La clave de idempotencia supera la longitud permitida.'
            );
        }


        return $clave;
    }


    private function normalizarTexto(
        ?string $valor
    ): ?string {

        if ($valor === null) {
            return null;
        }


        $valor =
            trim(
                $valor
            );


        return $valor === ''
            ? null
            : $valor;
    }


    /**
     * Una misma clave de idempotencia solamente
     * puede representar la misma operación.
     */
    private function validarMismaOperacion(
        OperacionPostventa $existente,
        array $atributos
    ): void {

        $esMisma =
            $existente->tipo
                === $atributos['tipo']

            &&

            $existente->origen
                === $atributos['origen']

            &&

            (int) ($existente->reserva_id ?? 0)
                ===
            (int) ($atributos['reserva_id'] ?? 0)

            &&

            (int) ($existente->viaje_id ?? 0)
                ===
            (int) ($atributos['viaje_id'] ?? 0)

            &&

            (int) ($existente->pago_id ?? 0)
                ===
            (int) ($atributos['pago_id'] ?? 0)

            &&

            (int) ($existente->ticket_id ?? 0)
                ===
            (int) ($atributos['ticket_id'] ?? 0)

            &&

            (int) ($existente->pasajero_reserva_id ?? 0)
                ===
            (int) ($atributos['pasajero_reserva_id'] ?? 0)

            &&

            (int) ($existente->ejecutado_por_usuario_id ?? 0)
                ===
            (int) ($atributos['ejecutado_por_usuario_id'] ?? 0)

            &&

            ($existente->motivo ?? null)
                ===
            ($atributos['motivo'] ?? null)

            &&

            ($existente->monto ?? null)
                ===
            ($atributos['monto'] ?? null)

            &&

            ($existente->moneda ?? null)
                ===
            ($atributos['moneda'] ?? null)

            &&

            ($existente->datos ?? null)
                ==
            ($atributos['datos'] ?? null);


        if (
            ! $esMisma
        ) {
            throw new OperacionPostventaInvalidaException(
                'La clave de idempotencia ya fue utilizada para una operación diferente.'
            );
        }
    }
}
