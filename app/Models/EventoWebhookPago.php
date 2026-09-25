<?php

namespace App\Models;

use App\Enums\ProveedorPago;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventoWebhookPago extends Model
{
    use HasFactory;


    protected $table =
        'eventos_webhook_pago';


    protected $fillable = [

        'proveedor',

        'evento_id',

        'tipo',

        'accion',

        'data_id',

        'request_id',

        'procesado',

        'procesado_en',

        'error_mensaje',
    ];


    protected function casts(): array
    {
        return [

            'proveedor' =>
                ProveedorPago::class,

            'procesado' =>
                'boolean',

            'procesado_en' =>
                'datetime',
        ];
    }
}
