<?php

namespace App\Http\Requests\Api\V1\Viajes;

use App\Enums\EstadoViaje;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CambiarEstadoViajeRequest extends FormRequest
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
                'string',
                Rule::enum(EstadoViaje::class),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->estado)) {
            $this->merge([
                'estado' => strtoupper(
                    trim($this->estado)
                ),
            ]);
        }
    }
}
