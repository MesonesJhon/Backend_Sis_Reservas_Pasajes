<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Representa a una persona que viajará
 * dentro de una reserva.
 *
 * PasajeroReserva NO es Usuario.
 *
 * Un pasajero no necesita poseer una cuenta
 * para participar en una reserva.
 */
class PasajeroReserva extends Model
{
    use HasFactory;


    protected $table =
        'pasajeros_reserva';


    protected $fillable = [
        'reserva_id',

        'ocupacion_asiento_id',

        'tipo_documento',

        'numero_documento',

        'nombres',

        'apellidos',

        'telefono',

        'correo',

        'precio',
    ];


    protected function casts(): array
    {
        return [
            'precio' =>
                'decimal:2',
        ];
    }


    /**
     * Reserva a la que pertenece
     * este pasajero.
     */
    public function reserva(): BelongsTo
    {
        return $this->belongsTo(
            Reserva::class
        );
    }


    /**
     * Ocupación que determina el asiento
     * y segmento de viaje del pasajero.
     */
    public function ocupacionAsiento(): BelongsTo
    {
        return $this->belongsTo(
            OcupacionAsiento::class
        );
    }

    /**
     * Ticket electrónico individual
     * correspondiente a este pasajero.
     */
    public function ticket(): HasOne
    {
        return $this->hasOne(
            Ticket::class,
            'pasajero_reserva_id'
        );
    }

    /**
     * Historial operativo individual
     * correspondiente al pasajero.
     */
    public function operacionesPostventa(): HasMany
    {
        return $this->hasMany(
            OperacionPostventa::class,
            'pasajero_reserva_id'
        );
    }
}
