<?php

namespace App\Actions\Asientos;

use App\Enums\EstadoOcupacionAsiento;
use App\Exceptions\OperacionAsientoInvalidaException;
use App\Exceptions\OperacionUsuarioNoPermitidaException;
use App\Models\OcupacionAsiento;
use Illuminate\Support\Facades\DB;

class ReservarAsiento
{
    /**
     * Convierte un bloqueo temporal
     * en una reserva.
     */
    public function ejecutar(
        int $ocupacionId,
        int $usuarioId
    ): OcupacionAsiento {

        $resultado = DB::transaction(
            function () use (
                $ocupacionId,
                $usuarioId
            ): array {

                $ocupacion = OcupacionAsiento::query()
                    ->lockForUpdate()
                    ->findOrFail(
                        $ocupacionId
                    );


                /*
                 * La ocupación solamente puede ser
                 * modificada por quien la creó.
                 */
                if (
                    (int) $ocupacion->usuario_id
                    !== $usuarioId
                ) {
                    throw new OperacionUsuarioNoPermitidaException(
                        'No puedes reservar una ocupación que pertenece a otro usuario.'
                    );
                }


                /*
                 * Únicamente BLOQUEADO puede pasar
                 * a RESERVADO.
                 */
                if (
                    $ocupacion->estado
                    !== EstadoOcupacionAsiento::BLOQUEADO
                ) {
                    throw new OperacionAsientoInvalidaException(
                        'El asiento no está bloqueado.'
                    );
                }


                /*
                 * Si ya expiró, lo liberamos.
                 *
                 * La excepción se lanzará después
                 * de confirmar esta transacción.
                 */
                if (
                    $ocupacion->expira_en
                    && $ocupacion->expira_en->isPast()
                ) {
                    $ocupacion->update([
                        'estado' =>
                            EstadoOcupacionAsiento::LIBERADO,

                        'expira_en' =>
                            null,
                    ]);

                    return [
                        'expirado' => true,

                        'ocupacion' =>
                            $ocupacion->fresh(),
                    ];
                }


                /*
                 * BLOQUEADO -> RESERVADO
                 */
                $ocupacion->update([
                    'estado' =>
                        EstadoOcupacionAsiento::RESERVADO,

                    'expira_en' =>
                        null,
                ]);


                return [
                    'expirado' => false,

                    'ocupacion' =>
                        $ocupacion->fresh(),
                ];
            }
        );


        /*
         * Aquí la transacción ya hizo COMMIT.
         */
        if ($resultado['expirado']) {
            throw new OperacionAsientoInvalidaException(
                'El bloqueo del asiento expiró.'
            );
        }


        return $resultado['ocupacion'];
    }
}
