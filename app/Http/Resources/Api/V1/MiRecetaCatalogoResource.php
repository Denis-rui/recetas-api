<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Receta;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Receta
 */
class MiRecetaCatalogoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $esEliminada = $this->trashed();
        $visibilidad = $esEliminada
            ? 'eliminada'
            : ($this->publicada_en !== null ? 'publicada' : 'privada');

        $solicitudPendiente = null;
        if ($this->relationLoaded('solicitudesRevision')) {
            $pendiente = $this->solicitudesRevision->firstWhere('estado', 'pendiente');
            if ($pendiente) {
                $solicitudPendiente = [
                    'id' => (int) $pendiente->id,
                    'tipo' => (string) $pendiente->tipo,
                    'estado' => (string) $pendiente->estado,
                    'created_at' => $pendiente->created_at?->toISOString(),
                ];
            }
        }

        $datos = [
            'id' => (int) $this->id,
            'nombre' => (string) $this->nombre,
            'descripcion' => (string) $this->descripcion,
            'imagen_url' => route('api.v1.mis-recetas.imagen', ['receta' => $this->id]),
            'porciones' => (int) $this->porciones,
            'tiempo_preparacion' => (int) $this->tiempo_preparacion,
            'categorias' => CategoriaResource::collection($this->whenLoaded('categorias')),
            'ingredientes_resumen' => $this->relationLoaded('ingredientes')
                ? $this->ingredientes->take(3)->map(fn ($ingrediente) => [
                    'ingrediente_id' => (int) $ingrediente->id,
                    'nombre' => (string) $ingrediente->nombre,
                ])->values()->all()
                : [],
            'cantidad_ingredientes' => (int) ($this->cantidad_ingredientes ?? $this->ingredientes_count ?? ($this->relationLoaded('ingredientes') ? $this->ingredientes->count() : 0)),
            'visibilidad' => $visibilidad,
            'version' => (int) $this->version,
            'solicitud_pendiente' => $solicitudPendiente,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'publicada_en' => $this->publicada_en?->toISOString(),
        ];

        if ($esEliminada) {
            $datos['deleted_at'] = $this->deleted_at?->toISOString();
            $datos['tipo_eliminacion'] = $this->tipo_eliminacion;
            $datos['motivo_eliminacion'] = $this->motivo_eliminacion;
        }

        return $datos;
    }
}
