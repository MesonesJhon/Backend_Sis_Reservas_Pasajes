<?php

namespace App\Models;

use App\Enums\OrigenOperacionPostventa;
use App\Enums\TipoOperacionPostventa;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bitácora consolidada de operaciones
 * posteriores a la venta y de ejecución
 * operativa del viaje.
 *
 * Esta entidad complementa, pero NO sustituye,
 * los campos específicos de auditoría existentes
 * en Reserva, Pago y Ticket.
 */
class OperacionPostventa extends Model
{
    use HasFactory;


    protected $table =
        'operaciones_postventa';


    protected $fillable = [

        'tipo',

        'origen',

        'reserva_id',

        'viaje_id',

        'pago_id',

        'ticket_id',

        'pasajero_reserva_id',

        'ejecutado_por_usuario_id',

        'motivo',

        'monto',

        'moneda',

        'datos',

        'clave_idempotencia',

        'ocurrio_en',
    ];


    protected function casts(): array
    {
        return [

            'tipo' =>
                TipoOperacionPostventa::class,

            'origen' =>
                OrigenOperacionPostventa::class,

            'monto' =>
                'decimal:2',

            'datos' =>
                'array',

            'ocurrio_en' =>
                'datetime',
        ];
    }


    /**
     * Reserva relacionada con la operación.
     */
    public function reserva(): BelongsTo
    {
        return $this->belongsTo(
            Reserva::class
        );
    }


    /**
     * Viaje involucrado.
     */
    public function viaje(): BelongsTo
    {
        return $this->belongsTo(
            Viaje::class
        );
    }


    /**
     * Pago relacionado.
     */
    public function pago(): BelongsTo
    {
        return $this->belongsTo(
            Pago::class
        );
    }


    /**
     * Ticket individual relacionado.
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(
            Ticket::class
        );
    }


    /**
     * Pasajero afectado por la operación.
     */
    public function pasajero(): BelongsTo
    {
        return $this->belongsTo(
            PasajeroReserva::class,
            'pasajero_reserva_id'
        );
    }


    /**
     * Usuario responsable de ejecutar
     * la operación cuando corresponda.
     */
    public function ejecutadoPor(): BelongsTo
    {
        return $this->belongsTo(
            Usuario::class,
            'ejecutado_por_usuario_id'
        );
    }
}
