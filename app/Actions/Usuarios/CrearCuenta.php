<?php

namespace App\Actions\Usuarios;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class CrearCuenta
{
    /**
     * Ejecuta la creación de una nueva cuenta.
     *
     * @param  array<string, mixed>  $datos
     */
    public function ejecutar(array $datos, ?UploadedFile $foto = null): User
    {
        if ($foto !== null) {
            $rutaFoto = $foto->store('perfiles', 'public');
            $datos['foto_perfil'] = $rutaFoto;
        }

        // Regla RN-03 y RN-04: Rol por defecto Administrador si no se especifica, y estado Activo.
        $rol = $datos['rol'] ?? 'administrador';

        return User::create([
            'name' => $datos['name'],
            'email' => $datos['email'],
            'password' => $datos['password'],
            'foto_perfil' => $datos['foto_perfil'] ?? null,
            'rol' => $rol,
            'activo' => true,
        ]);
    }
}

