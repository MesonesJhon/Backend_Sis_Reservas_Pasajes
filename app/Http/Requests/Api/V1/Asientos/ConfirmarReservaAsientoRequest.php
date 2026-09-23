<?php

namespace App\Http\Requests\Api\V1\Asientos;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmarReservaAsientoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ocupacion_id' => [
                'required',
                'integer',
                'exists:ocupaciones_asientos,id',
            ],
        ];
    }
}
