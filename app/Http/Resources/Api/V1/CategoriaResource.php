<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Categoria;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Categoria
 */
class CategoriaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'nombre' => (string) $this->nombre,
            'subcategorias' => $this->whenLoaded('subcategorias', fn () => $this->subcategorias->map(fn ($subcategoria): array => [
                'id' => (int) $subcategoria->id,
                'nombre' => (string) $subcategoria->nombre,
            ])->values()->all()),
        ];
    }
}
