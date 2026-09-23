<?php

namespace App\Domain\Reservas;

use App\Exceptions\OperacionReservaInvalidaException;
use App\Models\TarifaViaje;
use App\Models\Viaje;
use Illuminate\Support\Collection;

/**
 * Calcula los importes comerciales de una reserva.
 *
 * El frontend nunca proporciona el precio.
 *
 * Los precios siempre se obtienen de tarifas_viaje.
 */
class CalculadorTotalReserva
{
    /**
     * Calcula:
     *
     * - precio de cada ocupación;
     * - total completo de la reserva.
     *
     * @return array{
     *     total: string,
     *     precios: array<int, string>
     * }
     */
    public function calcular(
        Viaje $viaje,
        Collection $ocupaciones
    ): array {

        $totalCentavos = 0;

        $preciosPorOcupacion = [];

        /*
         * Cache local de tarifas.
         *
         * Si A1 y A2 utilizan el mismo tramo,
         * no necesitamos consultar dos veces
         * la misma tarifa.
         */
        $tarifasPorSegmento = [];


        /*
         * Orden estable.
         *
         * Además de facilitar la lectura,
         * mantener siempre el mismo orden reduce
         * posibilidades de deadlocks cuando exista
         * concurrencia real en PostgreSQL.
         */
        $ocupacionesOrdenadas =
            $ocupaciones->sortBy('id');


        foreach (
            $ocupacionesOrdenadas
            as $ocupacion
        ) {

            $claveSegmento =
                $ocupacion->punto_origen_id
                .'-'
                .$ocupacion->punto_destino_id;


            /*
             * Obtenemos la tarifa únicamente
             * la primera vez que aparece el segmento.
             */
            if (
                ! isset(
                    $tarifasPorSegmento[
                        $claveSegmento
                    ]
                )
            ) {

                /*
                 * lockForUpdate evita que el precio sea
                 * modificado durante la creación de
                 * esta reserva.
                 */
                $tarifa = TarifaViaje::query()

                    ->where(
                        'viaje_id',
                        $viaje->id
                    )

                    ->where(
                        'punto_origen_id',
                        $ocupacion->punto_origen_id
                    )

                    ->where(
                        'punto_destino_id',
                        $ocupacion->punto_destino_id
                    )

                    ->where(
                        'activo',
                        true
                    )

                    ->lockForUpdate()

                    ->first();


                if (! $tarifa) {
                    throw new OperacionReservaInvalidaException(
                        'No existe una tarifa activa para uno de los segmentos seleccionados.'
                    );
                }


                $tarifasPorSegmento[
                    $claveSegmento
                ] = (string) $tarifa->precio;
            }


            $precio =
                $tarifasPorSegmento[
                    $claveSegmento
                ];


            $preciosPorOcupacion[
                $ocupacion->id
            ] = $precio;


            /*
             * Para sumar dinero no utilizamos float.
             *
             * Convertimos primero:
             *
             * S/ 50.00 -> 5000 centavos
             */
            $totalCentavos +=
                $this->convertirACentavos(
                    $precio
                );
        }


        return [
            'total' =>
                $this->convertirDesdeCentavos(
                    $totalCentavos
                ),

            'precios' =>
                $preciosPorOcupacion,
        ];
    }


    /**
     * Convierte una cantidad decimal almacenada
     * como string en centavos enteros.
     *
     * Ejemplo:
     *
     * "50.25" -> 5025
     */
    private function convertirACentavos(
        string $monto
    ): int {

        $partes = explode(
            '.',
            $monto,
            2
        );


        $entero =
            (int) $partes[0];


        $decimales =
            $partes[1]
            ?? '0';


        $decimales = str_pad(
            $decimales,
            2,
            '0'
        );


        $decimales = substr(
            $decimales,
            0,
            2
        );


        return (
            $entero * 100
        ) + (int) $decimales;
    }


    /**
     * Convierte nuevamente los centavos
     * en un decimal de dos posiciones.
     *
     * Ejemplo:
     *
     * 5025 -> "50.25"
     */
    private function convertirDesdeCentavos(
        int $centavos
    ): string {

        $entero =
            intdiv(
                $centavos,
                100
            );


        $decimales =
            $centavos % 100;


        return sprintf(
            '%d.%02d',
            $entero,
            $decimales
        );
    }
}
