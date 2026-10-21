<?php

namespace App\Policies;

use App\Models\Subcategoria;
use App\Models\User;

class SubcategoriaPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->esAdministradorActivo($user);
    }

    public function create(User $user): bool
    {
        return $this->esAdministradorActivo($user);
    }

    public function update(User $user, Subcategoria $subcategoria): bool
    {
        return $this->esAdministradorActivo($user);
    }

    public function delete(User $user, Subcategoria $subcategoria): bool
    {
        return $this->esAdministradorActivo($user);
    }

    private function esAdministradorActivo(User $user): bool
    {
        return $user->esAdministrador() && $user->estaActivo();
    }
}
