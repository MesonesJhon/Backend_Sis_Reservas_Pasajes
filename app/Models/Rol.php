<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Representa un conjunto de permisos que puede
 * asignarse a uno o varios usuarios.
 */
class Rol extends Model
{
    protected $table = 'roles';

    protected $fillable = [
        'nombre',
        'descripcion',
    ];

    /**
     * Obtiene los usuarios que poseen este rol.
     */
    public function usuarios(): BelongsToMany
    {
        return $this->belongsToMany(
            Usuario::class,
            'rol_usuario',
            'rol_id',
            'usuario_id'
        );
    }

    /**
     * Obtiene los permisos asociados al rol.
     */
    public function permisos(): BelongsToMany
    {
        return $this->belongsToMany(
            Permiso::class,
            'permiso_rol',
            'rol_id',
            'permiso_id'
        );
    }
}
