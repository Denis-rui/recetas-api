<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class ListarRecetasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('buscar') && is_string($this->buscar)) {
            $trimmed = trim($this->buscar);
            $this->merge([
                'buscar' => $trimmed !== '' ? $trimmed : null,
            ]);
        }
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'between:1,50'],
            'buscar' => ['sometimes', 'nullable', 'string', 'max:100'],
            'categoria_id' => ['sometimes', 'integer', 'min:1', 'exists:categorias,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'page.integer' => 'La página debe ser un número entero.',
            'page.min' => 'La página debe ser de al menos 1.',
            'per_page.integer' => 'La cantidad por página debe ser un número entero.',
            'per_page.between' => 'La cantidad por página debe estar entre 1 y 50.',
            'buscar.string' => 'El término de búsqueda debe ser texto.',
            'buscar.max' => 'El término de búsqueda no puede superar los 100 caracteres.',
            'categoria_id.integer' => 'La categoría debe ser un número entero.',
            'categoria_id.min' => 'La categoría no es válida.',
            'categoria_id.exists' => 'La categoría seleccionada no es válida.',
        ];
    }
}

