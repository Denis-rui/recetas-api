<?php

namespace App\Http\Requests\Api\V1;

use App\Rules\IdentificadorEntero;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ListarIngredientesRequest extends FormRequest
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
     * @return array<string, array<int, string|ValidationRule>>
     */
    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'bail', new IdentificadorEntero, 'integer', 'max:'.intdiv(PHP_INT_MAX, 50)],
            'per_page' => ['sometimes', 'bail', new IdentificadorEntero, 'integer', 'between:1,50'],
            'buscar' => ['sometimes', 'nullable', 'string', 'max:100'],
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
            'page.max' => 'La página solicitada supera el límite permitido.',
            'per_page.integer' => 'La cantidad por página debe ser un número entero.',
            'per_page.between' => 'La cantidad por página debe estar entre 1 y 50.',
            'buscar.string' => 'El término de búsqueda debe ser texto.',
            'buscar.max' => 'El término de búsqueda no puede superar los 100 caracteres.',
        ];
    }
}
