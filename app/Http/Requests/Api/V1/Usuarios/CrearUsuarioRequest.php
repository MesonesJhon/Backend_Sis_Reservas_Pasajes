<?php

namespace App\Http\Requests\Api\V1\Usuarios;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Valida los datos necesarios para crear un usuario interno.
 *
 * La creación de usuarios internos está destinada inicialmente
 * a operadores y conductores. Los clientes utilizan el registro
 * público y los administradores no se crean mediante este flujo.
 */
class CrearUsuarioRequest extends FormRequest
{
    /**
     * La autorización se controla mediante el middleware
     * "permiso:usuarios.crear".
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Define las reglas de validación para crear
     * un usuario interno.
     */
    public function rules(): array
    {
        return [
            'nombres' => [
                'required',
                'string',
                'max:100',
            ],

            'apellidos' => [
                'required',
                'string',
                'max:100',
            ],

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

            'rol' => [
                'required',
                'string',
                Rule::in([
                    'OPERADOR',
                    'CONDUCTOR',
                ]),
            ],
        ];
    }
}
