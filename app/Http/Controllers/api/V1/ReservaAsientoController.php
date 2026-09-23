<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Asientos\ReservarAsiento;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Asientos\ReservarAsientoRequest;
use App\Http\Resources\Api\V1\OcupacionAsientoResource;

class ReservaAsientoController extends Controller
{
    public function __construct(
        private readonly ReservarAsiento $action
    ) {
    }


    /**
     * Convierte un bloqueo temporal
     * en una reserva.
     */
    public function store(
        ReservarAsientoRequest $request
    ): OcupacionAsientoResource {

        $ocupacion = $this->action->ejecutar(
            $request->integer(
                'ocupacion_id'
            ),

            (int) $request->user()
                ->getAuthIdentifier()
        );


        return new OcupacionAsientoResource(
            $ocupacion
        );
    }
}
