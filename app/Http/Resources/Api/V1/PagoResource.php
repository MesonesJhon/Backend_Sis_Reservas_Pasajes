<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PagoResource extends JsonResource
{
    public function toArray(
        Request $request
    ): array {

        return [

            'id' =>
                $this->id,

            'reserva_id' =>
                $this->reserva_id,

            'proveedor' =>
                $this->proveedor->value,

            'canal' =>
                $this->canal->value,

            'monto' =>
                $this->monto,

            'moneda' =>
                $this->moneda,

            'estado' =>
                $this->estado->value,

            'estado_proveedor' =>
                $this->estado_proveedor,

            'detalle_estado' =>
                $this->detalle_estado,

            /*
             * Solamente será útil durante
             * el inicio del Checkout.
             */
            'checkout_url' =>
                $this->checkout_url,

            'requiere_revision' =>
                (bool)
                $this->requiere_revision,

            'aprobado_en' =>
                $this->aprobado_en
                    ?->format(
                        'Y-m-d H:i:s'
                    ),

            'ultima_verificacion_en' =>
                $this->ultima_verificacion_en
                    ?->format(
                        'Y-m-d H:i:s'
                    ),

            /*
             * Solamente se incluye cuando
             * el Controller cargó la reserva.
             */
            'reserva' =>
                $this->whenLoaded(
                    'reserva',

                    fn () => [

                        'id' =>
                            $this
                                ->reserva
                                ->id,

                        'codigo' =>
                            $this
                                ->reserva
                                ->codigo,

                        'estado' =>
                            $this
                                ->reserva
                                ->estado
                                ->value,

                        'confirmada_en' =>
                            $this
                                ->reserva
                                ->confirmada_en
                                ?->format(
                                    'Y-m-d H:i:s'
                                ),
                    ]
                ),

            'created_at' =>
                $this->created_at
                    ?->format(
                        'Y-m-d H:i:s'
                    ),
        ];
    }
}
