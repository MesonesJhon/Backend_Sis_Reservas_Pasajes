<?php

namespace App\Http\Requests\Api\V1\Usuarios;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida el nuevo estado de acceso de un usuario.
 */
class CambiarEstadoUsuarioRequest extends FormRequest
{
    /**
     * La autorización se controla mediante
     * el permiso "usuarios.desactivar".
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * El estado debe enviarse explícitamente
     * como un valor booleano.
     */
    public function rules(): array
    {
        return [
            'activo' => [
                'required',
                'boolean',
            ],
        ];
    }
}
