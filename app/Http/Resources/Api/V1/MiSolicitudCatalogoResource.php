<?php

namespace App\Http\Resources\Api\V1;

use App\Models\SolicitudRevision;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SolicitudRevision
 */
class MiSolicitudCatalogoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'receta_id' => (int) $this->receta_id,
            'receta_nombre' => $this->receta?->nombre ?? (string) data_get($this->contenido, 'nombre', ''),
            'tipo' => (string) $this->tipo,
            'estado' => (string) $this->estado,
            'version_base' => (int) $this->version_base,
            'clave_idempotencia' => (string) $this->clave_idempotencia,
            'created_at' => $this->created_at?->toISOString(),
            'revisada_en' => $this->revisada_en?->toISOString(),
            'cancelada_en' => $this->cancelada_en?->toISOString(),
            'motivo_rechazo' => $this->motivo_rechazo,
        ];
    }
}

