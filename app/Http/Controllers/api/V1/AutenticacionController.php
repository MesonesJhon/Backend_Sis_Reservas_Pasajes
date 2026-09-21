<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Autenticacion\CerrarSesion;
use App\Actions\Autenticacion\IniciarSesion;
use App\Actions\Autenticacion\RegistrarCliente;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Autenticacion\IniciarSesionRequest;
use App\Http\Requests\Api\V1\Autenticacion\RegistrarUsuarioRequest;
use App\Http\Resources\Api\V1\UsuarioResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AutenticacionController extends Controller
{
    /**
     * Registra una nueva cuenta de cliente.
     */
    public function registrar(
        RegistrarUsuarioRequest $request,
        RegistrarCliente $registrarCliente
    ): JsonResponse {
        $datos = $request->validated();

        $usuario = $registrarCliente->ejecutar($datos);

        $token = $usuario
            ->createToken($datos['nombre_dispositivo'])
            ->plainTextToken;

        $usuario->load('roles.permisos');

        return response()->json([
            'mensaje' => 'Usuario registrado correctamente.',
            'usuario' => new UsuarioResource($usuario),
            'token' => $token,
        ], 201);
    }

    /**
     * Autentica al usuario y devuelve su token de acceso.
     */
    public function iniciarSesion(
        IniciarSesionRequest $request,
        IniciarSesion $iniciarSesion
    ): JsonResponse {
        $datos = $request->validated();

        $resultado = $iniciarSesion->ejecutar(
            $datos['correo'],
            $datos['contrasena'],
            $datos['nombre_dispositivo']
        );

        $resultado['usuario']->load('roles.permisos');

        return response()->json([
            'mensaje' => 'Sesión iniciada correctamente.',
            'usuario' => new UsuarioResource(
                $resultado['usuario']
            ),
            'token' => $resultado['token'],
        ]);
    }

    /**
     * Devuelve la información del usuario autenticado.
     */
    public function usuario(Request $request): UsuarioResource
    {
        $usuario = $request->user();

        $usuario->load('roles.permisos');

        return new UsuarioResource($usuario);
    }

    /**
     * Revoca el token correspondiente a la sesión actual.
     */
    public function cerrarSesion(
        Request $request,
        CerrarSesion $cerrarSesion
    ): JsonResponse {
        $cerrarSesion->ejecutar($request->user());

        return response()->json([
            'mensaje' => 'Sesión cerrada correctamente.',
        ]);
    }
}
