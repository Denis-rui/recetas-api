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
}
