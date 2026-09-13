<?php

namespace App\Actions\Auth;

use App\Models\RecuperacionPassword;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class VerificarCodigoRecuperacion
{
    /**
     * Verifica el código de recuperación en una transacción atómica con bloqueo pesimista.
     * Si es válido:
     * - Comprueba que la cuenta continúe activa.
     * - Marca el código como verificado.
     * - Genera una autorización temporal (token_recuperacion) de 64 caracteres con vigencia de 15 minutos.
     * - Retorna el token en texto plano (en la base de datos solo se guarda su hash sha256).
     *
     * Si falla:
     * - Incrementa el contador de intentos de forma atómica y comitea el intento fallido.
     * - Si alcanza 5 intentos, invalida el código.
     * - Lanza ValidationException con mensaje genérico.
     *
     * @throws ValidationException
     */
    public function ejecutar(string $email, string $codigo): string
    {
        $emailNormalizado = strtolower(trim($email));

        $resultado = DB::transaction(function () use ($emailNormalizado, $codigo) {
            // 1. Bloqueo pesimista del usuario primero (orden canónico)
            $usuario = User::where('email', $emailNormalizado)
                ->lockForUpdate()
                ->first();

            if (! $usuario || ! $usuario->estaActivo() || strtolower((string) $usuario->email) !== $emailNormalizado) {
                // Si la cuenta ya no existe con ese correo o está inactiva, invalidar recuperaciones pendientes de este correo
                DB::table('recuperaciones_password')
                    ->where('email', $emailNormalizado)
                    ->whereNull('invalidado_en')
                    ->update(['invalidado_en' => now()]);

                return ['exito' => false, 'tipo' => 'inexistente'];
            }

            // 2. Bloqueo pesimista de la recuperación segundo
            $recuperacion = RecuperacionPassword::where('user_id', $usuario->id)
                ->where('email', $emailNormalizado)
                ->whereNull('codigo_verificado_en')
                ->whereNull('usado_en')
                ->whereNull('invalidado_en')
                ->where('codigo_expira_en', '>', now())
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if (! $recuperacion) {
                return ['exito' => false, 'tipo' => 'inexistente'];
            }

            // Validar que el correo actual del usuario coincida con el de la recuperación
            if (strtolower((string) $recuperacion->email) !== strtolower((string) $usuario->email)) {
                $recuperacion->invalidado_en = now();
                $recuperacion->save();

                return ['exito' => false, 'tipo' => 'invalido'];
            }

            // Si ya alcanzó 5 intentos fallidos, asegurar invalidación
            if ($recuperacion->intentos >= 5) {
                $recuperacion->invalidado_en = now();
                $recuperacion->save();

                return ['exito' => false, 'tipo' => 'bloqueado'];
            }

            // Comprobar coincidencia del código
            if (! Hash::check($codigo, $recuperacion->codigo_hash)) {
                $recuperacion->intentos += 1;
                if ($recuperacion->intentos >= 5) {
                    $recuperacion->invalidado_en = now();
                }
                $recuperacion->save();

                return ['exito' => false, 'tipo' => 'codigo_incorrecto'];
            }

            // Código válido: generar token_recuperacion de alta entropía (64 caracteres)
            $tokenPlano = Str::random(64);
            $tokenHash = hash('sha256', $tokenPlano);

            $recuperacion->codigo_verificado_en = now();
            $recuperacion->token_recuperacion_hash = $tokenHash;
            $recuperacion->token_expira_en = now()->addMinutes(15);
            $recuperacion->save();

            return ['exito' => true, 'token' => $tokenPlano];
        });

        if (! $resultado['exito']) {
            if (($resultado['tipo'] ?? '') === 'inexistente') {
                Hash::check('000000', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');
            }

            throw ValidationException::withMessages([
                'codigo' => 'El código de recuperación es inválido o ha vencido.',
            ]);
        }

        return $resultado['token'];
    }
}
