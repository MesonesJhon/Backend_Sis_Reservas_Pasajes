<?php

namespace App\Http\Requests\Api\V1\Usuarios;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Valida el rol que será asignado a un usuario interno.
 *
 * Este flujo administrativo permite cambiar únicamente
 * entre los roles OPERADOR y CONDUCTOR.
 */
class AsignarRolRequest extends FormRequest
{
    /**
     * La autorización se controla mediante
     * el permiso "roles.asignar".
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Define los roles que pueden asignarse mediante
     * esta operación administrativa.
     */
    public function rules(): array
    {
        return [
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
