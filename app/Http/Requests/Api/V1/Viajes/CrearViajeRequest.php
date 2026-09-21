<?php

namespace App\Http\Requests\Api\V1\Viajes;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CrearViajeRequest extends FormRequest
{
    public function authorize(): bool
    {
        /*
         * La autorización real se controla mediante
         * auth:sanctum + permiso:viajes.crear.
         */
        return true;
    }

    public function rules(): array
    {
        return [
            'codigo' => [
                'required',
                'string',
                'max:30',
                'unique:viajes,codigo',
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
     * Normaliza datos antes de aplicar las reglas.
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
