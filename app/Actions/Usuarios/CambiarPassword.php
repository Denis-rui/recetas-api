<?php

namespace App\Actions\Usuarios;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class CambiarPassword
{
    /**
     * Actualiza la contraseña del usuario y revoca de inmediato:
     * - Todos los tokens móviles de Sanctum.
     * - Todas las sesiones web existentes.
     * - El acceso persistente de «Recordarme» (remember_token).
     * - Todas las recuperaciones de contraseña pendientes.
     */
    public function ejecutar(User $usuario, string $nuevaPassword): User
    {
        return DB::transaction(function () use ($usuario, $nuevaPassword) {
            $usuario->password = $nuevaPassword;
            $usuario->setRememberToken(null);
            $usuario->save();

            if (method_exists($usuario, 'tokens')) {
                $usuario->tokens()->delete();
            }

            DB::table('sessions')->where('user_id', $usuario->id)->delete();

            DB::table('recuperaciones_password')
                ->where('user_id', $usuario->id)
                ->whereNull('invalidado_en')
                ->update(['invalidado_en' => now()]);

            return $usuario;
        });
    }
}
