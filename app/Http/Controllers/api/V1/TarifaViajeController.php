<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Viajes\ConfigurarTarifasViaje;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Viajes\ConfigurarTarifasViajeRequest;
use App\Http\Resources\Api\V1\TarifaViajeResource;
use App\Models\Viaje;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TarifaViajeController extends Controller
{
    public function index(
        Viaje $viaje
    ): AnonymousResourceCollection {
        $tarifas = $viaje->tarifas()
            ->with([
                'puntoOrigen',
                'puntoDestino',
            ])
            ->get();

        return TarifaViajeResource::collection($tarifas);
    }

    public function update(
        ConfigurarTarifasViajeRequest $request,
        Viaje $viaje,
        ConfigurarTarifasViaje $configurarTarifas
    ): AnonymousResourceCollection {
        $viaje = $configurarTarifas->ejecutar(
            $viaje,
            $request->validated('tarifas')
        );

        return TarifaViajeResource::collection(
            $viaje->tarifas
        );
    }
}
