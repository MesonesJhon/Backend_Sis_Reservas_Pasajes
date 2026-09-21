<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PuntoRuta extends Model
{
    use HasFactory;

    protected $table = 'puntos_ruta';

    protected $fillable = [
        'ruta_id',
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
     * Ruta a la que pertenece esta posición
     * dentro del recorrido.
     */
    public function ruta(): BelongsTo
    {
        return $this->belongsTo(
            Ruta::class,
            'ruta_id'
        );
    }

    /**
     * Punto físico asociado a esta posición
     * del recorrido.
     */
    public function punto(): BelongsTo
    {
        return $this->belongsTo(
            Punto::class,
            'punto_id'
        );
    }

    /**
     * Indica si en este punto se permite
     * subir pasajeros.
     */
    public function permiteEmbarque(): bool
    {
        return $this->permite_embarque;
    }

    /**
     * Indica si en este punto se permite
     * bajar pasajeros.
     */
    public function permiteDesembarque(): bool
    {
        return $this->permite_desembarque;
    }
}
