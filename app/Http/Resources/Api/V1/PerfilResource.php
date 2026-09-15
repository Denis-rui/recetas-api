<?php

namespace App\Http\Resources\Api\V1;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class PerfilResource extends JsonResource
{
    /**
     * Transforma el modelo User a una respuesta JSON con campos explícitos para la aplicación móvil.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'foto_perfil_url' => $this->foto_perfil ? route('api.v1.perfil.foto') : null,
            'rol' => $this->rol,
            'activo' => (bool) $this->activo,
        ];
    }
}
