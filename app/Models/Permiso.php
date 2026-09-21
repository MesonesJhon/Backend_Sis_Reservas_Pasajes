<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Representa una acción específica que puede realizarse
 * dentro del sistema.
 */
class Permiso extends Model
{
    protected $table = 'permisos';

    protected $fillable = [
        'nombre',
        'descripcion',
    ];

    /**
     * Obtiene los roles que contienen este permiso.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            Rol::class,
            'permiso_rol',
            'permiso_id',
            'rol_id'
        );
    }
}
