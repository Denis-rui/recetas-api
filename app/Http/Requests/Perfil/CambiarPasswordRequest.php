<?php

namespace App\Http\Requests\Perfil;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CambiarPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->esAdministrador() && $this->user()->estaActivo();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string', 'current_password'],
            'password' => ['required', 'string', 'min:12', 'confirmed', 'different:current_password'],
            'password_confirmation' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'current_password.required' => 'Debe ingresar su contraseña actual.',
            'current_password.current_password' => 'La contraseña actual no es correcta.',
            'password.required' => 'La nueva contraseña es obligatoria.',
            'password.min' => 'La nueva contraseña debe tener un mínimo de 12 caracteres.',
            'password.confirmed' => 'La confirmación de la nueva contraseña no coincide.',
            'password.different' => 'La nueva contraseña debe ser diferente a la contraseña actual.',
            'password_confirmation.required' => 'Debe confirmar su nueva contraseña.',
        ];
    }
}
