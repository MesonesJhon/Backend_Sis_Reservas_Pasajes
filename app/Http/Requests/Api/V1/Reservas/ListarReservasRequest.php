<?php

namespace App\Http\Requests\Api\V1\Reservas;

use App\Enums\EstadoReserva;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListarReservasRequest extends FormRequest
{
    public function authorize(): bool
    {
        /*
         * La autorización general la controla:
         *
         * permiso:reservas.ver
         */
        return true;
    }


    public function rules(): array
    {
        return [

            /*
            |--------------------------------------------------------------------------
            | Estado
            |--------------------------------------------------------------------------
            */

            'estado' => [
                'nullable',
                Rule::enum(
                    EstadoReserva::class
                ),
            ],


            /*
            |--------------------------------------------------------------------------
            | Viaje
            |--------------------------------------------------------------------------
            */

            'viaje_id' => [
                'nullable',
                'integer',
                'exists:viajes,id',
            ],


            /*
            |--------------------------------------------------------------------------
            | Código comercial
            |--------------------------------------------------------------------------
            */

            'codigo' => [
                'nullable',
                'string',
                'max:30',
            ],


            /*
            |--------------------------------------------------------------------------
            | Paginación
            |--------------------------------------------------------------------------
            */

            'per_page' => [
                'nullable',
                'integer',
                'min:1',
                'max:100',
            ],
        ];
    }
}
