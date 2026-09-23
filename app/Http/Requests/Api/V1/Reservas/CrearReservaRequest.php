<?php

namespace App\Http\Requests\Api\V1\Reservas;

use Illuminate\Foundation\Http\FormRequest;

class CrearReservaRequest extends FormRequest
{
    public function authorize(): bool
    {
        /*
         * La autorización por permiso se realiza
         * mediante:
         *
         * permiso:reservas.crear
         */
        return true;
    }


    public function rules(): array
    {
        return [

            /*
            |--------------------------------------------------------------------------
            | Viaje
            |--------------------------------------------------------------------------
            */

            'viaje_id' => [
                'required',
                'integer',
                'exists:viajes,id',
            ],


            /*
            |--------------------------------------------------------------------------
            | Contacto principal
            |--------------------------------------------------------------------------
            |
            | Al menos correo o teléfono.
            |
            */

            'correo_contacto' => [
                'nullable',
                'required_without:telefono_contacto',
                'email',
                'max:150',
            ],

            'telefono_contacto' => [
                'nullable',
                'required_without:correo_contacto',
                'string',
                'max:30',
            ],


            /*
            |--------------------------------------------------------------------------
            | Pasajeros
            |--------------------------------------------------------------------------
            */

            'pasajeros' => [
                'required',
                'array',
                'min:1',
            ],


            'pasajeros.*.ocupacion_id' => [
                'required',
                'integer',
                'distinct',
                'exists:ocupaciones_asientos,id',
            ],


            'pasajeros.*.tipo_documento' => [
                'required',
                'string',
                'max:20',
            ],


            'pasajeros.*.numero_documento' => [
                'required',
                'string',
                'max:30',
            ],


            'pasajeros.*.nombres' => [
                'required',
                'string',
                'max:100',
            ],


            'pasajeros.*.apellidos' => [
                'required',
                'string',
                'max:100',
            ],


            'pasajeros.*.telefono' => [
                'nullable',
                'string',
                'max:30',
            ],


            'pasajeros.*.correo' => [
                'nullable',
                'email',
                'max:150',
            ],
        ];
    }


    public function messages(): array
    {
        return [

            'viaje_id.required' =>
                'Debe indicar el viaje.',

            'viaje_id.exists' =>
                'El viaje indicado no existe.',


            'correo_contacto.required_without' =>
                'Debe proporcionar un correo o teléfono de contacto.',

            'telefono_contacto.required_without' =>
                'Debe proporcionar un teléfono o correo de contacto.',


            'pasajeros.required' =>
                'Debe registrar al menos un pasajero.',

            'pasajeros.min' =>
                'Debe registrar al menos un pasajero.',


            'pasajeros.*.ocupacion_id.distinct' =>
                'Una ocupación no puede asignarse a más de un pasajero.',

            'pasajeros.*.ocupacion_id.exists' =>
                'Una de las ocupaciones seleccionadas no existe.',
        ];
    }
}
