<?php

namespace App\Models;

use App\Enums\EstadoTicket;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Representa el ticket electrónico
 * individual de un pasajero.
 *
 * Una reserva con tres pasajeros
 * tendrá tres tickets.
 */
class Ticket extends Model
{
    use HasFactory;


    protected $table =
        'tickets';


    protected $fillable = [

        'codigo',

        'reserva_id',

        'pasajero_reserva_id',

        'estado',

        'emitido_en',

        'validado_en',

        'validado_por_usuario_id',

        'anulado_en',

        'motivo_anulacion',
    ];


    protected function casts(): array
    {
        return [

            'estado' =>
                EstadoTicket::class,

            'emitido_en' =>
                'datetime',

            'validado_en' =>
                'datetime',

            'anulado_en' =>
                'datetime',
        ];
    }


    /**
     * Reserva comercial que originó
     * este ticket.
     */
    public function reserva(): BelongsTo
    {
        return $this->belongsTo(
            Reserva::class
        );
    }


    /**
     * Pasajero propietario del ticket.
     */
    public function pasajero(): BelongsTo
    {
        return $this->belongsTo(
            PasajeroReserva::class,
            'pasajero_reserva_id'
        );
    }


    /**
     * Personal que realizó la
     * validación de embarque.
     */
    public function validadoPor(): BelongsTo
    {
        return $this->belongsTo(
            Usuario::class,
            'validado_por_usuario_id'
        );
    }


    /**
     * Indica si todavía puede
     * utilizarse para embarque.
     */
    public function estaVigente(): bool
    {
        return $this->estado
            === EstadoTicket::VIGENTE;
    }
}
