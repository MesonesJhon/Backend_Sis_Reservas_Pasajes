<?php

namespace App\Actions\Postventa;

use App\Actions\Asientos\BloquearAsiento;
use App\Actions\Reservas\CrearReserva;
use App\Enums\EstadoReprogramacion;
use App\Exceptions\OperacionAsientoInvalidaException;
use App\Exceptions\OperacionReprogramacionInvalidaException;
use App\Models\ReprogramacionReserva;
use App\Models\Reserva;
use App\Models\Usuario;
use App\Models\Viaje;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Prepara una reprogramación creando:
 *
 * - nuevos bloqueos;
 * - una nueva reserva destino;
 * - la relación entre reserva origen y destino.
 *
 * NO modifica todavía la reserva original.
 *
 * La consolidación definitiva se realizará
 * posteriormente en RF-10.2-D.
 */
class PrepararReprogramacionReserva
{
    public function __construct(
        private readonly CotizarReprogramacionReserva $cotizarReprogramacion,

        private readonly BloquearAsiento $bloquearAsiento,

        private readonly CrearReserva $crearReserva
    ) {
    }


    public function ejecutar(
        Usuario $usuario,
        Reserva $reservaOrigen,
        Viaje $viajeDestino,
        array $asignaciones,
        ?string $motivo = null
    ): ReprogramacionReserva {

        return DB::transaction(
            function () use (
                $usuario,
                $reservaOrigen,
                $viajeDestino,
                $asignaciones,
                $motivo
            ): ReprogramacionReserva {

                /*
                |--------------------------------------------------------------------------
                | 1. Bloquear reserva origen
                |--------------------------------------------------------------------------
                |
                | Este lock también serializa dos intentos
                | simultáneos de reprogramar la misma reserva.
                |
                */

                $reservaOrigen =
                    Reserva::query()

                        ->lockForUpdate()

                        ->findOrFail(
                            $reservaOrigen->id
                        );


                /*
                |--------------------------------------------------------------------------
                | 2. Bloquear ambos viajes
                |--------------------------------------------------------------------------
                |
                | Siempre en el mismo orden para reducir
                | riesgo de deadlocks.
                |
                */

                $viajeIds =
                    collect([
                        (int) $reservaOrigen->viaje_id,
                        (int) $viajeDestino->id,
                    ])
                        ->unique()
                        ->sort()
                        ->values();


                $viajes =
                    Viaje::query()

                        ->whereIn(
                            'id',
                            $viajeIds
                        )

                        ->orderBy(
                            'id'
                        )

                        ->lockForUpdate()

                        ->get()

                        ->keyBy(
                            'id'
                        );


                $viajeDestino =
                    $viajes->get(
                        $viajeDestino->id
                    );


                if ($viajeDestino === null) {
                    throw new OperacionReprogramacionInvalidaException(
                        'El viaje destino no existe.'
                    );
                }


                /*
                |--------------------------------------------------------------------------
                | 3. Bloquear historial de reprogramaciones
                |--------------------------------------------------------------------------
                |
                | Aunque no exista todavía una fila activa,
                | el lock de Reserva anterior serializa
                | los intentos sobre la misma reserva.
                |
                */

                ReprogramacionReserva::query()

                    ->where(
                        'reserva_origen_id',
                        $reservaOrigen->id
                    )

                    ->orderBy(
                        'id'
                    )

                    ->lockForUpdate()

                    ->get();


                /*
                |--------------------------------------------------------------------------
                | 4. Cotizar nuevamente
                |--------------------------------------------------------------------------
                |
                | No confiamos en una cotización enviada
                | previamente por el frontend.
                |
                */

                $cotizacion =
                    $this
                        ->cotizarReprogramacion
                        ->ejecutar(
                            $usuario,
                            $reservaOrigen,
                            $viajeDestino
                        );


                /*
                |--------------------------------------------------------------------------
                | 5. Cargar pasajeros originales
                |--------------------------------------------------------------------------
                */

                $reservaOrigen->load([
                    'pasajeros.ocupacionAsiento',
                ]);


                /*
                |--------------------------------------------------------------------------
                | 6. Validar asignaciones nuevas
                |--------------------------------------------------------------------------
                |
                | Formato esperado:
                |
                | [
                |   [
                |       'pasajero_reserva_id' => 10,
                |       'asiento_viaje_id' => 32,
                |   ],
                | ]
                |
                */

                $asignaciones =
                    $this->validarAsignaciones(
                        $reservaOrigen,
                        $asignaciones
                    );


                /*
                |--------------------------------------------------------------------------
                | 7. Crear bloqueos del nuevo viaje
                |--------------------------------------------------------------------------
                |
                | Ordenamos por asiento para conservar
                | un orden estable de locks.
                |
                */

                $asignaciones =
                    collect(
                        $asignaciones
                    )
                        ->sortBy([
                            [
                                'asiento_viaje_id',
                                'asc',
                            ],

                            [
                                'pasajero_reserva_id',
                                'asc',
                            ],
                        ])
                        ->values()
                        ->all();


                $ocupacionesNuevas =
                    [];


                $pasajerosOrigen =
                    $reservaOrigen
                        ->pasajeros
                        ->keyBy(
                            'id'
                        );


                foreach (
                    $asignaciones
                    as $asignacion
                ) {

                    $pasajeroOrigen =
                        $pasajerosOrigen->get(
                            $asignacion[
                                'pasajero_reserva_id'
                            ]
                        );


                    $ocupacionOrigen =
                        $pasajeroOrigen
                            ->ocupacionAsiento;


                    if ($ocupacionOrigen === null) {
                        throw new OperacionReprogramacionInvalidaException(
                            'Uno de los pasajeros no posee una ocupación original válida.'
                        );
                    }


                    try {

                        $ocupacionNueva =
                            $this
                                ->bloquearAsiento
                                ->ejecutar(
                                    $viajeDestino,

                                    $asignacion[
                                        'asiento_viaje_id'
                                    ],

                                    /*
                                     * Conservamos exactamente
                                     * el mismo segmento.
                                     */
                                    $ocupacionOrigen
                                        ->punto_origen_id,

                                    $ocupacionOrigen
                                        ->punto_destino_id,

                                    $usuario->id
                                );

                    } catch (
                        OperacionAsientoInvalidaException $exception
                    ) {

                        throw new OperacionReprogramacionInvalidaException(
                            'Uno de los asientos seleccionados ya no se encuentra disponible para el segmento requerido.',
                            previous:
                                $exception
                        );
                    }


                    $ocupacionesNuevas[
                        $pasajeroOrigen->id
                    ] =
                        $ocupacionNueva;
                }


                /*
                |--------------------------------------------------------------------------
                | 8. Preparar pasajeros de la nueva reserva
                |--------------------------------------------------------------------------
                |
                | Datos personales:
                | snapshot de la reserva original.
                |
                | Precio:
                | NO se copia.
                |
                | CrearReserva lo obtiene nuevamente
                | desde tarifas_viaje.
                |
                */

                $pasajerosDestino =
                    [];


                foreach (
                    $reservaOrigen->pasajeros
                    as $pasajeroOrigen
                ) {

                    $ocupacionNueva =
                        $ocupacionesNuevas[
                            $pasajeroOrigen->id
                        ];


                    $pasajerosDestino[] = [

                        'ocupacion_id' =>
                            $ocupacionNueva->id,

                        'tipo_documento' =>
                            $pasajeroOrigen
                                ->tipo_documento,

                        'numero_documento' =>
                            $pasajeroOrigen
                                ->numero_documento,

                        'nombres' =>
                            $pasajeroOrigen
                                ->nombres,

                        'apellidos' =>
                            $pasajeroOrigen
                                ->apellidos,

                        'telefono' =>
                            $pasajeroOrigen
                                ->telefono,

                        'correo' =>
                            $pasajeroOrigen
                                ->correo,
                    ];
                }


                /*
                |--------------------------------------------------------------------------
                | 9. Crear reserva destino
                |--------------------------------------------------------------------------
                |
                | Reutilizamos CrearReserva:
                |
                | - valida bloques;
                | - bloquea ocupaciones;
                | - consulta tarifas;
                | - congela precios;
                | - calcula total;
                | - BLOQUEADO -> RESERVADO.
                |
                */

                $reservaDestino =
                    $this
                        ->crearReserva
                        ->ejecutar(
                            $usuario,
                            [

                                'viaje_id' =>
                                    $viajeDestino->id,

                                'correo_contacto' =>
                                    $reservaOrigen
                                        ->correo_contacto,

                                'telefono_contacto' =>
                                    $reservaOrigen
                                        ->telefono_contacto,

                                'pasajeros' =>
                                    $pasajerosDestino,
                            ]
                        );


                /*
                |--------------------------------------------------------------------------
                | 10. Protección contra cambio de tarifa
                |--------------------------------------------------------------------------
                |
                | CotizarReprogramacionReserva calculó
                | una tarifa antes de crear los bloques.
                |
                | CrearReserva vuelve a calcularla bajo lock.
                |
                | Si cambió entre ambos momentos,
                | revertimos toda la preparación.
                |
                */

                if (
                    (string) $reservaDestino->total
                    !==
                    (string) $cotizacion[
                        'total_nuevo'
                    ]
                ) {
                    throw new OperacionReprogramacionInvalidaException(
                        'La tarifa del nuevo viaje cambió durante la reprogramación. Debe realizarse una nueva cotización.'
                    );
                }


                /*
                |--------------------------------------------------------------------------
                | 11. Preservar propietario original
                |--------------------------------------------------------------------------
                |
                | CrearReserva determina el cliente según
                | el usuario ejecutor.
                |
                | Pero una reprogramación hecha por OPERADOR
                | debe seguir perteneciendo al cliente original.
                |
                */

                if (
                    (int) (
                        $reservaDestino
                            ->cliente_usuario_id
                        ?? 0
                    )
                    !==
                    (int) (
                        $reservaOrigen
                            ->cliente_usuario_id
                        ?? 0
                    )
                ) {

                    $reservaDestino->update([
                        'cliente_usuario_id' =>
                            $reservaOrigen
                                ->cliente_usuario_id,
                    ]);
                }


                /*
                |--------------------------------------------------------------------------
                | 12. Motivo
                |--------------------------------------------------------------------------
                */

                $motivo =
                    $this->normalizarMotivo(
                        $motivo
                    );


                /*
                |--------------------------------------------------------------------------
                | 13. Crear proceso de reprogramación
                |--------------------------------------------------------------------------
                |
                | Ya existe:
                |
                | - reserva origen;
                | - reserva destino;
                | - nueva tarifa;
                | - nuevos asientos reservados.
                |
                | Todavía falta resolver la parte económica.
                |
                */

                $reprogramacion =
                    ReprogramacionReserva::query()
                        ->create([

                            'codigo' =>
                                'RPG-'
                                .Str::upper(
                                    (string) Str::ulid()
                                ),

                            'reserva_origen_id' =>
                                $reservaOrigen->id,

                            'reserva_destino_id' =>
                                $reservaDestino->id,

                            'estado' =>
                                EstadoReprogramacion::PENDIENTE_AJUSTE,

                            'solicitada_por_usuario_id' =>
                                $usuario->id,

                            'motivo' =>
                                $motivo,

                            'solicitada_en' =>
                                now(),
                        ]);


                /*
                |--------------------------------------------------------------------------
                | 14. Retorno
                |--------------------------------------------------------------------------
                */

                return $reprogramacion
                    ->refresh()
                    ->load([

                        'reservaOrigen',

                        'reservaDestino' =>
                            fn ($consulta) =>
                                $consulta
                                    ->with([
                                        'pasajeros.ocupacionAsiento.asientoViaje',
                                    ]),

                        'solicitadaPor',
                    ]);
            }
        );
    }


    /**
     * Garantiza que exista exactamente
     * una asignación por pasajero original.
     */
    private function validarAsignaciones(
        Reserva $reservaOrigen,
        array $asignaciones
    ): array {

        if ($asignaciones === []) {
            throw new OperacionReprogramacionInvalidaException(
                'Debe seleccionar un asiento para cada pasajero.'
            );
        }


        $normalizadas =
            [];


        foreach (
            $asignaciones
            as $asignacion
        ) {

            if (
                ! is_array(
                    $asignacion
                )
                ||
                ! isset(
                    $asignacion[
                        'pasajero_reserva_id'
                    ],
                    $asignacion[
                        'asiento_viaje_id'
                    ]
                )
            ) {
                throw new OperacionReprogramacionInvalidaException(
                    'La asignación de asientos de reprogramación es inválida.'
                );
            }


            $pasajeroId =
                filter_var(
                    $asignacion[
                        'pasajero_reserva_id'
                    ],
                    FILTER_VALIDATE_INT
                );


            $asientoViajeId =
                filter_var(
                    $asignacion[
                        'asiento_viaje_id'
                    ],
                    FILTER_VALIDATE_INT
                );


            if (
                $pasajeroId === false
                ||
                $pasajeroId <= 0
                ||
                $asientoViajeId === false
                ||
                $asientoViajeId <= 0
            ) {
                throw new OperacionReprogramacionInvalidaException(
                    'La asignación de asientos contiene identificadores inválidos.'
                );
            }


            $normalizadas[] = [

                'pasajero_reserva_id' =>
                    (int) $pasajeroId,

                'asiento_viaje_id' =>
                    (int) $asientoViajeId,
            ];
        }


        /*
        |--------------------------------------------------------------------------
        | Pasajeros duplicados
        |--------------------------------------------------------------------------
        */

        $idsAsignados =
            collect(
                $normalizadas
            )
                ->pluck(
                    'pasajero_reserva_id'
                );


        if (
            $idsAsignados
                ->duplicates()
                ->isNotEmpty()
        ) {
            throw new OperacionReprogramacionInvalidaException(
                'Un pasajero no puede recibir más de una asignación de asiento.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Deben coincidir exactamente los pasajeros
        |--------------------------------------------------------------------------
        */

        $idsEsperados =
            $reservaOrigen
                ->pasajeros
                ->pluck(
                    'id'
                )
                ->map(
                    fn ($id) =>
                        (int) $id
                )
                ->sort()
                ->values();


        $idsRecibidos =
            $idsAsignados
                ->map(
                    fn ($id) =>
                        (int) $id
                )
                ->sort()
                ->values();


        if (
            $idsEsperados->all()
            !==
            $idsRecibidos->all()
        ) {
            throw new OperacionReprogramacionInvalidaException(
                'Debe asignarse exactamente un nuevo asiento a cada pasajero de la reserva.'
            );
        }


        return $normalizadas;
    }


    private function normalizarMotivo(
        ?string $motivo
    ): ?string {

        if ($motivo === null) {
            return null;
        }


        $motivo =
            trim(
                $motivo
            );


        if ($motivo === '') {
            return null;
        }


        if (
            mb_strlen(
                $motivo
            ) > 255
        ) {
            throw new OperacionReprogramacionInvalidaException(
                'El motivo de reprogramación supera la longitud permitida.'
            );
        }


        return $motivo;
    }
}
