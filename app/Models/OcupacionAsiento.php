<?php

namespace App\Models;


use App\Enums\EstadoOcupacionAsiento;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


/**
 * Representa la ocupación comercial
 * de un asiento dentro de un segmento.
 *
 * Ejemplo:
 *
 * Asiento A1
 *
 * Chiclayo -> Olmos
 *
 * ocupado
 */
class OcupacionAsiento extends Model
{


    protected $table = 'ocupaciones_asientos';



    protected $fillable = [

        'usuario_id',

        'viaje_id',

        'asiento_viaje_id',

        'punto_origen_id',

        'punto_destino_id',

        'estado',

        'expira_en',

    ];



    protected function casts(): array
    {
        return [

            'estado'
                => EstadoOcupacionAsiento::class,


            'expira_en'
                => 'datetime',

        ];
    }




    /**
     * Viaje asociado.
     */
    public function viaje(): BelongsTo
    {
        return $this->belongsTo(
            Viaje::class
        );
    }



    /**
     * Asiento congelado del viaje.
     */
    public function asientoViaje(): BelongsTo
    {
        return $this->belongsTo(
            AsientoViaje::class
        );
    }



    /**
     * Punto donde inicia la ocupación.
     */
    public function puntoOrigen(): BelongsTo
    {
        return $this->belongsTo(
            Punto::class,
            'punto_origen_id'
        );
    }



    /**
     * Punto donde termina la ocupación.
     */
    public function puntoDestino(): BelongsTo
    {
        return $this->belongsTo(
            Punto::class,
            'punto_destino_id'
        );
    }

    /**
     * Usuario propietario de la ocupación.
     *
     * Es quien seleccionó originalmente
     * el asiento durante el proceso de compra.
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(
            Usuario::class
        );
    }

}
