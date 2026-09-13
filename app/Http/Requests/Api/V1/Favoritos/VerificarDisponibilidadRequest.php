<?php

namespace App\Http\Requests\Api\V1\Favoritos;

use Illuminate\Foundation\Http\FormRequest;

class VerificarDisponibilidadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->estaActivo();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'list', 'min:1', 'max:50'],
            'ids.*' => ['required', 'integer', 'min:1', 'distinct'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ids.required' => 'Debe enviar una lista de identificadores de recetas para verificar.',
            'ids.array' => 'El campo ids debe ser una lista.',
            'ids.list' => 'El campo ids debe ser una lista indexada.',
            'ids.min' => 'Debe consultar al menos un identificador de receta.',
            'ids.max' => 'No puede consultar más de 50 identificadores por petición.',
            'ids.*.integer' => 'Cada identificador debe ser un número entero.',
            'ids.*.distinct' => 'La lista contiene identificadores repetidos.',
        ];
    }
}

