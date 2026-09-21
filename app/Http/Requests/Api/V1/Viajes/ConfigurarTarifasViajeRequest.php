<?php

namespace App\Http\Requests\Api\V1\Viajes;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConfigurarTarifasViajeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tarifas' => [
                'required',
                'array',
                'min:1',
            ],

            'tarifas.*.punto_origen_id' => [
                'required',
                'integer',
                Rule::exists('puntos', 'id'),
            ],

            'tarifas.*.punto_destino_id' => [
                'required',
                'integer',
                Rule::exists('puntos', 'id'),
            ],

            'tarifas.*.precio' => [
                'required',
                'numeric',
                'gt:0',
                'decimal:0,2',
            ],
        ];
    }
}
