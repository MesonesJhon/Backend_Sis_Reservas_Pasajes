<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Tickets\ListarTicketsReserva;
use App\Actions\Tickets\ObtenerTicket;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\TicketResource;
use App\Models\Reserva;
use App\Models\Ticket;
use App\Actions\Tickets\ObtenerQrTicket;
use Illuminate\Http\Response;
use App\Actions\Tickets\ValidarTicket;
use App\Http\Requests\Api\V1\Tickets\ValidarTicketRequest;
use App\Http\Resources\Api\V1\ValidacionTicketResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TicketController extends Controller
{
    public function __construct(
        private readonly ObtenerTicket $obtenerTicket,

        private readonly ListarTicketsReserva $listarTicketsReserva
    ) {
    }


    /**
     * Consulta un ticket mediante
     * su código público.
     */
    public function show(
        Request $request,
        Ticket $ticket
    ): TicketResource {

        $ticket =
            $this
                ->obtenerTicket
                ->ejecutar(
                    $request->user(),
                    $ticket
                );


        return new TicketResource(
            $ticket
        );
    }


    /**
     * Lista todos los tickets
     * pertenecientes a una reserva.
     */
    public function porReserva(
        Request $request,
        Reserva $reserva
    ): AnonymousResourceCollection {

        $tickets =
            $this
                ->listarTicketsReserva
                ->ejecutar(
                    $request->user(),
                    $reserva
                );


        return TicketResource::collection(
            $tickets
        );
    }

    /**
     * Valida un ticket electrónico
     * durante el embarque.
     */
    public function validar(
        ValidarTicketRequest $request,
        ValidarTicket $validarTicket
    ): ValidacionTicketResource {

        $ticket =
            $validarTicket
                ->ejecutar(
                    $request->user(),

                    $request->validated(
                        'token'
                    ),

                    (int) $request->validated(
                        'viaje_id'
                    )
                );


        return new ValidacionTicketResource(
            $ticket
        );
    }


    /**
     * Devuelve el QR gráfico del ticket
     * como una imagen SVG.
     */
    public function qr(
        Request $request,
        Ticket $ticket,
        ObtenerQrTicket $obtenerQrTicket
    ): Response {

        $svg =
            $obtenerQrTicket
                ->ejecutar(
                    $request->user(),
                    $ticket
                );


        return response(
            $svg,
            Response::HTTP_OK,
            [
                'Content-Type' =>
                    'image/svg+xml; charset=UTF-8',

                /*
                * El QR representa un elemento sensible
                * de acceso al viaje.
                *
                * No queremos que proxies públicos
                * almacenen copias permanentes.
                */
                'Cache-Control' =>
                    'private, no-store, max-age=0',

                'Pragma' =>
                    'no-cache',

                'X-Content-Type-Options' =>
                    'nosniff',

                'Content-Disposition' =>
                    'inline; filename="'
                    .$ticket->codigo
                    .'.svg"',
            ]
        );
    }
}
