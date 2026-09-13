<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Receta;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Receta
 */
class RecetaDetalleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'descripcion' => $this->descripcion,
            'imagen_url' => route('api.v1.recetas.imagen', ['receta' => $this->id]),
            'porciones' => (int) $this->porciones,
            'tiempo_preparacion' => (int) $this->tiempo_preparacion,
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
            'tips' => $this->tips,
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
        ];
    }
}
