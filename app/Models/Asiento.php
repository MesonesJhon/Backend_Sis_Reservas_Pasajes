<?php

namespace App\Models;

use App\Enums\TipoAsiento;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Representa un asiento físico perteneciente
 * a la configuración de un vehículo.
 *
 * No representa su disponibilidad para un viaje.
 * Los estados de reserva se gestionarán posteriormente
 * en el contexto específico de cada viaje.
 */
class Asiento extends Model
{
    protected $table = 'asientos';

    protected $fillable = [
        'vehiculo_id',
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
     * Obtiene el vehículo al que pertenece
     * físicamente este asiento.
     */
    public function vehiculo(): BelongsTo
    {
        return $this->belongsTo(Vehiculo::class);
    }
}
