<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifica que el usuario autenticado tenga un rol determinado.
 *
 * Este middleware debe reservarse para operaciones cuya regla
 * de negocio dependa explícitamente de un rol. Para autorización
 * habitual se debe preferir VerificarPermiso.
 */
class VerificarRol
{
    /**
     * Comprueba que el usuario autenticado posea el rol requerido.
     */
    public function handle(
        Request $request,
        Closure $next,
        string $rol
    ): Response {
        $usuario = $request->user();

        if (! $usuario) {
            return new JsonResponse([
                'mensaje' => 'Usuario no autenticado.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        if (! $usuario->tieneRol($rol)) {
            return new JsonResponse([
                'mensaje' => 'No tiene el rol requerido para realizar esta operación.',
                'rol_requerido' => $rol,
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
