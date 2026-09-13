<?php

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class IniciarSesionApi
{
    /**
     * @return array{usuario: User, token: string}|null
     */
    public function ejecutar(string $email, string $password, string $dispositivo): ?array
    {
        $email = strtolower(trim($email));
        $previo = User::query()->where('email', $email)->first();

        if (! $previo || ! Hash::check($password, $previo->password)) {
            return null;
        }

        return DB::transaction(function () use ($previo, $email, $dispositivo): ?array {
            $usuario = User::query()->whereKey($previo->id)->lockForUpdate()->first();

            // La comprobación costosa se hizo fuera del bloqueo; solo sirve si el hash sigue vigente.
            if (! $usuario || ! $usuario->estaActivo()
                || strtolower($usuario->email) !== $email
                || ! hash_equals($previo->password, $usuario->password)) {
                return null;
            }

            return [
                'usuario' => $usuario,
                'token' => $usuario->createToken($dispositivo)->plainTextToken,
            ];
        });
    }
}
