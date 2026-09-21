<?php

namespace App\Models;

use App\Enums\TipoPunto;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Punto extends Model
{
    use HasFactory;

    protected $table = 'puntos';

    /**
     * Atributos que pueden asignarse masivamente.
     */
    protected $fillable = [
        'nombre',
        'tipo',
        'departamento',
        'provincia',
        'distrito',
        'direccion',
        'referencia',
        'latitud',
        'longitud',
        'activo',
    ];

    /**
     * Convierte automáticamente determinados campos
     * de la base de datos a tipos propios de PHP.
     */
    protected function casts(): array
    {
        return [
            'tipo' => TipoPunto::class,
            'latitud' => 'decimal:7',
            'longitud' => 'decimal:7',
            'activo' => 'boolean',
        ];
    }

    /**
     * Obtiene las participaciones de este punto
     * dentro de los recorridos de las rutas.
     */
    public function puntosRuta(): HasMany
    {
        return $this->hasMany(
            PuntoRuta::class,
            'punto_id'
        );
    }

    /**
     * Determina si el punto puede utilizarse
     * en nuevas configuraciones de rutas.
     */
    public function puedeUtilizarse(): bool
    {
        return $this->activo;
    }

    /**
     * Obtiene las rutas en las que participa
     * este punto físico.
     */
    public function rutas(): BelongsToMany
    {
        return $this->belongsToMany(
            Ruta::class,
            'puntos_ruta',
            'punto_id',
            'ruta_id'
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
     * Apariciones del punto en recorridos consolidados
     * de viajes.
     */
    public function puntosViaje(): HasMany
    {
        return $this->hasMany(PuntoViaje::class);
    }

    /**
     * Tarifas en las que este punto funciona
     * como origen del segmento.
     */
    public function tarifasComoOrigen(): HasMany
    {
        return $this->hasMany(
            TarifaViaje::class,
            'punto_origen_id'
        );
    }

    /**
     * Tarifas en las que este punto funciona
     * como destino del segmento.
     */
    public function tarifasComoDestino(): HasMany
    {
        return $this->hasMany(
            TarifaViaje::class,
            'punto_destino_id'
        );
    }
}
