<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Ruta extends Model
{
    use HasFactory;

    protected $table = 'rutas';

    protected $fillable = [
        'codigo',
        'nombre',
        'duracion_estimada_minutos',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'duracion_estimada_minutos' => 'integer',
            'activo' => 'boolean',
        ];
    }

    /**
     * Obtiene todos los elementos que forman
     * el recorrido de la ruta.
     */
    public function puntosRuta(): HasMany
    {
        return $this->hasMany(
            PuntoRuta::class,
            'ruta_id'
        );
    }

    /**
     * Obtiene el primer punto del recorrido,
     * el cual representa el origen.
     */
    public function origen(): ?PuntoRuta
    {
        return $this->puntosRuta()
            ->with('punto')
            ->orderBy('orden')
            ->first();
    }

    /**
     * Obtiene el último punto del recorrido,
     * el cual representa el destino.
     */
    public function destino(): ?PuntoRuta
    {
        return $this->puntosRuta()
            ->with('punto')
            ->orderByDesc('orden')
            ->first();
    }

    /**
     * Obtiene directamente los puntos físicos asociados
     * a la ruta.
     *
     * Para lógica que necesite orden, embarque, desembarque
     * o tiempos se debe utilizar puntosRuta().
     */
    public function puntos(): BelongsToMany
    {
        return $this->belongsToMany(
            Punto::class,
            'puntos_ruta',
            'ruta_id',
            'punto_id'
        )
            ->withPivot([
                'orden',
                'permite_embarque',
                'permite_desembarque',
                'minutos_desde_origen',
            ])
            ->withTimestamps();
    }

    /**
     * Determina si la ruta está habilitada
     * administrativamente.
     */
    public function estaActiva(): bool
    {
        return $this->activo;
    }
}
