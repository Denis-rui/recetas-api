<?php

namespace App\Actions\Auth;

use App\Models\RecuperacionPassword;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RestablecerPasswordConToken
{
    /**
     * Restablece la contraseña mediante la autorización temporal (token_recuperacion).
     * En una operación atómica:
     * - Comprueba que la autorización sea válida, no vencida y no consumida.
     * - Comprueba que la cuenta continúe activa.
     * - Actualiza la contraseña del usuario.
     * - Invalida el acceso de «Recordarme» ($usuario->setRememberToken(null)).
     * - Revoca todos los tokens móviles de Sanctum.
     * - Elimina las sesiones web activas.
     * - Marca la autorización como consumida e invalida las demás recuperaciones pendientes.
     *
     * @throws ValidationException
     */
    public function ejecutar(string $tokenRecuperacion, string $nuevaPassword): User
    {
        $tokenHash = hash('sha256', $tokenRecuperacion);

        // Lectura preliminar ligera para localizar al usuario antes de adquirir bloqueos ordenados
        $previa = DB::table('recuperaciones_password')
            ->where('token_recuperacion_hash', $tokenHash)
            ->whereNull('usado_en')
            ->whereNull('invalidado_en')
            ->where('token_expira_en', '>', now())
            ->first(['id', 'user_id']);

        if (! $previa) {
            throw ValidationException::withMessages([
                'token_recuperacion' => 'La autorización de recuperación es inválida o ha vencido.',
            ]);
        }

        $resultado = DB::transaction(function () use ($previa, $tokenHash, $nuevaPassword) {
            // 1. Bloqueo pesimista del usuario primero (orden canónico)
            $usuario = User::where('id', $previa->user_id)->lockForUpdate()->first();

            if (! $usuario || ! $usuario->estaActivo()) {
                return ['exito' => false];
            }

            // 2. Bloqueo pesimista de la recuperación segundo
            $recuperacion = RecuperacionPassword::where('id', $previa->id)
                ->where('token_recuperacion_hash', $tokenHash)
                ->where('user_id', $usuario->id)
                ->whereNull('usado_en')
                ->whereNull('invalidado_en')
                ->where('token_expira_en', '>', now())
                ->lockForUpdate()
                ->first();

            if (! $recuperacion) {
                return ['exito' => false];
            }

            // Revalidar que la recuperación corresponda al correo actual de la cuenta activa
            if (strtolower((string) $usuario->email) !== strtolower((string) $recuperacion->email)) {
                $recuperacion->invalidado_en = now();
                $recuperacion->save();

                return ['exito' => false];
            }

            // 1. Actualizar contraseña y limpiar remember_token
            $usuario->password = $nuevaPassword;
            $usuario->setRememberToken(null);
            $usuario->save();

            // 2. Consumir la autorización actual
            $recuperacion->usado_en = now();
            $recuperacion->save();

            // 3. Invalidar cualquier otra recuperación pendiente de este usuario
            DB::table('recuperaciones_password')
                ->where('user_id', $usuario->id)
                ->where('id', '!=', $recuperacion->id)
                ->whereNull('invalidado_en')
                ->update(['invalidado_en' => now()]);

            // 4. Revocar todos los tokens móviles
            if (method_exists($usuario, 'tokens')) {
                $usuario->tokens()->delete();
            }

            // 5. Invalidar sesiones web activas
            DB::table('sessions')->where('user_id', $usuario->id)->delete();

            return ['exito' => true, 'usuario' => $usuario];
        });

        if (! $resultado['exito']) {
            throw ValidationException::withMessages([
                'token_recuperacion' => 'La autorización de recuperación es inválida o ha vencido.',
            ]);
        }

        return $resultado['usuario'];
    }
}
