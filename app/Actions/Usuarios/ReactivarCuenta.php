<?php

namespace App\Actions\Usuarios;

use App\Models\User;
use Illuminate\Support\Facades\Gate;

class ReactivarCuenta
{
    /**
     * Reactiva una cuenta deshabilitada.
     */
    public function ejecutar(User $autor, User $usuario): User
    {
        Gate::forUser($autor)->authorize('reactivar', $usuario);

        $usuario->activo = true;
        $usuario->save();

        return $usuario;
    }
}

