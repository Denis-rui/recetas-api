<?php

namespace App\Http\Requests\Api\V1\MisRecetas;

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
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
        ];
    }
}
