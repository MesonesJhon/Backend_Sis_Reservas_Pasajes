<?php

namespace App\Http\Requests\Api\V1\Rutas;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ActualizarRutaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $ruta = $this->route('ruta');

        return [
            'codigo' => [
                'required',
                'string',
                'max:30',
                Rule::unique('rutas', 'codigo')
                    ->ignore($ruta),
            ],
            'nombre' => [
                'required',
                'string',
                'max:150',
            ],
            'duracion_estimada_minutos' => [
                'required',
                'integer',
                'min:1',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'codigo' => is_string($this->codigo)
                ? strtoupper(trim($this->codigo))
                : $this->codigo,

            'nombre' => is_string($this->nombre)
                ? trim($this->nombre)
                : $this->nombre,
        ]);
    }
}
