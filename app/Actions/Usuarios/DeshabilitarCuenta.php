<?php

namespace App\Actions\Usuarios;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class DeshabilitarCuenta
{
    /**
     * Deshabilita una cuenta de usuario y revoca de inmediato todas sus sesiones,
     * tokens activos y el acceso persistente de «Recordarme».
     */
    public function ejecutar(User $autor, User $usuario): User
    {
        Gate::forUser($autor)->authorize('deshabilitar', $usuario);

        return DB::transaction(function () use ($usuario) {
            $usuario->activo = false;
            $usuario->setRememberToken(null);
            $usuario->save();

            // Regla RN-08 y Criterio 8: La cuenta pierde el acceso de sus sesiones existentes
            DB::table('sessions')->where('user_id', $usuario->id)->delete();

            if (method_exists($usuario, 'tokens')) {
                $usuario->tokens()->delete();
            }

            DB::table('recuperaciones_password')
                ->where('user_id', $usuario->id)
                ->whereNull('invalidado_en')
                ->update(['invalidado_en' => now()]);

            return $usuario;
        });
    }
}
