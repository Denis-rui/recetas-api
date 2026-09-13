<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MiValoracionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'receta_id' => (int) $this->resource['receta_id'],
            'puntuacion' => $this->resource['puntuacion'] !== null ? (int) $this->resource['puntuacion'] : null,
            'valoracion_promedio' => $this->resource['valoracion_promedio'] !== null
                ? round((float) $this->resource['valoracion_promedio'], 2)
                : null,
            'cantidad_valoraciones' => (int) ($this->resource['cantidad_valoraciones'] ?? 0),
        ];
    }
}

