<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Rutas\ActualizarRuta;
use App\Actions\Rutas\CambiarActividadRuta;
use App\Actions\Rutas\CrearRuta;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Rutas\ActualizarRutaRequest;
use App\Http\Requests\Api\V1\Rutas\CambiarActividadRutaRequest;
use App\Http\Requests\Api\V1\Rutas\CrearRutaRequest;
use App\Http\Resources\Api\V1\RutaResource;
use App\Models\Ruta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use App\Actions\Rutas\ConfigurarRecorridoRuta;
use App\Http\Requests\Api\V1\Rutas\ConfigurarRecorridoRutaRequest;

class RutaController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $rutas = Ruta::query()
            ->with([
                'puntosRuta' => fn ($query) =>
                    $query
                        ->with('punto')
                        ->orderBy('orden'),
            ])
            ->orderBy('nombre')
            ->paginate(20);

        return RutaResource::collection($rutas);
    }

    public function store(
        CrearRutaRequest $request,
        CrearRuta $crearRuta
    ): JsonResponse {
        $ruta = $crearRuta->ejecutar(
            $request->validated()
        );

        return response()->json([
            'mensaje' => 'Ruta registrada correctamente.',
            'ruta' => new RutaResource($ruta),
        ], 201);
    }

    public function show(Ruta $ruta): RutaResource
    {
        $ruta->load([
            'puntosRuta' => fn ($query) =>
                $query
                    ->with('punto')
                    ->orderBy('orden'),
        ]);

        return new RutaResource($ruta);
    }

    public function update(
        ActualizarRutaRequest $request,
        Ruta $ruta,
        ActualizarRuta $actualizarRuta
    ): JsonResponse {
        $ruta = $actualizarRuta->ejecutar(
            $ruta,
            $request->validated()
        );

        $ruta->load([
            'puntosRuta' => fn ($query) =>
                $query
                    ->with('punto')
                    ->orderBy('orden'),
        ]);

        return response()->json([
            'mensaje' => 'Ruta actualizada correctamente.',
            'ruta' => new RutaResource($ruta),
        ]);
    }

    public function cambiarActividad(
        CambiarActividadRutaRequest $request,
        Ruta $ruta,
        CambiarActividadRuta $cambiarActividadRuta
    ): JsonResponse {
        $ruta = $cambiarActividadRuta->ejecutar(
            $ruta,
            $request->boolean('activo')
        );

        return response()->json([
            'mensaje' => $ruta->activo
                ? 'Ruta activada correctamente.'
                : 'Ruta desactivada correctamente.',

            'ruta' => new RutaResource($ruta),
        ]);
    }

    public function configurarRecorrido(
        ConfigurarRecorridoRutaRequest $request,
        Ruta $ruta,
        ConfigurarRecorridoRuta $configurarRecorridoRuta
    ): JsonResponse {
        $ruta = $configurarRecorridoRuta->ejecutar(
            $ruta,
            $request->validated('puntos')
        );

        return response()->json([
            'mensaje' => 'Recorrido configurado correctamente.',
            'ruta' => new RutaResource($ruta),
        ]);
    }
}
