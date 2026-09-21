<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Usuarios\ActualizarUsuario;
use App\Actions\Usuarios\AsignarRolUsuario;
use App\Actions\Usuarios\CambiarEstadoUsuario;
use App\Actions\Usuarios\CrearUsuario;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Usuarios\ActualizarUsuarioRequest;
use App\Http\Requests\Api\V1\Usuarios\AsignarRolRequest;
use App\Http\Requests\Api\V1\Usuarios\CambiarEstadoUsuarioRequest;
use App\Http\Requests\Api\V1\Usuarios\CrearUsuarioRequest;
use App\Http\Resources\Api\V1\UsuarioResource;
use App\Models\Usuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * Atiende las operaciones administrativas relacionadas
 * con los usuarios registrados en el sistema.
 *
 * La validación se delega a Form Requests y la lógica
 * de negocio a las Actions correspondientes.
 */
class UsuarioController extends Controller
{
    /**
     * Lista los usuarios registrados de forma paginada.
     */
    public function index(): AnonymousResourceCollection
    {
        $usuarios = Usuario::query()
            ->with('roles.permisos')
            ->orderByDesc('id')
            ->paginate(20);

        return UsuarioResource::collection($usuarios);
    }

    /**
     * Registra un nuevo usuario interno.
     */
    public function store(
        CrearUsuarioRequest $request,
        CrearUsuario $crearUsuario
    ): JsonResponse {
        $usuario = $crearUsuario->ejecutar(
            $request->validated()
        );

        return response()->json([
            'mensaje' => 'Usuario creado correctamente.',
            'usuario' => new UsuarioResource($usuario),
        ], Response::HTTP_CREATED);
    }

    /**
     * Muestra la información de un usuario específico.
     */
    public function show(Usuario $usuario): UsuarioResource
    {
        $usuario->load('roles.permisos');

        return new UsuarioResource($usuario);
    }

    /**
     * Actualiza los datos generales de un usuario.
     */
    public function update(
        ActualizarUsuarioRequest $request,
        Usuario $usuario,
        ActualizarUsuario $actualizarUsuario
    ): JsonResponse {
        $usuarioActualizado = $actualizarUsuario->ejecutar(
            $usuario,
            $request->validated()
        );

        return response()->json([
            'mensaje' => 'Usuario actualizado correctamente.',
            'usuario' => new UsuarioResource($usuarioActualizado),
        ]);
    }

    /**
     * Activa o desactiva un usuario respetando
     * las reglas de protección de cuentas.
     */
    public function cambiarEstado(
        CambiarEstadoUsuarioRequest $request,
        Usuario $usuario,
        CambiarEstadoUsuario $cambiarEstadoUsuario
    ): JsonResponse {
        $usuarioActualizado = $cambiarEstadoUsuario->ejecutar(
            $request->user(),
            $usuario,
            $request->boolean('activo')
        );

        return response()->json([
            'mensaje' => $usuarioActualizado->activo
                ? 'Usuario activado correctamente.'
                : 'Usuario desactivado correctamente.',

            'usuario' => new UsuarioResource($usuarioActualizado),
        ]);
    }

    /**
     * Cambia el rol operativo del usuario respetando
     * las reglas de protección de cuentas.
     */
    public function asignarRol(
        AsignarRolRequest $request,
        Usuario $usuario,
        AsignarRolUsuario $asignarRolUsuario
    ): JsonResponse {
        $usuarioActualizado = $asignarRolUsuario->ejecutar(
            $request->user(),
            $usuario,
            $request->validated('rol')
        );

        return response()->json([
            'mensaje' => 'Rol actualizado correctamente.',
            'usuario' => new UsuarioResource($usuarioActualizado),
        ]);
    }
}
