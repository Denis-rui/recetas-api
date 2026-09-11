<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Determina si el usuario autenticado puede ver el listado de cuentas.
     */
    public function viewAny(User $auth): bool
    {
        return $auth->esAdministrador() && $auth->estaActivo();
    }

    /**
     * Determina si el usuario autenticado puede ver una cuenta específica.
     */
    public function view(User $auth, User $user): bool
    {
        return $auth->esAdministrador() && $auth->estaActivo();
    }

    /**
     * Determina si el usuario autenticado puede crear cuentas.
     */
    public function create(User $auth): bool
    {
        return $auth->esAdministrador() && $auth->estaActivo();
    }

    /**
     * Determina si el usuario autenticado puede actualizar los datos de una cuenta.
     */
    public function update(User $auth, User $user): bool
    {
        return $auth->esAdministrador() && $auth->estaActivo();
    }

    /**
     * Determina si el usuario autenticado puede cambiar el rol de otra cuenta.
     * Regla RN-06 y RN-07: Ningún administrador podrá cambiar su propio rol.
     */
    public function cambiarRol(User $auth, User $user): bool
    {
        return $auth->esAdministrador()
            && $auth->estaActivo()
            && $auth->id !== $user->id;
    }

    /**
     * Determina si el usuario autenticado puede deshabilitar otra cuenta.
     * Regla RN-06 y RN-07: Ningún administrador podrá deshabilitar su propia cuenta.
     */
    public function deshabilitar(User $auth, User $user): bool
    {
        return $auth->esAdministrador()
            && $auth->estaActivo()
            && $auth->id !== $user->id
            && $user->estaActivo();
    }

    /**
     * Determina si el usuario autenticado puede reactivar una cuenta deshabilitada.
     */
    public function reactivar(User $auth, User $user): bool
    {
        return $auth->esAdministrador()
            && $auth->estaActivo()
            && ! $user->estaActivo();
    }
}
