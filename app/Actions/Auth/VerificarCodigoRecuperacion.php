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

        DB::beginTransaction();

        try {
            $recuperacion = RecuperacionPassword::where('email', $emailNormalizado)
                ->whereNull('codigo_verificado_en')
                ->whereNull('usado_en')
                ->whereNull('invalidado_en')
                ->where('codigo_expira_en', '>', now())
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if (! $recuperacion) {
                DB::commit();
                Hash::check('000000', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

                throw ValidationException::withMessages([
                    'codigo' => 'El código de recuperación es inválido o ha vencido.',
                ]);
            }

            $usuario = User::where('id', $recuperacion->user_id)->first();
            if (! $usuario || ! $usuario->estaActivo()) {
                $recuperacion->invalidado_en = now();
                $recuperacion->save();
                DB::commit();

                throw ValidationException::withMessages([
                    'codigo' => 'El código de recuperación es inválido o ha vencido.',
                ]);
            }

            if ($recuperacion->intentos >= 5) {
                $recuperacion->invalidado_en = now();
                $recuperacion->save();
                DB::commit();

                throw ValidationException::withMessages([
                    'codigo' => 'El código de recuperación es inválido o ha vencido.',
                ]);
            }

            if (! Hash::check($codigo, $recuperacion->codigo_hash)) {
                $recuperacion->intentos += 1;
                if ($recuperacion->intentos >= 5) {
                    $recuperacion->invalidado_en = now();
                }
                $recuperacion->save();
                DB::commit(); // El intento fallido se persiste antes de lanzar la excepción

                throw ValidationException::withMessages([
                    'codigo' => 'El código de recuperación es inválido o ha vencido.',
                ]);
            }

            // Código válido: generar token_recuperacion de alta entropía
            $tokenPlano = Str::random(64);
            $tokenHash = hash('sha256', $tokenPlano);

            $recuperacion->codigo_verificado_en = now();
            $recuperacion->token_recuperacion_hash = $tokenHash;
            $recuperacion->token_expira_en = now()->addMinutes(15);
            $recuperacion->save();

            DB::commit();

            return $tokenPlano;
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            throw $e;
        }
    }
}
