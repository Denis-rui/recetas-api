<?php

namespace App\Policies;

use App\Models\Categoria;
use App\Models\User;

class CategoriaPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->esAdministradorActivo($user);
    }

    public function create(User $user): bool
    {
        return $this->esAdministradorActivo($user);
    }

    public function update(User $user, Categoria $categoria): bool
    {
        return $this->esAdministradorActivo($user);
    }

    public function delete(User $user, Categoria $categoria): bool
    {
        return $this->esAdministradorActivo($user);
    }

    private function esAdministradorActivo(User $user): bool
    {
        return $user->esAdministrador() && $user->estaActivo();
    }
}
