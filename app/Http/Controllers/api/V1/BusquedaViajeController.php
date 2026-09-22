<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\BusquedaViajes\BuscarViajes;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\BusquedaViajes\BuscarViajesRequest;
use Illuminate\Http\JsonResponse;


/**
 * Controlador encargado de consultas comerciales
 * de viajes disponibles.
 */
class BusquedaViajeController extends Controller
{

    public function __construct(
        private readonly BuscarViajes $buscarViajes
    ) {
    }


    /**
     * Buscar viajes por origen,
     * destino y fecha.
     */
    public function index(
        BuscarViajesRequest $request
    ): JsonResponse {

        $viajes = $this->buscarViajes
            ->ejecutar(
                $request->validated()
            );


        return response()->json([
            'data' => $viajes,
        ]);
    }
}
