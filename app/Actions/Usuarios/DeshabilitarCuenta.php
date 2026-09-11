<?php

namespace App\Actions\Usuarios;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class DeshabilitarCuenta
{
    /**
     * Deshabilita una cuenta de usuario y revoca de inmediato todas sus sesiones y tokens activos.
     */
    public function ejecutar(User $autor, User $usuario): User
    {
        Gate::forUser($autor)->authorize('deshabilitar', $usuario);

        $usuario->activo = false;
        $usuario->save();

        // Regla RN-08 y Criterio 8: La cuenta pierde el acceso de sus sesiones existentes
        DB::table('sessions')->where('user_id', $usuario->id)->delete();

        if (method_exists($usuario, 'tokens')) {
            $usuario->tokens()->delete();
        }

        return $usuario;
    }
}

