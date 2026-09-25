<?php

namespace App\Http\Requests\Api\V1\Pagos;

use App\Enums\CanalPago;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IniciarPagoRequest extends FormRequest
{
    /**
     * Convertimos el header HTTP en un dato
     * validable por Laravel.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([

            'idempotency_key' =>
                $this->header(
                    'Idempotency-Key'
                ),
        ]);
    }


    public function authorize(): bool
    {
        /*
         * Middleware:
         *
         * permiso:pagos.crear
         *
         * La Action valida además propiedad.
         */
        return true;
    }


    public function rules(): array
    {
        return [

            /*
             * WEB o MOVIL.
             */
            'canal' => [
                'required',
                Rule::enum(
                    CanalPago::class
                ),
            ],


            /*
             * Debe generarse una vez por intención
             * de pago y conservarse durante reintentos.
             */
            'idempotency_key' => [
                'required',
                'uuid',
            ],
        ];
    }


    public function messages(): array
    {
        return [

            'canal.required' =>
                'Debe indicar el canal de pago.',

            'canal.enum' =>
                'El canal de pago no es válido.',

            'idempotency_key.required' =>
                'Debe enviar el header Idempotency-Key.',

            'idempotency_key.uuid' =>
                'El header Idempotency-Key debe contener un UUID válido.',
        ];
    }
}
