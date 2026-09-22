<?php

namespace App\Http\Requests\Api\V1\BusquedaViajes;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida los parámetros necesarios para buscar viajes.
 *
 * Esta validación evita que la lógica de negocio reciba
 * información incompleta o incorrecta.
 */
class BuscarViajesRequest extends FormRequest
{
    public function authorize(): bool
    {
        /*
         * La búsqueda comercial inicialmente podrá ser pública.
         *
         * Más adelante podemos cambiarlo a auth:sanctum
         * si el negocio requiere usuarios registrados.
         */
        return true;
    }


    public function rules(): array
    {
        return [
            'punto_origen_id' => [
                'required',
                'integer',
                // 'exists:puntos,id',
            ],

            'punto_destino_id' => [
                'required',
                'integer',
                // 'exists:puntos,id',
            ],

            'fecha' => [
                'required',
                'date',
            ],
        ];
    }


    public function messages(): array
    {
        return [
            'punto_origen_id.required'
                => 'El punto de origen es obligatorio.',

            'punto_destino_id.required'
                => 'El punto de destino es obligatorio.',

            'fecha.required'
                => 'La fecha del viaje es obligatoria.',
        ];
    }
}
