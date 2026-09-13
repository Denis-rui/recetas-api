<?php

namespace App\Http\Requests\Api\V1\Perfil;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ActualizarPerfilApiRequest extends FormRequest
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
        $userId = $this->user()?->id;
        $requierePassword = $this->filled('email') && $this->input('email') !== $this->user()?->email;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($userId),
            ],
            'current_password' => $requierePassword
                ? ['required', 'string', 'current_password']
                : ['sometimes', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'El nombre completo es obligatorio cuando se incluye.',
            'name.max' => 'El nombre no puede superar los 255 caracteres.',
            'email.required' => 'El correo electrónico es obligatorio cuando se incluye.',
            'email.email' => 'Debe ingresar un formato de correo electrónico válido.',
            'email.unique' => 'Este correo electrónico ya pertenece a otra cuenta.',
            'current_password.required' => 'Para modificar el correo electrónico debe ingresar su contraseña actual.',
            'current_password.current_password' => 'La contraseña actual ingresada es incorrecta.',
        ];
    }
}
