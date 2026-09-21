<?php

namespace App\Models;

use App\Enums\FuncionPersonalViaje;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonalViaje extends Model
{
    use HasFactory;

    protected $table = 'personal_viaje';

    protected $fillable = [
        'viaje_id',
        'usuario_id',
        'funcion',
    ];

    protected function casts(): array
    {
        return [
            'funcion' => FuncionPersonalViaje::class,
        ];
    }

    /**
     * Viaje al que fue asignado el trabajador.
     */
    public function viaje(): BelongsTo
    {
        return $this->belongsTo(Viaje::class);
    }

    /**
     * Usuario que participa como personal del viaje.
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class);
    }

    /**
     * Permite comprobar rápidamente si esta asignación
     * corresponde al conductor principal.
     */
    public function esConductor(): bool
    {
        return $this->funcion === FuncionPersonalViaje::CONDUCTOR;
    }
}
