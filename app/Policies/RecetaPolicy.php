<?php

namespace App\Policies;

use App\Models\Receta;
use App\Models\User;

class RecetaPolicy
{
    /**
     * Determina si el usuario puede ver su listado de recetas propias.
     */
    public function viewAny(User $user): bool
    {
        return $user->estaActivo();
    }

    /**
     * Determina si el usuario puede ver el detalle de una receta propia.
     */
    public function view(User $user, Receta $receta): bool
    {
        return $user->estaActivo() && $receta->creado_por === $user->id;
    }

    /**
     * Determina si el usuario puede crear una receta privada en su cuenta.
     */
    public function create(User $user): bool
    {
        return $user->estaActivo();
    }

    /**
     * Determina si el usuario puede editar su receta privada.
     * Una receta publicada o eliminada no puede editarse directamente mediante esta política.
     */
    public function update(User $user, Receta $receta): bool
    {
        return $user->estaActivo()
            && $receta->creado_por === $user->id
            && ! $receta->trashed()
            && $receta->publicada_en === null;
    }

    /**
     * Determina si el usuario puede eliminar su receta propia (privada o publicada).
     */
    public function delete(User $user, Receta $receta): bool
    {
        return $user->estaActivo()
            && $receta->creado_por === $user->id
            && ! $receta->trashed();
    }

    /**
     * Determina si el usuario puede solicitar publicación (o publicar directamente) su receta privada.
     */
    public function publicar(User $user, Receta $receta): bool
    {
        return $user->estaActivo()
            && $receta->creado_por === $user->id
            && ! $receta->trashed()
            && $receta->publicada_en === null;
    }

    /**
     * Determina si el usuario puede proponer o aplicar correcciones a su receta publicada.
     */
    public function corregir(User $user, Receta $receta): bool
    {
        return $user->estaActivo()
            && $receta->creado_por === $user->id
            && ! $receta->trashed()
            && $receta->publicada_en !== null;
    }

    /**
     * Determina si el usuario puede subir o reemplazar una imagen para su receta propia.
     */
    public function subirImagen(User $user, Receta $receta): bool
    {
        return $user->estaActivo()
            && $receta->creado_por === $user->id
            && ! $receta->trashed();
    }
}
