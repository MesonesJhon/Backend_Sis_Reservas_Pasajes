<?php

namespace App\Models;

use App\Enums\EstadoReprogramacion;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReprogramacionReserva extends Model
{
    use HasFactory;


    protected $table =
        'reprogramaciones_reserva';


    protected $fillable = [

        'codigo',

        'reserva_origen_id',

        'reserva_destino_id',

        'estado',

        'solicitada_por_usuario_id',

        'completada_por_usuario_id',

        'cancelada_por_usuario_id',

        'motivo',

        'motivo_cancelacion',

        'solicitada_en',

        'completada_en',

        'cancelada_en',
    ];


    protected function casts(): array
    {
        return [

            'estado' =>
                EstadoReprogramacion::class,

            'solicitada_en' =>
                'datetime',

            'completada_en' =>
                'datetime',

            'cancelada_en' =>
                'datetime',
        ];
    }


    /**
     * Reserva histórica que está siendo
     * reemplazada.
     */
    public function reservaOrigen(): BelongsTo
    {
        return $this->belongsTo(
            Reserva::class,
            'reserva_origen_id'
        );
    }


    /**
     * Nueva reserva generada como resultado
     * de la reprogramación.
     */
    public function reservaDestino(): BelongsTo
    {
        return $this->belongsTo(
            Reserva::class,
            'reserva_destino_id'
        );
    }


    /**
     * Usuario que inició la operación.
     */
    public function solicitadaPor(): BelongsTo
    {
        return $this->belongsTo(
            Usuario::class,
            'solicitada_por_usuario_id'
        );
    }


    /**
     * Usuario que consolidó la reprogramación.
     */
    public function completadaPor(): BelongsTo
    {
        return $this->belongsTo(
            Usuario::class,
            'completada_por_usuario_id'
        );
    }


    /**
     * Usuario que anuló el proceso.
     */
    public function canceladaPor(): BelongsTo
    {
        return $this->belongsTo(
            Usuario::class,
            'cancelada_por_usuario_id'
        );
    }


    public function estaActiva(): bool
    {
        return ! $this->estado
            ->esTerminal();
    }


    public function estaCompletada(): bool
    {
        return $this->estado
            === EstadoReprogramacion::COMPLETADA;
    }
}
