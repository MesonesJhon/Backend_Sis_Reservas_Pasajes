<?php

namespace App\Http\Requests\Api\V1\Rutas;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConfigurarRecorridoRutaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'puntos' => [
                'required',
                'array',
                'min:2',
            ],

            'puntos.*.punto_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('puntos', 'id')
                    ->where('activo', true),
            ],

            'puntos.*.orden' => [
                'required',
                'integer',
                'min:1',
                'distinct',
            ],

            'puntos.*.permite_embarque' => [
                'required',
                'boolean',
            ],

            'puntos.*.permite_desembarque' => [
                'required',
                'boolean',
            ],

            'puntos.*.minutos_desde_origen' => [
                'required',
                'integer',
                'min:0',
            ],
        ];
    }
}
