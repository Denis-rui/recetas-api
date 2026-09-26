<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Categoria;
use App\Models\Ingrediente;
use App\Models\SolicitudRevision;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SolicitudRevision
 */
class MiSolicitudDetalleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $contenido = $this->contenido ?? [];

        // Resolver nombres de categorías e ingredientes contenidos en la propuesta
        $categoriasIds = $contenido['categorias'] ?? [];
        $ingredientesIds = array_column($contenido['ingredientes'] ?? [], 'ingrediente_id');

        $nombresCategorias = ! empty($categoriasIds)
            ? Categoria::whereIn('id', $categoriasIds)->pluck('nombre', 'id')->all()
            : [];

        $nombresIngredientes = ! empty($ingredientesIds)
            ? Ingrediente::whereIn('id', $ingredientesIds)->pluck('nombre', 'id')->all()
            : [];

        $categoriasEnriquecidas = array_map(fn ($catId) => [
            'id' => (int) $catId,
            'nombre' => $nombresCategorias[$catId] ?? "Categoría #{$catId}",
        ], $categoriasIds);

        $ingredientesEnriquecidos = array_map(fn ($item) => [
            'ingrediente_id' => (int) $item['ingrediente_id'],
            'nombre' => $nombresIngredientes[$item['ingrediente_id']] ?? "Ingrediente #{$item['ingrediente_id']}",
            'cantidad' => $item['cantidad'] !== null ? round((float) $item['cantidad'], 3) : null,
            'unidad' => $item['unidad'] ?? null,
            'notas' => $item['notas'] ?? null,
            'orden' => (int) ($item['orden'] ?? 1),
        ], $contenido['ingredientes'] ?? []);

        $contenidoFormateado = [
            'nombre' => $contenido['nombre'] ?? '',
            'descripcion' => $contenido['descripcion'] ?? '',
            'porciones' => (int) ($contenido['porciones'] ?? 0),
            'tiempo_preparacion' => (int) ($contenido['tiempo_preparacion'] ?? 0),
            'tips' => $contenido['tips'] ?? null,
            'imagen_url' => route('api.v1.mis-solicitudes.imagen', ['solicitud' => $this->id]),
            'categorias' => $categoriasEnriquecidas,
            'ingredientes' => $ingredientesEnriquecidos,
            'pasos' => $contenido['pasos'] ?? [],
        ];

        return [
            'id' => (int) $this->id,
            'receta_id' => (int) $this->receta_id,
            'receta_nombre' => $this->receta?->nombre ?? (string) ($contenido['nombre'] ?? ''),
            'tipo' => (string) $this->tipo,
            'estado' => (string) $this->estado,
            'version_base' => (int) $this->version_base,
            'clave_idempotencia' => (string) $this->clave_idempotencia,
            'contenido' => $contenidoFormateado,
            'motivo_rechazo' => $this->motivo_rechazo,
            'created_at' => $this->created_at?->toISOString(),
            'revisada_en' => $this->revisada_en?->toISOString(),
            'cancelada_en' => $this->cancelada_en?->toISOString(),
        ];
    }
}
