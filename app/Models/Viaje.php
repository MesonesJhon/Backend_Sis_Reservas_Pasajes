<?php

namespace App\Models;

use App\Enums\EstadoViaje;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Viaje extends Model
{
    use HasFactory;

    protected $table = 'viajes';

    protected $fillable = [
        'codigo',
        'ruta_id',
        'vehiculo_id',
        'salida_programada',
        'llegada_estimada',
        'estado',
        'observaciones',
    ];

    protected function casts(): array
    {
        return [
            'salida_programada' => 'datetime',
            'llegada_estimada' => 'datetime',
            'estado' => EstadoViaje::class,
        ];
    }

    /**
     * Ruta utilizada como base para programar el viaje.
     */
    public function ruta(): BelongsTo
    {
        return $this->belongsTo(Ruta::class);
    }

    /**
     * Vehículo asignado al viaje.
     */
    public function vehiculo(): BelongsTo
    {
        return $this->belongsTo(Vehiculo::class);
    }

    /**
     * Copia del recorrido utilizada específicamente
     * por este viaje.
     *
     * El orden no se coloca directamente en la relación
     * para permitir consultas con orden diferente cuando
     * sea necesario.
     */
    public function puntosViaje(): HasMany
    {
        return $this->hasMany(PuntoViaje::class);
    }

    /**
     * Personal asignado al viaje.
     */
    public function personal(): HasMany
    {
        return $this->hasMany(PersonalViaje::class);
    }

    /**
     * Tarifas configuradas para los distintos
     * segmentos comercializables del viaje.
     */
    public function tarifas(): HasMany
    {
        return $this->hasMany(TarifaViaje::class);
    }

    /**
     * Determina si el viaje todavía puede modificar
     * su configuración administrativa.
     */
    public function permiteEdicion(): bool
    {
        return $this->estado->permiteEdicion();
    }

    /**
     * Determina si el viaje terminó su ciclo operativo.
     */
    public function estaFinalizado(): bool
    {
        return $this->estado === EstadoViaje::FINALIZADO;
    }

    /**
     * Determina si el viaje fue cancelado.
     */
    public function estaCancelado(): bool
    {
        return $this->estado === EstadoViaje::CANCELADO;
    }

    /**
     * Obtiene el primer punto del recorrido consolidado.
     */
    public function origen(): ?PuntoViaje
    {
        return $this->puntosViaje()
            ->with('punto')
            ->orderBy('orden')
            ->first();
    }

    /**
     * Obtiene el último punto del recorrido consolidado.
     */
    public function destino(): ?PuntoViaje
    {
        return $this->puntosViaje()
            ->with('punto')
            ->orderByDesc('orden')
            ->first();
    }

    /**
     * Inventario de asientos congelado para este viaje.
     */
    public function asientosViaje(): HasMany
    {
        return $this->hasMany(AsientoViaje::class);
    }
}
