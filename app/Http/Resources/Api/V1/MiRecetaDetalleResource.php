<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Receta;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Receta
 */
class MiRecetaDetalleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $esEliminada = $this->trashed();

        // Para recetas eliminadas, según Opción A confirmada, solo se exponen metadatos de eliminación
        if ($esEliminada) {
            return [
                'id' => (int) $this->id,
                'nombre' => (string) $this->nombre,
                'visibilidad' => 'eliminada',
                'version' => (int) $this->version,
                'deleted_at' => $this->deleted_at?->toISOString(),
                'tipo_eliminacion' => $this->tipo_eliminacion,
                'motivo_eliminacion' => $this->motivo_eliminacion,
                'created_at' => $this->created_at?->toISOString(),
                'updated_at' => $this->updated_at?->toISOString(),
            ];
        }

        $visibilidad = $this->publicada_en !== null ? 'publicada' : 'privada';

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

        return [
            'id' => (int) $this->id,
            'nombre' => (string) $this->nombre,
            'descripcion' => (string) $this->descripcion,
            'imagen_url' => route('api.v1.mis-recetas.imagen', ['receta' => $this->id]),
            'porciones' => (int) $this->porciones,
            'tiempo_preparacion' => (int) $this->tiempo_preparacion,
            'tips' => $this->tips,
            'visibilidad' => $visibilidad,
            'version' => (int) $this->version,
            'solicitud_pendiente' => $solicitudPendiente,
            'categorias' => CategoriaResource::collection($this->whenLoaded('categorias')),
            'ingredientes' => $this->relationLoaded('ingredientes')
                ? $this->ingredientes->sortBy(fn ($i) => $i->pivot->orden)->values()->map(fn ($ingrediente) => [
                    'ingrediente_id' => (int) $ingrediente->id,
                    'nombre' => (string) $ingrediente->nombre,
                    'cantidad' => $ingrediente->pivot->cantidad !== null
                        ? round((float) $ingrediente->pivot->cantidad, 3)
                        : null,
                    'unidad' => $ingrediente->pivot->unidad,
                    'notas' => $ingrediente->pivot->notas,
                    'orden' => (int) $ingrediente->pivot->orden,
                ])->all()
                : [],
            'pasos' => $this->relationLoaded('pasos')
                ? $this->pasos->sortBy('orden')->values()->map(fn ($paso) => [
                    'orden' => (int) $paso->orden,
                    'instruccion' => (string) $paso->instruccion,
                ])->all()
                : [],
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'publicada_en' => $this->publicada_en?->toISOString(),
        ];
    }
}

