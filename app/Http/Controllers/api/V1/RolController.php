<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\RolResource;
use App\Models\Rol;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Atiende las operaciones HTTP relacionadas con los roles.
 *
 * La autorización de acceso se realiza mediante middleware,
 * evitando incluir reglas de permisos dentro del controlador.
 */
class RolController extends Controller
{
    /**
     * Lista los roles disponibles junto con sus permisos.
     */
    public function index(): AnonymousResourceCollection
    {
        $roles = Rol::query()
            ->with('permisos')
            ->orderBy('nombre')
            ->get();

        return RolResource::collection($roles);
    }
}
