<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    /**
     * Asignaciones operativas del usuario
     * en los diferentes viajes.
     */
    public function asignacionesViaje(): HasMany
    {
        return $this->hasMany(
            PersonalViaje::class
        );
    }

    /**
     * Ocupaciones de asientos creadas por el usuario.
     */
    public function ocupacionesAsientos(): HasMany
    {
        return $this->hasMany(
            OcupacionAsiento::class
        );
    }

    /**
     * Reservas donde este usuario
     * figura como cliente propietario.
     */
    public function reservasComoCliente(): HasMany
    {
        return $this->hasMany(
            Reserva::class,
            'cliente_usuario_id'
        );
    }


    /**
     * Reservas que fueron registradas
     * por este usuario.
     *
     * Puede tratarse de:
     *
     * - un CLIENTE creando su propia reserva;
     * - un OPERADOR registrando una venta presencial.
     */
    public function reservasCreadas(): HasMany
    {
        return $this->hasMany(
            Reserva::class,
            'creado_por_usuario_id'
        );
    }


    /**
     * Intentos de pago iniciados
     * por este usuario.
     */
    public function pagosIniciados(): HasMany
    {
        return $this->hasMany(
            Pago::class,
            'iniciado_por_usuario_id'
        );
    }
}
