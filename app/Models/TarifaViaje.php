<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TarifaViaje extends Model
{
    use HasFactory;

    protected $table = 'tarifas_viaje';

    protected $fillable = [
        'viaje_id',
        'punto_origen_id',
        'punto_destino_id',
        'precio',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            /*
             * Mantener dos decimales evita perder
             * precisión al representar dinero.
             */
            'precio' => 'decimal:2',
            'activo' => 'boolean',
        ];
    }

    public function viaje(): BelongsTo
    {
        return $this->belongsTo(Viaje::class);
    }

    /**
     * Punto desde el cual se comercializa
     * este segmento.
     */
    public function puntoOrigen(): BelongsTo
    {
        return $this->belongsTo(
            Punto::class,
            'punto_origen_id'
        );
    }

    /**
     * Punto donde termina el segmento comercializado.
     */
    public function puntoDestino(): BelongsTo
    {
        return $this->belongsTo(
            Punto::class,
            'punto_destino_id'
        );
    }
}
