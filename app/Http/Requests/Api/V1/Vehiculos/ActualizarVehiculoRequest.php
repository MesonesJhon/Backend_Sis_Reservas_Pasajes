<?php

namespace App\Http\Requests\Api\V1\Vehiculos;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ActualizarVehiculoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Valida la actualización de la información general
     * del vehículo.
     *
     * El estado operativo y la activación poseen endpoints
     * independientes porque representan operaciones distintas.
     */
    public function rules(): array
    {
        $vehiculo = $this->route('vehiculo');

        return [
            'tipo_vehiculo_id' => [
                'required',
                'integer',
                Rule::exists('tipos_vehiculo', 'id')
                    ->where('activo', true),
            ],

            'placa' => [
                'required',
                'string',
                'max:15',
                Rule::unique('vehiculos', 'placa')
                    ->ignore($vehiculo),
            ],

            'codigo_interno' => [
                'required',
                'string',
                'max:30',
                Rule::unique('vehiculos', 'codigo_interno')
                    ->ignore($vehiculo),
            ],

            'marca' => [
                'required',
                'string',
                'max:80',
            ],

            'modelo' => [
                'required',
                'string',
                'max:80',
            ],

            'capacidad' => [
                'required',
                'integer',
                'min:1',
            ],

            'numero_pisos' => [
                'required',
                'integer',
                'min:1',
            ],

            'caracteristicas' => [
                'nullable',
                'array',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'placa' => $this->filled('placa')
                ? strtoupper(trim($this->placa))
                : $this->placa,

            'codigo_interno' => $this->filled('codigo_interno')
                ? strtoupper(trim($this->codigo_interno))
                : $this->codigo_interno,
        ]);
    }
}
