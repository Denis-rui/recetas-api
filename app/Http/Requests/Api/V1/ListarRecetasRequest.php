<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Ingrediente;
use App\Rules\IdentificadorEntero;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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
     * @return array<string, array<int, string|ValidationRule>>
     */
    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'bail', new IdentificadorEntero, 'integer', 'max:'.intdiv(PHP_INT_MAX, 50)],
            'per_page' => ['sometimes', 'bail', new IdentificadorEntero, 'integer', 'between:1,50'],
            'buscar' => ['sometimes', 'nullable', 'string', 'max:100'],
            'categoria_id' => ['sometimes', 'bail', new IdentificadorEntero, 'exists:categorias,id'],
            'ingredientes' => ['sometimes', 'bail', 'array', 'list', 'max:50'],
            'ingredientes.*' => [
                'bail',
                'required',
                new IdentificadorEntero,
                'distinct',
            ],
        ];
    }

    /**
     * @return array<int, Closure>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $ingredientes = $this->input('ingredientes', []);
                if ($ingredientes !== [] && Ingrediente::query()->whereIn('id', $ingredientes)->count() !== count($ingredientes)) {
                    $validator->errors()->add('ingredientes', 'Uno o más ingredientes seleccionados ya no existen.');
                }
            },
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
            'categoria_id.integer' => 'La categoría debe ser un número entero.',
            'categoria_id.min' => 'La categoría no es válida.',
            'categoria_id.exists' => 'La categoría seleccionada no es válida.',
            'ingredientes.array' => 'Los ingredientes deben enviarse como una lista de identificadores.',
            'ingredientes.list' => 'Los ingredientes deben formar una lista con índices consecutivos desde cero.',
            'ingredientes.max' => 'Puedes seleccionar como máximo 50 ingredientes.',
            'ingredientes.*.required' => 'Cada ingrediente debe tener un identificador.',
            'ingredientes.*.integer' => 'Cada ingrediente debe ser un identificador entero.',
            'ingredientes.*.min' => 'Cada identificador de ingrediente debe ser mayor que cero.',
            'ingredientes.*.distinct' => 'No repitas ingredientes en la selección.',
        ];
    }
}
