<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifica que el usuario autenticado tenga autorización
 * para ejecutar una operación específica del sistema.
 *
 * Los permisos se obtienen mediante los roles asignados al usuario.
 */
class VerificarPermiso
{
    /**
     * Valida que el usuario posea el permiso requerido.
     *
     * Retorna 401 cuando no existe un usuario autenticado y
     * 403 cuando el usuario está autenticado, pero no cuenta
     * con autorización para realizar la operación.
     */
    public function handle(
        Request $request,
        Closure $next,
        string $permiso
    ): Response {
        $usuario = $request->user();

        if (! $usuario) {
            return new JsonResponse([
                'mensaje' => 'Usuario no autenticado.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        if (! $usuario->tienePermiso($permiso)) {
            return new JsonResponse([
                'mensaje' => 'No tiene permiso para realizar esta operación.',
                'permiso_requerido' => $permiso,
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
