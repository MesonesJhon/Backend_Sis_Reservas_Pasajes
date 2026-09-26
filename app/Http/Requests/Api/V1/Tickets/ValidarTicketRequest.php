<?php

namespace App\Http\Requests\Api\V1\Tickets;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidarTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }


    public function rules(): array
    {
        return [

            /*
            |--------------------------------------------------------------------------
            | Token leído del QR
            |--------------------------------------------------------------------------
            */

            'token' => [
                'required',
                'string',
                'max:512',
            ],


            /*
            |--------------------------------------------------------------------------
            | Viaje que actualmente está procesando el scanner
            |--------------------------------------------------------------------------
            |
            | Esto permite impedir que se utilice un ticket válido
            | perteneciente a otro viaje.
            |
            */

            'viaje_id' => [
                'required',
                'integer',

                Rule::exists(
                    'viajes',
                    'id'
                ),
            ],
        ];
    }
}
