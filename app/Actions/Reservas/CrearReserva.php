<?php

namespace App\Actions\Reservas;

use App\Domain\Reservas\CalculadorTotalReserva;
use App\Domain\Reservas\ValidadorCreacionReserva;
use App\Enums\EstadoOcupacionAsiento;
use App\Enums\EstadoReserva;
use App\Models\OcupacionAsiento;
use App\Models\Reserva;
use App\Models\Usuario;
use App\Models\Viaje;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Crea una reserva comercial completa.
 *
 * Toda la operación se realiza dentro de una
 * única transacción para evitar reservas parciales.
 */
class CrearReserva
{
    public function __construct(
        private readonly ValidadorCreacionReserva $validador,
        private readonly CalculadorTotalReserva $calculadorTotal
    ) {
    }


    public function ejecutar(
        Usuario $usuario,
        array $datos
    ): Reserva {

        return DB::transaction(
            function () use (
                $usuario,
                $datos,
            ) {

                /*
                |--------------------------------------------------------------------------
                | 1. Bloquear viaje
                |--------------------------------------------------------------------------
                |
                | Garantiza que su estado no cambie mientras
                | estamos generando la reserva.
                |
                */

                $viaje = Viaje::query()
                    ->lockForUpdate()
                    ->findOrFail(
                        $datos['viaje_id']
                    );


                /*
                |--------------------------------------------------------------------------
                | 2. IDs de ocupaciones solicitadas
                |--------------------------------------------------------------------------
                */

                $ocupacionIds =
                    collect(
                        $datos['pasajeros']
                    )
                        ->pluck(
                            'ocupacion_id'
                        )
                        ->map(
                            fn ($id) =>
                                (int) $id
                        )
                        ->sort()
                        ->values();


                /*
                |--------------------------------------------------------------------------
                | 3. Bloquear ocupaciones
                |--------------------------------------------------------------------------
                |
                | Esto es esencial.
                |
                | Dos solicitudes no podrán convertir
                | simultáneamente la misma ocupación
                | en dos reservas diferentes.
                |
                */

                $ocupaciones =
                    OcupacionAsiento::query()

                        ->whereIn(
                            'id',
                            $ocupacionIds
                        )

                        ->orderBy('id')

                        ->lockForUpdate()

                        ->get();


                /*
                |--------------------------------------------------------------------------
                | 4. Validaciones de dominio
                |--------------------------------------------------------------------------
                */

                $this->validador->validar(
                    $usuario,

                    $viaje,

                    $ocupaciones,

                    $datos['pasajeros'],

                    $datos[
                        'correo_contacto'
                    ] ?? null,

                    $datos[
                        'telefono_contacto'
                    ] ?? null
                );


                /*
                |--------------------------------------------------------------------------
                | 5. Calcular precio
                |--------------------------------------------------------------------------
                */

                $calculo =
                    $this
                        ->calculadorTotal
                        ->calcular(
                            $viaje,
                            $ocupaciones
                        );


                /*
                |--------------------------------------------------------------------------
                | 6. Fecha máxima de la reserva
                |--------------------------------------------------------------------------
                |
                | Si:
                |
                | A1 vence 10:10
                | A2 vence 10:08
                | A3 vence 10:12
                |
                | toda la reserva vence 10:08.
                |
                */

                $ocupacionMasProxima =
                    $ocupaciones
                        ->sortBy(
                            fn ($ocupacion) =>
                                $ocupacion
                                    ->expira_en
                                    ->getTimestamp()
                        )
                        ->first();


                $expiraEn =
                    $ocupacionMasProxima
                        ->expira_en
                        ->copy();


                /*
                |--------------------------------------------------------------------------
                | 7. Crear cabecera
                |--------------------------------------------------------------------------
                */

                $reserva = Reserva::create([

                    /*
                     * ULID tiene 26 caracteres.
                     *
                     * RSV- + ULID = exactamente
                     * 30 caracteres.
                     */
                    'codigo' =>
                        'RSV-'
                        .Str::upper(
                            (string) Str::ulid()
                        ),


                    'viaje_id' =>
                        $viaje->id,


                    /*
                     * CLIENTE:
                     * la reserva le pertenece.
                     *
                     * OPERADOR / ADMINISTRADOR:
                     * podrá representar una venta
                     * presencial sin cuenta de cliente.
                     */
                    'cliente_usuario_id' =>
                        $usuario->tieneRol(
                            'CLIENTE'
                        )
                            ? $usuario->id
                            : null,


                    /*
                     * Siempre registramos quién
                     * ejecutó realmente la operación.
                     */
                    'creado_por_usuario_id' =>
                        $usuario->id,


                    'estado' =>
                        EstadoReserva::PENDIENTE_PAGO,


                    'correo_contacto' =>
                        isset(
                            $datos[
                                'correo_contacto'
                            ]
                        )
                            ? strtolower(
                                trim(
                                    $datos[
                                        'correo_contacto'
                                    ]
                                )
                            )
                            : null,


                    'telefono_contacto' =>
                        isset(
                            $datos[
                                'telefono_contacto'
                            ]
                        )
                            ? trim(
                                $datos[
                                    'telefono_contacto'
                                ]
                            )
                            : null,


                    'total' =>
                        $calculo['total'],


                    'expira_en' =>
                        $expiraEn,
                ]);


                /*
                |--------------------------------------------------------------------------
                | 8. Indexar ocupaciones por ID
                |--------------------------------------------------------------------------
                */

                $ocupacionesPorId =
                    $ocupaciones
                        ->keyBy('id');


                /*
                |--------------------------------------------------------------------------
                | 9. Crear pasajeros
                |--------------------------------------------------------------------------
                */

                foreach (
                    $datos['pasajeros']
                    as $datosPasajero
                ) {

                    $ocupacionId =
                        (int)
                        $datosPasajero[
                            'ocupacion_id'
                        ];


                    $ocupacion =
                        $ocupacionesPorId
                            ->get(
                                $ocupacionId
                            );


                    /*
                     * Snapshot de datos personales
                     * y del precio utilizado.
                     */
                    $reserva
                        ->pasajeros()
                        ->create([

                            'ocupacion_asiento_id' =>
                                $ocupacion->id,


                            'tipo_documento' =>
                                strtoupper(
                                    trim(
                                        $datosPasajero[
                                            'tipo_documento'
                                        ]
                                    )
                                ),


                            'numero_documento' =>
                                trim(
                                    $datosPasajero[
                                        'numero_documento'
                                    ]
                                ),


                            'nombres' =>
                                trim(
                                    $datosPasajero[
                                        'nombres'
                                    ]
                                ),


                            'apellidos' =>
                                trim(
                                    $datosPasajero[
                                        'apellidos'
                                    ]
                                ),


                            'telefono' =>
                                isset(
                                    $datosPasajero[
                                        'telefono'
                                    ]
                                )
                                    ? trim(
                                        $datosPasajero[
                                            'telefono'
                                        ]
                                    )
                                    : null,


                            'correo' =>
                                isset(
                                    $datosPasajero[
                                        'correo'
                                    ]
                                )
                                    ? strtolower(
                                        trim(
                                            $datosPasajero[
                                                'correo'
                                            ]
                                        )
                                    )
                                    : null,


                            /*
                             * Precio congelado.
                             *
                             * Nunca viene desde
                             * el frontend.
                             */
                            'precio' =>
                                $calculo[
                                    'precios'
                                ][
                                    $ocupacion->id
                                ],
                        ]);


                    /*
                    |--------------------------------------------------------------------------
                    | 10. BLOQUEADO -> RESERVADO
                    |--------------------------------------------------------------------------
                    */

                    $ocupacion->update([

                        'reserva_id' =>
                            $reserva->id,

                        'estado' =>
                            EstadoOcupacionAsiento::RESERVADO,

                        /*
                         * Conservamos la fecha límite
                         * común de la reserva.
                         *
                         * Crear una reserva NO entrega
                         * tiempo adicional.
                         */
                        'expira_en' =>
                            $expiraEn,
                    ]);
                }


                /*
                |--------------------------------------------------------------------------
                | 11. Retorno completo
                |--------------------------------------------------------------------------
                */

                return $reserva
                    ->refresh()
                    ->load([
                        'viaje',

                        'cliente',

                        'creadoPor',

                        'pasajeros.ocupacionAsiento.asientoViaje',

                        'pasajeros.ocupacionAsiento.puntoOrigen',

                        'pasajeros.ocupacionAsiento.puntoDestino',
                    ]);
            }
        );
    }
}
