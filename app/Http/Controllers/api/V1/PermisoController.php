<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\PermisoResource;
use App\Models\Permiso;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Atiende las operaciones HTTP relacionadas con permisos.
 */
class PermisoController extends Controller
{
    /**
     * Lista los permisos disponibles en el sistema.
     */
    public function index(): AnonymousResourceCollection
    {
        $permisos = Permiso::query()
            ->orderBy('nombre')
            ->get();

        return PermisoResource::collection($permisos);
    }
}
