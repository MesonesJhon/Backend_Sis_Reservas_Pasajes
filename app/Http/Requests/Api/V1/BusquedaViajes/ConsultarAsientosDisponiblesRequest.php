<?php

namespace App\Http\Requests\Api\V1\BusquedaViajes;

use Illuminate\Foundation\Http\FormRequest;


/**
 * Valida la consulta de disponibilidad
 * de asientos dentro de un viaje.
 */
class ConsultarAsientosDisponiblesRequest extends FormRequest
{

    public function authorize(): bool
    {
        return true;
    }



    public function rules(): array
    {

        return [

            /*
             * Punto donde inicia
             * el pasajero.
             */
            'punto_origen_id' => [

                'required',

                'integer',

            ],



            /*
             * Punto donde termina
             * el pasajero.
             */
            'punto_destino_id' => [

                'required',

                'integer',

                'different:punto_origen_id',

            ],

        ];

    }


}
