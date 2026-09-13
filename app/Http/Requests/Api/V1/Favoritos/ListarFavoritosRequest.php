<?php

namespace App\Http\Requests\Api\V1\Favoritos;

use App\Rules\IdentificadorEntero;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ListarFavoritosRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->estaActivo();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'bail', new IdentificadorEntero, 'integer', 'max:'.intdiv(PHP_INT_MAX, 50)],
            'per_page' => ['sometimes', 'bail', new IdentificadorEntero, 'integer', 'between:1,50'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'page.max' => 'La página solicitada supera el límite permitido.',
            'per_page.between' => 'La cantidad por página debe estar entre 1 y 50.',
        ];
    }
}
