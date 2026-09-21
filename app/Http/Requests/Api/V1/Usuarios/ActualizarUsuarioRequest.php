<?php

namespace App\Http\Requests\Api\V1\Usuarios;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Valida los datos modificables de un usuario existente.
 *
 * El rol, contraseña y estado no se modifican desde este flujo,
 * ya que poseen casos de uso independientes.
 */
class ActualizarUsuarioRequest extends FormRequest
{
    /**
     * La autorización se controla mediante
     * el permiso "usuarios.editar".
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Define las reglas para actualizar los datos
     * generales del usuario.
     */
    public function rules(): array
    {
        $usuario = $this->route('usuario');

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

                Rule::unique('usuarios', 'correo')
                    ->ignore($usuario->id),
            ],
        ];
    }
}
