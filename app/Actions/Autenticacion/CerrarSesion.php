<?php

namespace App\Actions\Autenticacion;

use App\Models\Usuario;

class CerrarSesion
{
    /**
     * Revoca únicamente el token utilizado en la petición actual.
     */
    public function ejecutar(Usuario $usuario): void
    {
        $usuario->currentAccessToken()?->delete();
    }
}
