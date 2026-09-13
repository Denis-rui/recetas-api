<?php

namespace App\Http\Requests\Api\V1\Perfil;

use Illuminate\Foundation\Http\FormRequest;

class ActualizarFotoPerfilApiRequest extends FormRequest
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
            'foto_perfil' => ['required', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'foto_perfil.required' => 'Debe adjuntar una imagen de fotografía de perfil.',
            'foto_perfil.image' => 'El archivo debe ser una imagen.',
            'foto_perfil.mimes' => 'La foto de perfil debe estar en formato JPEG, PNG, JPG o WEBP.',
            'foto_perfil.max' => 'La foto de perfil no debe superar los 2 MB de tamaño.',
        ];
    }
}

