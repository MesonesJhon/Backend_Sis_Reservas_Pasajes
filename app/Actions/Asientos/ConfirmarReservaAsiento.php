<?php

namespace App\Actions\Asientos;

use App\Enums\EstadoOcupacionAsiento;
use App\Exceptions\OperacionAsientoInvalidaException;
use App\Exceptions\OperacionUsuarioNoPermitidaException;
use App\Models\OcupacionAsiento;
use Illuminate\Support\Facades\DB;

class ConfirmarReservaAsiento
{
    /**
     * Confirma definitivamente una reserva.
     *
     * RESERVADO -> CONFIRMADO
     */
    public function ejecutar(
        int $ocupacionId,
        int $usuarioId
    ): OcupacionAsiento {

        return DB::transaction(function () use (
            $ocupacionId,
            $usuarioId
        ) {

            $ocupacion = OcupacionAsiento::query()
                ->lockForUpdate()
                ->findOrFail(
                    $ocupacionId
                );


            /*
             * Nadie puede confirmar
             * la reserva de otro usuario.
             */
            if (
                (int) $ocupacion->usuario_id
                !== $usuarioId
            ) {
                throw new OperacionUsuarioNoPermitidaException(
                    'No puedes confirmar una ocupación que pertenece a otro usuario.'
                );
            }


            if (
                $ocupacion->estado
                !== EstadoOcupacionAsiento::RESERVADO
            ) {
                throw new OperacionAsientoInvalidaException(
                    'La ocupación no se encuentra en estado RESERVADO.'
                );
            }


            $ocupacion->update([
                'estado' =>
                    EstadoOcupacionAsiento::CONFIRMADO,

                'expira_en' =>
                    null,
            ]);


            return $ocupacion->fresh();
        });
    }
}
