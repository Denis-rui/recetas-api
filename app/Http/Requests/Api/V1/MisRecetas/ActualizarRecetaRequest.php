<?php

namespace App\Http\Requests\Api\V1\MisRecetas;

class ActualizarRecetaRequest extends GuardarRecetaRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $reglaImagen = $this->hasFile('imagen')
            ? ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048']
            : ['nullable', 'string', 'max:2048'];

        return [
            ...parent::rules(),
            'version' => ['required', 'integer', 'min:1'],
            'imagen' => $reglaImagen,
        ];
    }
}
