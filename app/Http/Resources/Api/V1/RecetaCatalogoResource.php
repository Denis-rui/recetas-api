<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Receta;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Receta
 */
class RecetaCatalogoResource extends JsonResource
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
            'cantidad_ingredientes' => (int) ($this->cantidad_ingredientes ?? $this->ingredientes_count ?? 0),
            $this->mergeWhen($this->relationLoaded('ingredientesDisponibles'), fn () => [
                'ingredientes_disponibles' => $this->ingredientesDisponibles->map(fn ($ingrediente) => [
                    'ingrediente_id' => (int) $ingrediente->id,
                    'nombre' => (string) $ingrediente->nombre,
                ])->all(),
                'ingredientes_faltantes' => $this->ingredientesFaltantes->map(fn ($ingrediente) => [
                    'ingrediente_id' => (int) $ingrediente->id,
                    'nombre' => (string) $ingrediente->nombre,
                ])->all(),
                'cantidad_coincidencias' => (int) $this->cantidad_coincidencias,
                'cantidad_faltantes' => (int) $this->cantidad_faltantes,
            ]),
        ];
    }
}
