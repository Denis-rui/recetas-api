<?php

namespace App\Http\Requests\Api\V1\MisSolicitudes;

use App\Rules\IdentificadorEntero;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListarMisSolicitudesRequest extends FormRequest
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
            'estado' => ['sometimes', Rule::in(['todos', 'pendiente', 'aprobada', 'rechazada', 'cancelada'])],
            'tipo' => ['sometimes', Rule::in(['todos', 'publicacion', 'correccion'])],
            'page' => ['sometimes', 'bail', new IdentificadorEntero, 'integer', 'max:'.intdiv(PHP_INT_MAX, 50)],
            'per_page' => ['sometimes', 'bail', new IdentificadorEntero, 'integer', 'between:1,50'],
        ];
    }
}
