<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Reservas\CrearReserva;
use App\Actions\Reservas\ListarReservas;
use App\Actions\Reservas\ObtenerReserva;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Reservas\CrearReservaRequest;
use App\Http\Requests\Api\V1\Reservas\ListarReservasRequest;
use App\Http\Resources\Api\V1\ReservaResource;
use App\Actions\Reservas\CancelarReserva;
use App\Http\Requests\Api\V1\Reservas\CancelarReservaRequest;
use App\Actions\Reservas\ConfirmarReserva;
use App\Http\Requests\Api\V1\Reservas\ConfirmarReservaRequest;
use App\Models\Reserva;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

use Illuminate\Http\Request;

class ReservaController extends Controller
{
    public function __construct(
        private readonly CrearReserva $crearReserva,
        private readonly ListarReservas $listarReservas,
        private readonly ObtenerReserva $obtenerReserva,
        private readonly CancelarReserva $cancelarReserva,
        private readonly ConfirmarReserva $confirmarReserva
    ) {
    }


    /**
     * Lista las reservas visibles para
     * el usuario autenticado.
     */
    public function index(
        ListarReservasRequest $request
    ): AnonymousResourceCollection {

        $reservas =
            $this->listarReservas
                ->ejecutar(
                    $request->user(),
                    $request->validated()
                );


        return ReservaResource::collection(
            $reservas
        );
    }


    /**
     * Registra una nueva reserva
     * en estado PENDIENTE_PAGO.
     */
    public function store(
        CrearReservaRequest $request
    ): JsonResponse {

        $reserva =
            $this->crearReserva
                ->ejecutar(
                    $request->user(),
                    $request->validated()
                );


        return (
            new ReservaResource(
                $reserva
            )
        )
            ->response()
            ->setStatusCode(
                Response::HTTP_CREATED
            );
    }


    /**
     * Muestra el detalle de una reserva.
     */
    public function show(
        Request $request,
        Reserva $reserva
    ): ReservaResource {

        $reserva =
            $this->obtenerReserva
                ->ejecutar(
                    $request->user(),
                    $reserva
                );


        return new ReservaResource(
            $reserva
        );
    }


    /**
     * Cancela una reserva y libera
     * sus ocupaciones de asiento.
     */
    public function cancelar(
        CancelarReservaRequest $request,
        Reserva $reserva
    ): ReservaResource {

        $reserva =
            $this->cancelarReserva
                ->ejecutar(
                    $request->user(),
                    $reserva,
                    $request->input('motivo')
                );


        return new ReservaResource(
            $reserva
        );
    }


    /**
     * Confirma definitivamente una reserva.
     *
     * PENDIENTE_PAGO -> CONFIRMADA
     *
     * Todas sus ocupaciones:
     *
     * RESERVADO -> CONFIRMADO
     */
    public function confirmar(
        ConfirmarReservaRequest $request,
        Reserva $reserva
    ): ReservaResource {

        $reserva =
            $this->confirmarReserva
                ->ejecutar(
                    $request->user(),
                    $reserva
                );


        return new ReservaResource(
            $reserva
        );
    }
}
