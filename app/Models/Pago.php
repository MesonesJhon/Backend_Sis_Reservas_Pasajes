<?php

namespace App\Models;

use App\Enums\CanalPago;
use App\Enums\EstadoPago;
use App\Enums\ProveedorPago;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Representa un intento de pago de una reserva.
 *
 * Una reserva puede tener múltiples intentos,
 * pero cada intento posee una clave de
 * idempotencia única.
 */
class Pago extends Model
{
    use HasFactory;


    protected $table = 'pagos';


    protected $fillable = [

        'reserva_id',

        'iniciado_por_usuario_id',

        'proveedor',

        'canal',

        'idempotency_key',

        'external_reference',

        'proveedor_order_id',

        'proveedor_payment_id',

        'monto',

        'moneda',

        'estado',

        'estado_proveedor',

        'detalle_estado',

        'checkout_url',

        'requiere_revision',

        'motivo_revision',

        'error_codigo',

        'error_mensaje',

        'aprobado_en',

        'rechazado_en',

        'cancelado_en',

        'reembolsado_en',

        'ultima_verificacion_en',
    ];


    protected function casts(): array
    {
        return [

            'proveedor' =>
                ProveedorPago::class,

            'canal' =>
                CanalPago::class,

            'estado' =>
                EstadoPago::class,

            'monto' =>
                'decimal:2',

            'requiere_revision' =>
                'boolean',

            'aprobado_en' =>
                'datetime',

            'rechazado_en' =>
                'datetime',

            'cancelado_en' =>
                'datetime',

            'reembolsado_en' =>
                'datetime',

            'ultima_verificacion_en' =>
                'datetime',
        ];
    }


    /**
     * Reserva a la que pertenece
     * este intento de pago.
     */
    public function reserva(): BelongsTo
    {
        return $this->belongsTo(
            Reserva::class
        );
    }


    /**
     * Usuario autenticado que inició
     * el intento.
     */
    public function iniciadoPor(): BelongsTo
    {
        return $this->belongsTo(
            Usuario::class,
            'iniciado_por_usuario_id'
        );
    }


    /**
     * Determina si Mercado Pago ya aprobó
     * financieramente el intento.
     */
    public function estaAprobado(): bool
    {
        return $this->estado
            === EstadoPago::APROBADO;
    }


    /**
     * Indica si todavía debemos esperar
     * una resolución del proveedor.
     */
    public function estaPendiente(): bool
    {
        return in_array(
            $this->estado,
            [
                EstadoPago::CREADO,
                EstadoPago::PENDIENTE,
            ],
            true
        );
    }
}
