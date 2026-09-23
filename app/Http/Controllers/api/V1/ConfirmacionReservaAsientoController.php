<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Asientos\ConfirmarReservaAsiento;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Asientos\ConfirmarReservaAsientoRequest;
use App\Http\Resources\Api\V1\OcupacionAsientoResource;

class ConfirmacionReservaAsientoController extends Controller
{
    public function __construct(
        private readonly ConfirmarReservaAsiento $action
    ) {
    }


    public function store(
        ConfirmarReservaAsientoRequest $request
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
