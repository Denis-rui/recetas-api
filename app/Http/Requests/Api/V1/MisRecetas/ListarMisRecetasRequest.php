<?php

namespace App\Http\Requests\Api\V1\MisRecetas;

use App\Rules\IdentificadorEntero;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListarMisRecetasRequest extends FormRequest
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
            'filtro' => ['sometimes', Rule::in(['todas', 'privadas', 'publicadas', 'eliminadas'])],
            'buscar' => ['sometimes', 'nullable', 'string', 'max:100'],
            'page' => ['sometimes', 'bail', new IdentificadorEntero, 'integer', 'max:'.intdiv(PHP_INT_MAX, 50)],
            'per_page' => ['sometimes', 'bail', new IdentificadorEntero, 'integer', 'between:1,50'],
        ];
    }
}
