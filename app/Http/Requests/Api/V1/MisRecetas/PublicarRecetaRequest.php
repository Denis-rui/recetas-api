<?php

namespace App\Http\Requests\Api\V1\MisRecetas;

use Illuminate\Foundation\Http\FormRequest;

class PublicarRecetaRequest extends FormRequest
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
            'clave_idempotencia' => ['required', 'uuid'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'clave_idempotencia.required' => 'La clave de idempotencia es obligatoria para evitar solicitudes duplicadas.',
            'clave_idempotencia.uuid' => 'La clave de idempotencia debe tener formato UUID válido.',
        ];
    }
}

