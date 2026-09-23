<?php

use App\Enums\EstadoReserva;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'reservas',
            function (Blueprint $table): void {

                $table->id();


                /*
                |--------------------------------------------------------------------------
                | Código comercial
                |--------------------------------------------------------------------------
                |
                | Es diferente del ID interno.
                |
                | Ejemplo:
                |
                | RSV-20260923-AB12CD
                |
                | Posteriormente CrearReserva será responsable
                | de generarlo.
                |
                */

                $table->string(
                    'codigo',
                    30
                )->unique();


                /*
                |--------------------------------------------------------------------------
                | Viaje
                |--------------------------------------------------------------------------
                |
                | Todos los pasajeros de una misma reserva
                | deben pertenecer al mismo viaje.
                |
                */

                $table->foreignId(
                    'viaje_id'
                )
                    ->constrained('viajes')
                    ->restrictOnDelete();


                /*
                |--------------------------------------------------------------------------
                | Cliente propietario
                |--------------------------------------------------------------------------
                |
                | Puede ser NULL porque un operador podrá
                | crear una reserva presencial para una persona
                | que no tenga cuenta en el sistema.
                |
                */

                $table->foreignId(
                    'cliente_usuario_id'
                )
                    ->nullable()
                    ->constrained(
                        'usuarios'
                    )
                    ->restrictOnDelete();


                /*
                |--------------------------------------------------------------------------
                | Usuario que registró la operación
                |--------------------------------------------------------------------------
                |
                | Nos permite diferenciar:
                |
                | cliente_usuario_id:
                | persona dueña de la reserva.
                |
                | creado_por_usuario_id:
                | usuario autenticado que ejecutó la operación.
                |
                */

                $table->foreignId(
                    'creado_por_usuario_id'
                )
                    ->constrained(
                        'usuarios'
                    )
                    ->restrictOnDelete();


                /*
                |--------------------------------------------------------------------------
                | Estado comercial
                |--------------------------------------------------------------------------
                */

                $table->string(
                    'estado',
                    30
                )->default(
                    EstadoReserva::PENDIENTE_PAGO->value
                );


                /*
                |--------------------------------------------------------------------------
                | Contacto principal
                |--------------------------------------------------------------------------
                |
                | Ambos son nullable a nivel de base de datos
                | porque la regla "al menos uno debe existir"
                | se validará en la capa de aplicación.
                |
                */

                $table->string(
                    'correo_contacto',
                    150
                )->nullable();

                $table->string(
                    'telefono_contacto',
                    30
                )->nullable();


                /*
                |--------------------------------------------------------------------------
                | Total congelado de la reserva
                |--------------------------------------------------------------------------
                |
                | Nunca debe venir calculado desde el frontend.
                |
                | CrearReserva sumará los precios de cada
                | pasajero utilizando las tarifas del viaje.
                |
                */

                $table->decimal(
                    'total',
                    10,
                    2
                )->default(0);


                /*
                |--------------------------------------------------------------------------
                | Expiración
                |--------------------------------------------------------------------------
                |
                | Una reserva PENDIENTE_PAGO conserva una
                | fecha máxima para completar la operación.
                |
                */

                $table->timestamp(
                    'expira_en'
                )->nullable();


                /*
                |--------------------------------------------------------------------------
                | Auditoría del ciclo de vida
                |--------------------------------------------------------------------------
                */

                $table->timestamp(
                    'confirmada_en'
                )->nullable();

                $table->timestamp(
                    'cancelada_en'
                )->nullable();

                $table->timestamp(
                    'expirada_en'
                )->nullable();


                /*
                 * Se utilizará cuando implementemos
                 * la cancelación de RF-07.
                 */
                $table->string(
                    'motivo_cancelacion',
                    255
                )->nullable();


                $table->timestamps();


                /*
                |--------------------------------------------------------------------------
                | Índices
                |--------------------------------------------------------------------------
                */

                $table->index(
                    [
                        'viaje_id',
                        'estado',
                    ],
                    'reservas_viaje_estado_index'
                );

                $table->index(
                    [
                        'cliente_usuario_id',
                        'estado',
                    ],
                    'reservas_cliente_estado_index'
                );

                /*
                 * Este índice será utilizado por el proceso
                 * que busque reservas pendientes vencidas.
                 */
                $table->index(
                    [
                        'estado',
                        'expira_en',
                    ],
                    'reservas_estado_expiracion_index'
                );

                $table->index(
                    'creado_por_usuario_id',
                    'reservas_creado_por_index'
                );
            }
        );
    }


    public function down(): void
    {
        Schema::dropIfExists(
            'reservas'
        );
    }
};
