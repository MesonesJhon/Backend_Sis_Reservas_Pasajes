<?php

namespace App\Actions\Asientos;

use App\Domain\Asientos\ValidadorBloqueoAsiento;
use App\Enums\EstadoOcupacionAsiento;
use App\Exceptions\OperacionAsientoInvalidaException;
use App\Models\AsientoViaje;
use App\Models\OcupacionAsiento;
use App\Models\Viaje;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class BloquearAsiento
{
    public function __construct(
        private readonly ValidadorBloqueoAsiento $validador
    ) {
    }


    /**
     * Crea un bloqueo temporal de asiento
     * para el usuario autenticado.
     */
    public function ejecutar(
        Viaje $viaje,
        int $asientoViajeId,
        int $origenId,
        int $destinoId,
        int $usuarioId
    ): OcupacionAsiento {

        return DB::transaction(function () use (
            $viaje,
            $asientoViajeId,
            $origenId,
            $destinoId,
            $usuarioId
        ) {

            /*
             * Bloqueamos la fila del asiento del viaje.
             *
             * Esto serializa las solicitudes concurrentes
             * que intentan utilizar el mismo asiento.
             */
            $asientoViaje = AsientoViaje::query()
                ->whereKey($asientoViajeId)
                ->where(
                    'viaje_id',
                    $viaje->id
                )
                ->lockForUpdate()
                ->first();


            if (! $asientoViaje) {
                throw new OperacionAsientoInvalidaException(
                    'El asiento no pertenece al viaje.'
                );
            }


            /*
             * Con el asiento ya bloqueado a nivel
             * de base de datos comprobamos su disponibilidad.
             */
            $this->validador->validar(
                $viaje,
                $asientoViajeId,
                $origenId,
                $destinoId
            );


            /*
             * Creamos el bloqueo indicando explícitamente
             * qué usuario es su propietario.
             */
            return OcupacionAsiento::create([
                'usuario_id' =>
                    $usuarioId,

                'viaje_id' =>
                    $viaje->id,

                'asiento_viaje_id' =>
                    $asientoViajeId,

                'punto_origen_id' =>
                    $origenId,

                'punto_destino_id' =>
                    $destinoId,

                'estado' =>
                    EstadoOcupacionAsiento::BLOQUEADO,

                'expira_en' =>
                    Carbon::now()->addMinutes(10),
            ]);
        });
    }
}
