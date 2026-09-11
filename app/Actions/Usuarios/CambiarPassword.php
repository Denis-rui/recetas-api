<?php

namespace App\Actions\Usuarios;

use App\Models\User;

class CambiarPassword
{
    /**
     * Actualiza la contraseña del usuario.
     */
    public function ejecutar(User $usuario, string $nuevaPassword): User
    {
        $usuario->password = $nuevaPassword;
        $usuario->save();

        return $usuario;
    }
}

