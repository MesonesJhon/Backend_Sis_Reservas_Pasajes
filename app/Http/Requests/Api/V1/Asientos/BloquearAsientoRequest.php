<?php

namespace App\Http\Requests\Api\V1\Asientos;

use Illuminate\Foundation\Http\FormRequest;

class BloquearAsientoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'viaje_id' => [
                'required',
                'integer',
                'exists:viajes,id',
            ],

            'asiento_viaje_id' => [
                'required',
                'integer',
                'exists:asientos_viaje,id',
            ],

            'punto_origen_id' => [
                'required',
                'integer',
                'exists:puntos,id',
            ],

            'punto_destino_id' => [
                'required',
                'integer',
                'exists:puntos,id',
            ],
        ];
    }
}
