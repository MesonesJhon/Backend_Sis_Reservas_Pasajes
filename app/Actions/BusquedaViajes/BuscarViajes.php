<?php

namespace App\Actions\BusquedaViajes;

use App\Enums\EstadoViaje;
use App\Models\Viaje;
use Carbon\Carbon;

use App\Domain\Viajes\ValidadorSegmentoViaje;
use App\Domain\Viajes\CalculadorHorarioSegmento;
use App\Domain\Viajes\ObtenerTarifaSegmento;


/**
 * Busca viajes comercializables según:
 *
 * - origen
 * - destino
 * - fecha
 *
 * Esta clase contiene la lógica principal
 * del RF-05.
 */
class BuscarViajes
{

    public function __construct(
        private readonly ValidadorSegmentoViaje $validador,

        private readonly CalculadorHorarioSegmento $horarios,

        private readonly ObtenerTarifaSegmento $tarifas
    ) {
    }



    /**
     * Ejecuta la búsqueda comercial.
     *
     * Retorna únicamente viajes que:
     *
     * - están programados
     * - tienen el segmento solicitado
     * - tienen tarifa activa
     *
     */
    public function ejecutar(array $datos): array
    {

        /*
         * Normalizamos la fecha recibida.
         */
        $fecha = Carbon::parse(
            $datos['fecha']
        )->toDateString();



        /*
         * Primero obtenemos candidatos.
         *
         * Todavía no validamos segmento.
         * Eso lo hará ValidadorSegmentoViaje.
         */
        $viajes = Viaje::query()

            ->where(
                'estado',
                EstadoViaje::PROGRAMADO
            )


            ->whereDate(
                'salida_programada',
                $fecha
            )


            ->with([
                'vehiculo.tipoVehiculo',

                'puntosViaje',

                'tarifas',

                'asientosViaje',
            ])

            ->get();



        $resultados = [];



        /*
         * Recorremos los viajes encontrados
         * y aplicamos reglas comerciales.
         */
        foreach ($viajes as $viaje) {


            try {


                /*
                 * Verifica:
                 *
                 * - origen existe
                 * - destino existe
                 * - orden correcto
                 * - embarque permitido
                 * - desembarque permitido
                 */
                $this->validador->validar(

                    $viaje,

                    $datos['punto_origen_id'],

                    $datos['punto_destino_id']

                );



                /*
                 * Calcula horario real del tramo.
                 *
                 * Ejemplo:
                 *
                 * Viaje sale 08:00
                 *
                 * Chiclayo -> 08:00
                 * Lambayeque -> 08:30
                 * Chota -> 14:00
                 */
                $horario =
                    $this->horarios->calcular(

                        $viaje,

                        $datos['punto_origen_id'],

                        $datos['punto_destino_id']

                    );



                /*
                 * Obtiene la tarifa exacta
                 * del segmento.
                 */
                $tarifa =
                    $this->tarifas->obtener(

                        $viaje,

                        $datos['punto_origen_id'],

                        $datos['punto_destino_id']

                    );



                /*
                 * Construimos respuesta comercial.
                 */
                $resultados[] = [

                    'viaje_id'
                        => $viaje->id,


                    'codigo'
                        => $viaje->codigo,


                    'horario'
                        => $horario,


                    'precio'
                        => $tarifa->precio,


                    'vehiculo'
                        => [

                            'tipo'
                                => $viaje
                                    ->vehiculo
                                    ->tipoVehiculo
                                    ->nombre
                                    ?? null,

                            'marca'
                                => $viaje
                                    ->vehiculo
                                    ->marca
                                    ?? null,


                            'modelo'
                                => $viaje
                                    ->vehiculo
                                    ->modelo
                                    ?? null,

                        ],


                ];



            } catch (\Exception $e) {


                /*
                 * Si el viaje no cumple:
                 *
                 * - segmento inválido
                 * - sin tarifa
                 *
                 * simplemente no aparece
                 * en resultados.
                 */
                continue;

            }

        }



        return $resultados;

    }

}
