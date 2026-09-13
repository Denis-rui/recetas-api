<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Ingrediente;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Ingrediente
 */
class IngredienteResource extends JsonResource
{
    /**
     * @return array{id: int, nombre: string}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
        ];
    }
}
