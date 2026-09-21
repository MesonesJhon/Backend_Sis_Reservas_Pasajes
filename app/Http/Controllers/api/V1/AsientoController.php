<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Vehiculos\ConfigurarAsientosVehiculo;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Vehiculos\ConfigurarAsientosRequest;
use App\Http\Resources\Api\V1\AsientoResource;
use App\Http\Resources\Api\V1\VehiculoResource;
use App\Models\Vehiculo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AsientoController extends Controller
{
    /**
     * Obtiene la distribución física de asientos
     * configurada para un vehículo.
     */
    public function index(
        Vehiculo $vehiculo
    ): AnonymousResourceCollection {
        $asientos = $vehiculo->asientos()
            ->orderBy('piso')
            ->orderBy('fila')
            ->orderBy('columna')
            ->get();

        return AsientoResource::collection($asientos);
    }

    /**
     * Reemplaza la configuración completa de asientos
     * perteneciente al vehículo.
     */
    public function configurar(
        ConfigurarAsientosRequest $request,
        Vehiculo $vehiculo,
        ConfigurarAsientosVehiculo $configurarAsientosVehiculo
    ): JsonResponse {
        $vehiculoConfigurado =
            $configurarAsientosVehiculo->ejecutar(
                $vehiculo,
                $request->validated('asientos')
            );

        return response()->json([
            'mensaje' => 'Configuración de asientos actualizada correctamente.',
            'vehiculo' => new VehiculoResource(
                $vehiculoConfigurado
            ),
        ]);
    }
}
