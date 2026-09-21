<?php

namespace App\Actions\Autenticacion;

use App\Models\Usuario;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class IniciarSesion
{
    /**
     * Valida las credenciales y genera un token de acceso
     * asociado al dispositivo del usuario.
     */
    public function ejecutar(
        string $correo,
        string $contrasena,
        string $nombreDispositivo
    ): array {
        $usuario = Usuario::where('correo', $correo)->first();

        if (
            ! $usuario ||
            ! Hash::check($contrasena, $usuario->contrasena)
        ) {
            throw ValidationException::withMessages([
                'correo' => [
                    'Las credenciales proporcionadas son incorrectas.',
                ],
            ]);
        }

        if (! $usuario->activo) {
            throw ValidationException::withMessages([
                'correo' => [
                    'El usuario se encuentra desactivado.',
                ],
            ]);
        }

        $token = $usuario
            ->createToken($nombreDispositivo)
            ->plainTextToken;

        return [
            'usuario' => $usuario,
            'token' => $token,
        ];
    }
}
