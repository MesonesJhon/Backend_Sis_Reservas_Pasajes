<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Viajes\AsignarPersonalViaje;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Viajes\AsignarPersonalViajeRequest;
use App\Http\Resources\Api\V1\PersonalViajeResource;
use App\Models\Viaje;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PersonalViajeController extends Controller
{
    public function index(
        Viaje $viaje
    ): AnonymousResourceCollection {
        $personal = $viaje->personal()
            ->with('usuario')
            ->get();

        return PersonalViajeResource::collection($personal);
    }

    public function update(
        AsignarPersonalViajeRequest $request,
        Viaje $viaje,
        AsignarPersonalViaje $asignarPersonal
    ): AnonymousResourceCollection {
        $viaje = $asignarPersonal->ejecutar(
            $viaje,
            $request->validated('personal')
        );

        return PersonalViajeResource::collection(
            $viaje->personal
        );
    }
}
