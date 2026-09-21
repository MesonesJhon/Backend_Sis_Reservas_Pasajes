<?php

namespace App\Http\Requests\Api\V1\Viajes;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ActualizarViajeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $viaje = $this->route('viaje');

        return [
            'codigo' => [
                'required',
                'string',
                'max:30',
                Rule::unique('viajes', 'codigo')->ignore($viaje),
            ],

            'ruta_id' => [
                'required',
                'integer',
                Rule::exists('rutas', 'id'),
            ],

            'vehiculo_id' => [
                'required',
                'integer',
                Rule::exists('vehiculos', 'id'),
            ],

            'salida_programada' => [
                'required',
                'date',
            ],

            'observaciones' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }

    /**
     * Normaliza los campos textuales antes de validarlos.
     */
    protected function prepareForValidation(): void
    {
        $datos = [];

        if (is_string($this->codigo)) {
            $datos['codigo'] = strtoupper(
                trim($this->codigo)
            );
        }

        if (is_string($this->observaciones)) {
            $datos['observaciones'] = trim(
                $this->observaciones
            );
        }

        $this->merge($datos);
    }
}
