<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\TipoVehiculoResource;
use App\Models\TipoVehiculo;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TipoVehiculoController extends Controller
{
    /**
     * Lista los tipos de vehículo activos disponibles
     * para registrar nuevas unidades.
     */
    public function index(): AnonymousResourceCollection
    {
        $tiposVehiculo = TipoVehiculo::query()
            ->where('activo', true)
            ->orderBy('nombre')
            ->get();

        return TipoVehiculoResource::collection(
            $tiposVehiculo
        );
    }
}
