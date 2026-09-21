<?php

namespace App\Models;

use App\Enums\EstadoVehiculo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Representa una unidad física utilizada
 * para transportar pasajeros.
 *
 * Un vehículo pertenece a un tipo y posee su propia
 * configuración física de asientos.
 */
class Vehiculo extends Model
{
    use HasFactory;
    protected $table = 'vehiculos';

    protected $fillable = [
        'tipo_vehiculo_id',
        'placa',
        'codigo_interno',
        'marca',
        'modelo',
        'capacidad',
        'numero_pisos',
        'caracteristicas',
        'estado',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'capacidad' => 'integer',
            'numero_pisos' => 'integer',
            'caracteristicas' => 'array',
            'estado' => EstadoVehiculo::class,
            'activo' => 'boolean',
        ];
    }

    /**
     * Obtiene la categoría a la que pertenece
     * el vehículo.
     */
    public function tipoVehiculo(): BelongsTo
    {
        return $this->belongsTo(
            TipoVehiculo::class,
            'tipo_vehiculo_id'
        );
    }

    /**
     * Obtiene la configuración física de asientos
     * perteneciente al vehículo.
     */
    public function asientos(): HasMany
    {
        return $this->hasMany(Asiento::class);
    }

    /**
     * Determina si el vehículo se encuentra en condiciones
     * administrativas y operativas para ser utilizado.
     */
    public function puedeOperar(): bool
    {
        return $this->activo
            && $this->estado->permiteOperacion();
    }

    /**
     * Viajes en los que este vehículo ha sido asignado.
     */
    public function viajes(): HasMany
    {
        return $this->hasMany(Viaje::class);
    }
}
