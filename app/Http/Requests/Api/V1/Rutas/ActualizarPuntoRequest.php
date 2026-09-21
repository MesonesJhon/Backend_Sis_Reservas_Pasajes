<?php

namespace App\Http\Requests\Api\V1\Rutas;

use App\Enums\TipoPunto;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ActualizarPuntoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * La actualización general no modifica el estado activo.
     * Esa responsabilidad pertenece a un endpoint separado.
     */
    public function rules(): array
    {
        return [
            'nombre' => [
                'required',
                'string',
                'max:150',
            ],

            'tipo' => [
                'required',
                Rule::enum(TipoPunto::class),
            ],

            'departamento' => [
                'required',
                'string',
                'max:100',
            ],

            'provincia' => [
                'required',
                'string',
                'max:100',
            ],

            'distrito' => [
                'required',
                'string',
                'max:100',
            ],

            'direccion' => [
                'nullable',
                'string',
                'max:255',
            ],

            'referencia' => [
                'nullable',
                'string',
                'max:255',
            ],

            'latitud' => [
                'nullable',
                'numeric',
                'between:-90,90',
            ],

            'longitud' => [
                'nullable',
                'numeric',
                'between:-180,180',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'nombre' => $this->normalizarTexto(
                $this->input('nombre')
            ),

            'departamento' => $this->normalizarTexto(
                $this->input('departamento')
            ),

            'provincia' => $this->normalizarTexto(
                $this->input('provincia')
            ),

            'distrito' => $this->normalizarTexto(
                $this->input('distrito')
            ),

            'direccion' => $this->normalizarTextoOpcional(
                $this->input('direccion')
            ),

            'referencia' => $this->normalizarTextoOpcional(
                $this->input('referencia')
            ),
        ]);
    }

    private function normalizarTexto(?string $valor): ?string
    {
        return $valor !== null
            ? trim($valor)
            : null;
    }

    private function normalizarTextoOpcional(
        ?string $valor
    ): ?string {
        if ($valor === null) {
            return null;
        }

        $valor = trim($valor);

        return $valor === ''
            ? null
            : $valor;
    }
}
