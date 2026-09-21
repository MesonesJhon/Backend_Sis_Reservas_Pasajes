<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Viajes\ActualizarViaje;
use App\Actions\Viajes\CrearViaje;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Viajes\ActualizarViajeRequest;
use App\Http\Requests\Api\V1\Viajes\CrearViajeRequest;
use App\Http\Resources\Api\V1\ViajeResource;
use App\Models\Viaje;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

use App\Actions\Viajes\CambiarEstadoViaje;
use App\Http\Requests\Api\V1\Viajes\CambiarEstadoViajeRequest;
use App\Enums\EstadoViaje;

class ViajeController extends Controller
{
    /**
     * Lista los viajes registrados.
     */
    public function index(): AnonymousResourceCollection
    {
        $viajes = Viaje::query()
            ->with([
                'ruta',
                'vehiculo',
            ])
            ->orderByDesc('salida_programada')
            ->paginate(20);

        return ViajeResource::collection($viajes);
    }

    /**
     * Registra un nuevo viaje en estado BORRADOR.
     */
    public function store(
        CrearViajeRequest $request,
        CrearViaje $crearViaje
    ) {
        $viaje = $crearViaje->ejecutar(
            $request->validated()
        );

        return (new ViajeResource($viaje))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Consulta el detalle completo de un viaje.
     */
    public function show(Viaje $viaje): ViajeResource
    {
        $viaje->load([
            'ruta',
            'vehiculo',

            'puntosViaje' => fn ($consulta) =>
                $consulta
                    ->with('punto')
                    ->orderBy('orden'),

            'personal.usuario',

            'tarifas' => fn ($consulta) =>
                $consulta
                    ->with([
                        'puntoOrigen',
                        'puntoDestino',
                    ])
                    ->orderBy('punto_origen_id')
                    ->orderBy('punto_destino_id'),
        ]);

        return new ViajeResource($viaje);
    }

    /**
     * Modifica la configuración de un viaje BORRADOR.
     */
    public function update(
        ActualizarViajeRequest $request,
        Viaje $viaje,
        ActualizarViaje $actualizarViaje
    ): ViajeResource {
        $viaje = $actualizarViaje->ejecutar(
            $viaje,
            $request->validated()
        );

        return new ViajeResource($viaje);
    }

    /**
     * Ejecuta una transición válida del estado del viaje.
     */
    public function cambiarEstado(
        CambiarEstadoViajeRequest $request,
        Viaje $viaje,
        CambiarEstadoViaje $cambiarEstado
    ): ViajeResource {
        $nuevoEstado = EstadoViaje::from(
            $request->validated('estado')
        );

        $viaje = $cambiarEstado->ejecutar(
            $viaje,
            $nuevoEstado
        );

        return new ViajeResource($viaje);
    }
}
