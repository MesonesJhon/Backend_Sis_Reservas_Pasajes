<?php

namespace App\Http\Requests\Api\V1\Vehiculos;

use App\Enums\TipoAsiento;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConfigurarAsientosRequest extends FormRequest
{
    /**
     * La autorización se controla mediante el middleware
     * de permisos definido en la ruta.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Valida la estructura completa de la distribución
     * de asientos enviada por el cliente.
     *
     * Las reglas que dependen del vehículo, como capacidad
     * y número de pisos, se validan en la Action.
     */
    public function rules(): array
    {
        return [
            'asientos' => [
                'required',
                'array',
                'min:1',
            ],

            'asientos.*.codigo' => [
                'required',
                'string',
                'max:20',
                'distinct',
            ],

            'asientos.*.numero' => [
                'nullable',
                'integer',
                'min:1',
            ],

            'asientos.*.piso' => [
                'required',
                'integer',
                'min:1',
            ],

            'asientos.*.fila' => [
                'required',
                'integer',
                'min:1',
            ],

            'asientos.*.columna' => [
                'required',
                'integer',
                'min:1',
            ],

            'asientos.*.tipo' => [
                'required',
                Rule::enum(TipoAsiento::class),
            ],

            'asientos.*.caracteristicas' => [
                'nullable',
                'array',
            ],

            'asientos.*.activo' => [
                'sometimes',
                'boolean',
            ],
        ];
    }
}
