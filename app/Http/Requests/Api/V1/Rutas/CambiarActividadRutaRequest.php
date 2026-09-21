<?php

namespace App\Http\Requests\Api\V1\Rutas;

use Illuminate\Foundation\Http\FormRequest;

class CambiarActividadRutaRequest extends FormRequest
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
