<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PuntoViajeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'orden' => $this->orden,

            'punto' => new PuntoResource(
                $this->whenLoaded('punto')
            ),

            'permite_embarque' =>
                $this->permite_embarque,

            'permite_desembarque' =>
                $this->permite_desembarque,

            'minutos_desde_origen' =>
                $this->minutos_desde_origen,
        ];
    }
}
