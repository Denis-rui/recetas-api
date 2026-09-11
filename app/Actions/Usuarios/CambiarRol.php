<?php

namespace App\Actions\Usuarios;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

class CambiarRol
{
    /**
     * Modifica el rol de un usuario y gestiona la revocación de accesos si deja de ser administrador.
     */
    public function ejecutar(User $autor, User $usuario, string $nuevoRol): User
    {
        Gate::forUser($autor)->authorize('cambiarRol', $usuario);

        if (! in_array($nuevoRol, ['administrador', 'usuario'], true)) {
            throw new InvalidArgumentException('El rol proporcionado no es válido.');
        }

        $usuario->rol = $nuevoRol;
        $usuario->save();

        // Si pierde el rol de Administrador, pierde el acceso a la plataforma web de inmediato (RN-10)
        if ($nuevoRol === 'usuario') {
            DB::table('sessions')->where('user_id', $usuario->id)->delete();
        }

        return $usuario;
    }
}

