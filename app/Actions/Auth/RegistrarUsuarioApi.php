<?php

namespace App\Actions\Auth;

use App\Models\User;

class RegistrarUsuarioApi
{
    /**
     * Registra un nuevo usuario para la aplicación móvil.
     * Garantiza que el rol sea siempre 'usuario' y el estado 'activo' = true,
     * ignorando cualquier campo administrativo enviado por el cliente.
     *
     * @param  array<string, mixed>  $datos  Datos validados (name, email, password)
     */
    public function ejecutar(array $datos): User
    {
        return User::create([
            'name' => $datos['name'],
            'email' => $datos['email'],
            'password' => $datos['password'],
            'rol' => 'usuario',
            'activo' => true,
        ]);
    }
}
