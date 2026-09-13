<?php

namespace App\Http\Requests\Api\V1\Valoraciones;

use Illuminate\Foundation\Http\FormRequest;

class GuardarValoracionRequest extends FormRequest
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
            'puntuacion' => ['required', 'integer', 'between:1,5'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'puntuacion.required' => 'Debe indicar una puntuación entre 1 y 5.',
            'puntuacion.integer' => 'La puntuación debe ser un número entero.',
            'puntuacion.between' => 'La puntuación debe estar entre 1 y 5 sombreritos de chef.',
        ];
    }
}

