<?php

namespace App\Http\Requests\Api\V1\Viajes;

use App\Enums\FuncionPersonalViaje;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AsignarPersonalViajeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'personal' => [
                'required',
                'array',
                'min:1',
            ],

            'personal.*.usuario_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('usuarios', 'id'),
            ],

            'personal.*.funcion' => [
                'required',
                'string',
                Rule::enum(FuncionPersonalViaje::class),
            ],
        ];
    }
}
