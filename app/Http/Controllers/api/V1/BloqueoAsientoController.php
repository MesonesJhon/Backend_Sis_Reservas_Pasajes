<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Asientos\BloquearAsiento;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Asientos\BloquearAsientoRequest;
use App\Http\Resources\Api\V1\OcupacionAsientoResource;
use App\Models\Viaje;

class BloqueoAsientoController extends Controller
{
    public function __construct(
        private readonly BloquearAsiento $action
    ) {
    }


    /**
     * Crea un bloqueo temporal de asiento
     * para el usuario autenticado.
     */
    public function store(
        BloquearAsientoRequest $request
    ): OcupacionAsientoResource {

        /*
         * Obtenemos el viaje solicitado.
         */
        $viaje = Viaje::findOrFail(
            $request->integer(
                'viaje_id'
            )
        );


        /*
         * Ejecutamos el bloqueo.
         *
         * El usuario_id NO debe venir desde
         * el frontend.
         *
         * Se obtiene directamente del usuario
         * autenticado mediante Sanctum.
         */
        $ocupacion = $this->action->ejecutar(
            $viaje,

            $request->integer(
                'asiento_viaje_id'
            ),

            $request->integer(
                'punto_origen_id'
            ),

            $request->integer(
                'punto_destino_id'
            ),

            /*
             * Quinto parámetro que faltaba.
             */
            (int) $request->user()
                ->getAuthIdentifier()
        );


        return new OcupacionAsientoResource(
            $ocupacion
        );
    }
}
