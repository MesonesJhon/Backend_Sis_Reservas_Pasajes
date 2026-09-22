<?php

namespace App\Models;

use App\Enums\TipoAsiento;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Representa un asiento congelado dentro de un viaje.
 *
 * Este modelo no representa la disponibilidad del asiento.
 * Únicamente conserva la configuración física que tenía
 * el vehículo cuando el viaje fue programado.
 *
 * La disponibilidad será calculada posteriormente según
 * las ocupaciones existentes para cada segmento.
 */
class AsientoViaje extends Model
{
    protected $table = 'asientos_viaje';

    protected $fillable = [
        'viaje_id',
        'asiento_id',
        'codigo',
        'numero',
        'piso',
        'fila',
        'columna',
        'tipo',
        'caracteristicas',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'numero' => 'integer',
            'piso' => 'integer',
            'fila' => 'integer',
            'columna' => 'integer',

            'tipo' => TipoAsiento::class,

            'caracteristicas' => 'array',
            'activo' => 'boolean',
        ];
    }

    /**
     * Viaje al que pertenece este asiento congelado.
     */
    public function viaje(): BelongsTo
    {
        return $this->belongsTo(Viaje::class);
    }

    /**
     * Asiento físico del vehículo del cual se obtuvo
     * originalmente este snapshot.
     */
    public function asiento(): BelongsTo
    {
        return $this->belongsTo(Asiento::class);
    }

    /**
     * Ocupaciones donde participa
     * este asiento.
     */
    public function ocupaciones(): HasMany
    {
        return $this->hasMany(
            OcupacionAsiento::class
        );
    }
}
