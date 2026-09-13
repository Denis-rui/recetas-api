<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Receta;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Receta
 */
class FavoritoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $esEliminada = $this->trashed();
        $agregadoEn = $this->pivot?->created_at?->toISOString();

        if ($esEliminada) {
            $mensaje = $this->tipo_eliminacion === 'autor'
                ? 'Esta receta fue eliminada por su autor'
                : 'Esta receta fue eliminada';

            return [
                'id' => (int) $this->id,
                'nombre' => (string) $this->nombre,
                'visibilidad' => 'eliminada',
                'mensaje' => $mensaje,
                'agregado_en' => $agregadoEn,
            ];
        }

        return [
            'id' => (int) $this->id,
            'nombre' => (string) $this->nombre,
            'descripcion' => (string) $this->descripcion,
            'imagen_url' => route('api.v1.recetas.imagen', ['receta' => $this->id]),
            'porciones' => (int) $this->porciones,
            'tiempo_preparacion' => (int) $this->tiempo_preparacion,
            'visibilidad' => 'publicada',
            'categorias' => CategoriaResource::collection($this->whenLoaded('categorias')),
            'valoracion_promedio' => $this->valoracion_promedio !== null
                ? round((float) $this->valoracion_promedio, 2)
                : null,
            'cantidad_valoraciones' => (int) ($this->cantidad_valoraciones ?? 0),
            'ingredientes_resumen' => $this->relationLoaded('ingredientes')
                ? $this->ingredientes->take(3)->map(fn ($ingrediente) => [
                    'ingrediente_id' => (int) $ingrediente->id,
                    'nombre' => (string) $ingrediente->nombre,
                ])->values()->all()
                : [],
            'cantidad_ingredientes' => (int) ($this->cantidad_ingredientes ?? $this->ingredientes_count ?? ($this->relationLoaded('ingredientes') ? $this->ingredientes->count() : 0)),
            'agregado_en' => $agregadoEn,
        ];
    }
}

