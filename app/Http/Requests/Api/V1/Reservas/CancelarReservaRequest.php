<?php

namespace App\Http\Requests\Api\V1\Reservas;

use Illuminate\Foundation\Http\FormRequest;

class CancelarReservaRequest extends FormRequest
{
    public function authorize(): bool
    {
        /*
         * El middleware controla:
         *
         * permiso:reservas.cancelar
         *
         * La Action controla propiedad/rol.
         */
        return true;
    }


    public function rules(): array
    {
        return [

            /*
             * El motivo es opcional en RF-07.
             *
             * Más adelante podríamos hacerlo obligatorio
             * para determinados roles o estados.
             */
            'motivo' => [
                'nullable',
                'string',
                'max:255',
            ],
        ];
    }


    public function messages(): array
    {
        return [
            'motivo.max' =>
                'El motivo de cancelación no puede superar los 255 caracteres.',
        ];
    }
}
