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

        $usuario = User::where('email', $emailNormalizado)->first();

        if ($usuario && $usuario->estaActivo()) {
            $codigo = sprintf('%06d', random_int(0, 999999));

            DB::transaction(function () use ($usuario, $emailNormalizado, $codigo) {
                DB::table('recuperaciones_password')
                    ->where(function ($query) use ($usuario, $emailNormalizado) {
                        $query->where('user_id', $usuario->id)
                            ->orWhere('email', $emailNormalizado);
                    })
                    ->whereNull('invalidado_en')
                    ->update(['invalidado_en' => now()]);

                RecuperacionPassword::create([
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
            });

            // Envío asíncrono encolado (ShouldQueue) para evitar latencias de red en la respuesta HTTP
            Mail::to($usuario->email)->queue(new CodigoRecuperacionMail($codigo, 10));
        } else {
            // Compensación de tiempo para mitigar timing attacks
            Hash::make('timing_pad_recuperacion_segura');
        }

        return self::MENSAJE_GENERICO;
    }
}
