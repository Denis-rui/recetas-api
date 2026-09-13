<?php

namespace App\Actions\Auth;

use App\Mail\CodigoRecuperacionMail;
use App\Models\RecuperacionPassword;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class SolicitarRecuperacionPassword
{
    public const MENSAJE_GENERICO = 'Si el correo corresponde a una cuenta habilitada, recibirás un código para restablecer tu contraseña.';

    /**
     * Procesa la solicitud de recuperación por código de 6 dígitos.
     * Si la cuenta existe y está activa:
     * - Invalida códigos anteriores.
     * - Genera código criptográfico de 6 dígitos (como string).
     * - Almacena el hash en la base de datos con vigencia de 10 minutos.
     * - Despacha el correo de forma encolada / asíncrona.
     *
     * Si la cuenta no existe o está inactiva:
     * - Ejecuta un hash dummy para balancear el tiempo de cómputo.
     * - No genera ni envía código alguno.
     * - Retorna el mismo mensaje genérico.
     */
    public function ejecutar(string $email): string
    {
        $emailNormalizado = strtolower(trim($email));
        $usuarioValido = false;
        $codigo = null;
        $emailDestino = null;
        $recuperacionId = null;

        DB::transaction(function () use ($emailNormalizado, &$usuarioValido, &$codigo, &$emailDestino, &$recuperacionId) {
            // 1. Bloqueo pesimista del usuario (orden canónico: User primero)
            $usuario = User::where('email', $emailNormalizado)
                ->lockForUpdate()
                ->first();

            if (! $usuario || ! $usuario->estaActivo() || strtolower((string) $usuario->email) !== $emailNormalizado) {
                return;
            }

            $usuarioValido = true;
            $emailDestino = $usuario->email;
            $codigo = sprintf('%06d', random_int(0, 999999));

            // 2. Bloqueo e invalidación de recuperaciones pendientes previas
            DB::table('recuperaciones_password')
                ->where(function ($query) use ($usuario, $emailNormalizado) {
                    $query->where('user_id', $usuario->id)
                        ->orWhere('email', $emailNormalizado);
                })
                ->whereNull('invalidado_en')
                ->update(['invalidado_en' => now()]);

            $recuperacion = RecuperacionPassword::create([
                'user_id' => $usuario->id,
                'email' => $emailNormalizado,
                'codigo_hash' => Hash::make($codigo),
                'intentos' => 0,
                'codigo_expira_en' => now()->addMinutes(10),
                'codigo_verificado_en' => null,
                'token_recuperacion_hash' => null,
                'token_expira_en' => null,
                'usado_en' => null,
                'invalidado_en' => null,
            ]);

            $recuperacionId = $recuperacion->id;
        });

        if ($usuarioValido && $codigo !== null && $emailDestino !== null && $recuperacionId !== null) {
            // Envío asíncrono encolado y cifrado
            Mail::to($emailDestino)->queue(new CodigoRecuperacionMail($codigo, $recuperacionId, 10));
        } else {
            // Compensación de tiempo para mitigar timing attacks
            Hash::make('timing_pad_recuperacion_segura');
        }

        return self::MENSAJE_GENERICO;
    }
}
