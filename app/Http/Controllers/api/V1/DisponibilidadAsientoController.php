<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Disponibilidad\CalculadorDisponibilidadAsientos;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\BusquedaViajes\ConsultarAsientosDisponiblesRequest;
use App\Http\Resources\Api\V1\AsientoDisponibleResource;
use App\Models\Viaje;
use DomainException;


/**
 * Controlador encargado de consultar la disponibilidad
 * real de asientos para un viaje determinado.
 *
 * La disponibilidad se calcula por segmentos,
 * considerando las ocupaciones existentes del viaje.
 */
class DisponibilidadAsientoController extends Controller
{


    /**
     * Constructor del controlador.
     *
     * Inyecta el servicio de dominio encargado
     * de realizar el cálculo de disponibilidad.
     */
    public function __construct(
        private readonly CalculadorDisponibilidadAsientos $calculador
    ) {
    }





    /**
     * Consulta los asientos disponibles
     * para un segmento específico del viaje.
     *
     * Ejemplo:
     *
     * Chiclayo -> Chota
     *
     * Si el punto solicitado no pertenece
     * al recorrido del viaje se devuelve
     * un error de validación.
     */
    public function index(
        ConsultarAsientosDisponiblesRequest $request,
        Viaje $viaje
    ) {


        try {


            $asientos = $this->calculador->calcular(

                $viaje,

                $request->integer(
                    'punto_origen_id'
                ),

                $request->integer(
                    'punto_destino_id'
                )

            );


            return AsientoDisponibleResource::collection(

                $asientos

            );


        } catch (DomainException $e) {


            return response()->json([

                'mensaje' => $e->getMessage()

            ], 422);


        }


    }


}
