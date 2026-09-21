<?php

namespace App\Http\Requests\Api\V1\Autenticacion;

use Illuminate\Foundation\Http\FormRequest;

class RegistrarUsuarioRequest extends FormRequest
{
    /**
     * El registro público está disponible para clientes.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Define las reglas de validación para registrar un cliente.
     */
    public function rules(): array
    {
        return [
            'nombres' => ['required', 'string', 'max:100'],
            'apellidos' => ['required', 'string', 'max:100'],

            'correo' => [
                'required',
                'email',
                'max:150',
                'unique:usuarios,correo',
            ],

            'contrasena' => [
                'required',
                'string',
                'min:8',
                'confirmed',
            ],

            'nombre_dispositivo' => [
                'required',
                'string',
                'max:100',
            ],
        ];
    }
}
