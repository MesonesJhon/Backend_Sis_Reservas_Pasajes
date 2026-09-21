<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Vehiculos\ActualizarVehiculo;
use App\Actions\Vehiculos\CambiarActividadVehiculo;
use App\Actions\Vehiculos\CambiarEstadoVehiculo;
use App\Actions\Vehiculos\CrearVehiculo;
use App\Enums\EstadoVehiculo;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Vehiculos\ActualizarVehiculoRequest;
use App\Http\Requests\Api\V1\Vehiculos\CambiarActividadVehiculoRequest;
use App\Http\Requests\Api\V1\Vehiculos\CambiarEstadoVehiculoRequest;
use App\Http\Requests\Api\V1\Vehiculos\CrearVehiculoRequest;
use App\Http\Resources\Api\V1\VehiculoResource;
use App\Models\Vehiculo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class VehiculoController extends Controller
{
    /**
     * Lista los vehículos registrados.
     */
    public function index(): AnonymousResourceCollection
    {
        $vehiculos = Vehiculo::query()
            ->with('tipoVehiculo')
            ->withCount('asientos')
            ->orderByDesc('id')
            ->paginate(20);

        return VehiculoResource::collection($vehiculos);
    }

    /**
     * Registra una nueva unidad de transporte.
     */
    public function store(
        CrearVehiculoRequest $request,
        CrearVehiculo $crearVehiculo
    ): JsonResponse {
        $vehiculo = $crearVehiculo->ejecutar(
            $request->validated()
        );

        return response()->json([
            'mensaje' => 'Vehículo registrado correctamente.',
            'vehiculo' => new VehiculoResource($vehiculo),
        ], 201);
    }

    /**
     * Obtiene el detalle de un vehículo.
     */
    public function show(Vehiculo $vehiculo): VehiculoResource
    {
        $vehiculo->load('tipoVehiculo');
        $vehiculo->loadCount('asientos');

        return new VehiculoResource($vehiculo);
    }

    /**
     * Actualiza la información general de un vehículo.
     */
    public function update(
        ActualizarVehiculoRequest $request,
        Vehiculo $vehiculo,
        ActualizarVehiculo $actualizarVehiculo
    ): JsonResponse {
        $vehiculoActualizado = $actualizarVehiculo->ejecutar(
            $vehiculo,
            $request->validated()
        );

        return response()->json([
            'mensaje' => 'Vehículo actualizado correctamente.',
            'vehiculo' => new VehiculoResource($vehiculoActualizado),
        ]);
    }

    /**
     * Cambia el estado operativo del vehículo.
     */
    public function cambiarEstado(
        CambiarEstadoVehiculoRequest $request,
        Vehiculo $vehiculo,
        CambiarEstadoVehiculo $cambiarEstadoVehiculo
    ): JsonResponse {
        $estado = EstadoVehiculo::from(
            $request->validated('estado')
        );

        $vehiculoActualizado = $cambiarEstadoVehiculo->ejecutar(
            $vehiculo,
            $estado
        );

        return response()->json([
            'mensaje' => 'Estado del vehículo actualizado correctamente.',
            'vehiculo' => new VehiculoResource($vehiculoActualizado),
        ]);
    }

    /**
     * Activa o desactiva administrativamente el vehículo.
     */
    public function cambiarActividad(
        CambiarActividadVehiculoRequest $request,
        Vehiculo $vehiculo,
        CambiarActividadVehiculo $cambiarActividadVehiculo
    ): JsonResponse {
        $vehiculoActualizado = $cambiarActividadVehiculo->ejecutar(
            $vehiculo,
            $request->boolean('activo')
        );

        return response()->json([
            'mensaje' => $vehiculoActualizado->activo
                ? 'Vehículo activado correctamente.'
                : 'Vehículo desactivado correctamente.',

            'vehiculo' => new VehiculoResource(
                $vehiculoActualizado
            ),
        ]);
    }
}
