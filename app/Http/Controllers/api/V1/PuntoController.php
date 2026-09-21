<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Rutas\ActualizarPunto;
use App\Actions\Rutas\CambiarActividadPunto;
use App\Actions\Rutas\CrearPunto;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Rutas\ActualizarPuntoRequest;
use App\Http\Requests\Api\V1\Rutas\CambiarActividadPuntoRequest;
use App\Http\Requests\Api\V1\Rutas\CrearPuntoRequest;
use App\Http\Resources\Api\V1\PuntoResource;
use App\Models\Punto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PuntoController extends Controller
{
    /**
     * Lista los puntos registrados.
     */
    public function index(): AnonymousResourceCollection
    {
        $puntos = Punto::query()
            ->orderBy('departamento')
            ->orderBy('provincia')
            ->orderBy('distrito')
            ->orderBy('nombre')
            ->paginate(20);

        return PuntoResource::collection($puntos);
    }

    /**
     * Registra un nuevo punto.
     */
    public function store(
        CrearPuntoRequest $request,
        CrearPunto $crearPunto
    ): JsonResponse {
        $punto = $crearPunto->ejecutar(
            $request->validated()
        );

        return response()->json([
            'mensaje' => 'Punto registrado correctamente.',
            'punto' => new PuntoResource($punto),
        ], 201);
    }

    /**
     * Obtiene el detalle de un punto.
     */
    public function show(Punto $punto): PuntoResource
    {
        return new PuntoResource($punto);
    }

    /**
     * Actualiza la información general de un punto.
     */
    public function update(
        ActualizarPuntoRequest $request,
        Punto $punto,
        ActualizarPunto $actualizarPunto
    ): JsonResponse {
        $puntoActualizado = $actualizarPunto->ejecutar(
            $punto,
            $request->validated()
        );

        return response()->json([
            'mensaje' => 'Punto actualizado correctamente.',
            'punto' => new PuntoResource(
                $puntoActualizado
            ),
        ]);
    }

    /**
     * Activa o desactiva administrativamente un punto.
     */
    public function cambiarActividad(
        CambiarActividadPuntoRequest $request,
        Punto $punto,
        CambiarActividadPunto $cambiarActividadPunto
    ): JsonResponse {
        $puntoActualizado =
            $cambiarActividadPunto->ejecutar(
                $punto,
                $request->boolean('activo')
            );

        return response()->json([
            'mensaje' => $puntoActualizado->activo
                ? 'Punto activado correctamente.'
                : 'Punto desactivado correctamente.',

            'punto' => new PuntoResource(
                $puntoActualizado
            ),
        ]);
    }
}
