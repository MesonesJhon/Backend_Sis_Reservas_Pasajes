<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OcupacionAsientoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'viaje_id' =>
                $this->viaje_id,

            'asiento_viaje_id' =>
                $this->asiento_viaje_id,

            'punto_origen_id' =>
                $this->punto_origen_id,

            'punto_destino_id' =>
                $this->punto_destino_id,

            'estado' =>
                $this->estado->value,

            'expira_en' =>
                $this->expira_en
                    ?->format('Y-m-d H:i:s'),

            'created_at' =>
                $this->created_at
                    ?->format('Y-m-d H:i:s'),

            'updated_at' =>
                $this->updated_at
                    ?->format('Y-m-d H:i:s'),
        ];
    }
}
