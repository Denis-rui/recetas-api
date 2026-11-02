<?php

namespace App\Actions\Usuarios;

use App\Models\User;
use Illuminate\Http\UploadedFile;

class CrearCuenta
{
    public function __construct(public GuardarFotoPerfil $guardarFotoPerfil) {}

    /**
     * Ejecuta la creación de una nueva cuenta.
     *
     * @param  array<string, mixed>  $datos
     */
    public function ejecutar(array $datos, ?UploadedFile $foto = null): User
    {
        if ($foto !== null) {
            $rutaFoto = $this->guardarFotoPerfil->ejecutar($foto);
            $datos['foto_perfil'] = $rutaFoto ?: null;
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
