<?php

namespace App\Models;

use App\Enums\EstadoReserva;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Representa una operación comercial de reserva.
 *
 * Una reserva:
 *
 * - pertenece a un viaje;
 * - puede contener uno o varios pasajeros;
 * - agrupa una o varias ocupaciones de asiento;
 * - posee un estado comercial independiente
 *   del estado de las ocupaciones.
 */
class Reserva extends Model
{
    use HasFactory;


    protected $table = 'reservas';


    protected $fillable = [
        'codigo',

        'viaje_id',

        'cliente_usuario_id',

        'creado_por_usuario_id',

        'estado',

        'correo_contacto',

        'telefono_contacto',

        'total',

        'expira_en',

        'confirmada_en',

        'cancelada_en',

        'expirada_en',

        'motivo_cancelacion',
    ];


    protected function casts(): array
    {
        return [

            'estado' =>
                EstadoReserva::class,

            'total' =>
                'decimal:2',

            'expira_en' =>
                'datetime',

            'confirmada_en' =>
                'datetime',

            'cancelada_en' =>
                'datetime',

            'expirada_en' =>
                'datetime',
        ];
    }


    /**
     * Viaje al que pertenece la reserva.
     */
    public function viaje(): BelongsTo
    {
        return $this->belongsTo(
            Viaje::class
        );
    }


    /**
     * Cliente propietario de la reserva.
     *
     * Puede ser NULL cuando la reserva haya sido
     * registrada presencialmente por un operador.
     */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(
            Usuario::class,
            'cliente_usuario_id'
        );
    }


    /**
     * Usuario autenticado que registró
     * originalmente la reserva.
     */
    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(
            Usuario::class,
            'creado_por_usuario_id'
        );
    }


    /**
     * Pasajeros incluidos en la reserva.
     */
    public function pasajeros(): HasMany
    {
        return $this->hasMany(
            PasajeroReserva::class
        );
    }


    /**
     * Ocupaciones de asiento asociadas
     * directamente a la reserva.
     */
    public function ocupaciones(): HasMany
    {
        return $this->hasMany(
            OcupacionAsiento::class
        );
    }


    /**
     * Determina si todavía se encuentra
     * esperando completar el pago.
     */
    public function estaPendientePago(): bool
    {
        return $this->estado
            === EstadoReserva::PENDIENTE_PAGO;
    }


    /**
     * Determina si la reserva ya superó
     * su fecha máxima de vigencia.
     */
    public function estaExpiradaPorTiempo(): bool
    {
        return $this->expira_en !== null
            && $this->expira_en->isPast();
    }
}
