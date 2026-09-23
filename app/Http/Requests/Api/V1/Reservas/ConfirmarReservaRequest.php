<?php

namespace App\Http\Requests\Api\V1\Reservas;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmarReservaRequest extends FormRequest
{
    public function authorize(): bool
    {
        /*
         * El acceso general se controla mediante:
         *
         * permiso:reservas.confirmar
         *
         * La Action vuelve a comprobar los roles
         * autorizados por seguridad de dominio.
         */
        return true;
    }


    public function rules(): array
    {
        /*
         * Confirmar una reserva no requiere
         * información enviada por el cliente.
         *
         * El estado se determina internamente.
         */
        return [];
    }
}
