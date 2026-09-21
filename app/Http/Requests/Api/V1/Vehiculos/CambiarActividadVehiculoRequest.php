<?php

namespace App\Http\Requests\Api\V1\Vehiculos;

use Illuminate\Foundation\Http\FormRequest;

class CambiarActividadVehiculoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'activo' => [
                'required',
                'boolean',
            ],
        ];
    }
}
