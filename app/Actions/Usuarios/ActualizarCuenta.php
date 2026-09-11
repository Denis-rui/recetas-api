<?php

namespace App\Actions\Usuarios;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ActualizarCuenta
{
    /**
     * Actualiza los datos de una cuenta existente.
     *
     * @param  array<string, mixed>  $datos
     */
    public function ejecutar(User $usuario, array $datos, ?UploadedFile $foto = null): User
    {
        if ($foto !== null) {
            if ($usuario->foto_perfil && Storage::disk('public')->exists($usuario->foto_perfil)) {
                Storage::disk('public')->delete($usuario->foto_perfil);
            }
            $usuario->foto_perfil = $foto->store('perfiles', 'public');
        }

        $usuario->name = $datos['name'];
        $usuario->email = $datos['email'];
        $usuario->save();

        return $usuario;
    }
}
