<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Representa a una persona que puede autenticarse en el sistema.
 *
 * Un usuario puede tener uno o varios roles y los permisos
 * efectivos se obtienen mediante dichos roles.
 */
class Usuario extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'usuarios';

    protected $fillable = [
        'nombres',
        'apellidos',
        'correo',
        'contrasena',
        'activo',
    ];

    protected $hidden = [
        'contrasena',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'correo_verificado_en' => 'datetime',
            'contrasena' => 'hashed',
            'activo' => 'boolean',
        ];
    }

    /**
     * Obtiene los roles asignados al usuario.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            Rol::class,
            'rol_usuario',
            'usuario_id',
            'rol_id'
        );
    }

    /**
     * Determina si el usuario posee un rol específico.
     */
    public function tieneRol(string $rol): bool
    {
        return $this->roles()
            ->where('nombre', $rol)
            ->exists();
    }

    /**
     * Determina si alguno de los roles del usuario
     * contiene el permiso solicitado.
     */
    public function tienePermiso(string $permiso): bool
    {
        return $this->roles()
            ->whereHas('permisos', function ($consulta) use ($permiso) {
                $consulta->where('nombre', $permiso);
            })
            ->exists();
    }
}
