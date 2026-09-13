<?php

namespace App\Actions\Usuarios;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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
            // Bloqueo pesimista del usuario primero (orden canónico)
            $userRecord = User::where('id', $usuario->id)->lockForUpdate()->first();
            if (! $userRecord || ! $userRecord->estaActivo()
                || ! hash_equals($usuario->password, $userRecord->password)) {
                throw ValidationException::withMessages([
                    'current_password' => 'Las credenciales cambiaron o la cuenta está inactiva. Inicie sesión nuevamente.',
                ]);
            }

            $userRecord->password = $nuevaPassword;
            $userRecord->setRememberToken(null);
            $userRecord->save();

            $usuario->password = $userRecord->password;
            $usuario->setRememberToken(null);

            if (method_exists($userRecord, 'tokens')) {
                $userRecord->tokens()->delete();
            }

            DB::table('sessions')->where('user_id', $userRecord->id)->delete();

            DB::table('recuperaciones_password')
                ->where('user_id', $userRecord->id)
                ->whereNull('invalidado_en')
                ->update(['invalidado_en' => now()]);

            return $usuario;
        });
    }
}
