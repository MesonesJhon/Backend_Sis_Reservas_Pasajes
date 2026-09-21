<?php

namespace App\Http\Requests\Api\V1\Vehiculos;

use App\Enums\EstadoVehiculo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CrearVehiculoRequest extends FormRequest
{
    /**
     * La autorización se controla mediante el middleware
     * de permisos definido en las rutas.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Valida los datos necesarios para registrar
     * una nueva unidad de transporte.
     */
    public function rules(): array
    {
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
                'unique:vehiculos,placa',
            ],

            'codigo_interno' => [
                'required',
                'string',
                'max:30',
                'unique:vehiculos,codigo_interno',
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

            'estado' => [
                'sometimes',
                Rule::enum(EstadoVehiculo::class),
            ],
        ];
    }

    /**
     * Normaliza identificadores antes de ejecutar
     * las reglas de validación.
     */
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
