<?php

namespace App\Http\Requests\Api\V1\Vehiculos;

use App\Enums\EstadoVehiculo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CambiarEstadoVehiculoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'estado' => [
                'required',
                Rule::enum(EstadoVehiculo::class),
            ],
        ];
    }
}
