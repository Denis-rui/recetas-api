<?php

namespace App\Policies;

use App\Models\SolicitudRevision;
use App\Models\User;

class SolicitudRevisionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->esAdministrador() && $user->estaActivo();
    }

    public function view(User $user, SolicitudRevision $solicitud): bool
    {
        return $this->viewAny($user) && $solicitud->receta !== null
            && $solicitud->solicitado_por === $solicitud->receta->creado_por;
    }

    public function decidir(User $user, SolicitudRevision $solicitud): bool
    {
        return $this->view($user, $solicitud);
    }

    /**
     * Determina si el usuario autor puede ver su propia solicitud de revisión.
     */
    public function viewOwn(User $user, SolicitudRevision $solicitud): bool
    {
        return $user->estaActivo() && $solicitud->solicitado_por === $user->id;
    }

    /**
     * Determina si el usuario autor puede cancelar su propia solicitud pendiente.
     */
    public function cancelar(User $user, SolicitudRevision $solicitud): bool
    {
        return $user->estaActivo()
            && $solicitud->solicitado_por === $user->id
            && $solicitud->estado === 'pendiente';
    }
}
