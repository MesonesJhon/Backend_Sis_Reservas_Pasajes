<?php

namespace App\Http\Requests\Api\V1\Autenticacion;

use Illuminate\Foundation\Http\FormRequest;

class IniciarSesionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Valida las credenciales necesarias para iniciar sesión.
     */
    public function rules(): array
    {
        return [
            'correo' => [
                'required',
                'email',
            ],

            'contrasena' => [
                'required',
                'string',
            ],

            'nombre_dispositivo' => [
                'required',
                'string',
                'max:100',
            ],
        ];
    }
}
