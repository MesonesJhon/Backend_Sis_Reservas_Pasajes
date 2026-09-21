<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Representa una categoría utilizada para clasificar
 * los vehículos de transporte.
 *
 * Ejemplos: BUS, COMBI y COLECTIVO.
 */
class TipoVehiculo extends Model
{
    protected $table = 'tipos_vehiculo';

    protected $fillable = [
        'nombre',
        'descripcion',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    /**
     * Obtiene los vehículos que pertenecen
     * a este tipo.
     */
    public function vehiculos(): HasMany
    {
        return $this->hasMany(Vehiculo::class);
    }
}
