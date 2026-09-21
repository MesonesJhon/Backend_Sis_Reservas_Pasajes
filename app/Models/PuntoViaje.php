<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PuntoViaje extends Model
{
    use HasFactory;

    protected $table = 'puntos_viaje';

    protected $fillable = [
        'viaje_id',
        'punto_id',
        'orden',
        'permite_embarque',
        'permite_desembarque',
        'minutos_desde_origen',
    ];

    protected function casts(): array
    {
        return [
            'orden' => 'integer',
            'permite_embarque' => 'boolean',
            'permite_desembarque' => 'boolean',
            'minutos_desde_origen' => 'integer',
        ];
    }

    /**
     * Viaje al que pertenece esta posición
     * dentro del recorrido.
     */
    public function viaje(): BelongsTo
    {
        return $this->belongsTo(Viaje::class);
    }

    /**
     * Punto físico asociado.
     */
    public function punto(): BelongsTo
    {
        return $this->belongsTo(Punto::class);
    }

    public function permiteEmbarque(): bool
    {
        return $this->permite_embarque;
    }

    public function permiteDesembarque(): bool
    {
        return $this->permite_desembarque;
    }
}
