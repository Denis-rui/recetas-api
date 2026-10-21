<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Categoria;
use App\Models\Ingrediente;
use App\Models\SolicitudRevision;
use App\Models\Subcategoria;
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

        // Resolver nombres de categoría, subcategoría e ingredientes contenidos en la propuesta
        $categoriaId = $contenido['categoria_id'] ?? null;
        $subcategoriaId = $contenido['subcategoria_id'] ?? null;
        $ingredientesIds = array_column($contenido['ingredientes'] ?? [], 'ingrediente_id');

        $categoria = $categoriaId !== null
            ? Categoria::query()->select(['id', 'nombre'])->find((int) $categoriaId)
            : null;

        $subcategoria = $subcategoriaId !== null
            ? Subcategoria::query()->select(['id', 'nombre'])->find((int) $subcategoriaId)
            : null;

        $nombresIngredientes = ! empty($ingredientesIds)
            ? Ingrediente::whereIn('id', $ingredientesIds)->pluck('nombre', 'id')->all()
            : [];

        $categoriaEnriquecida = $categoriaId !== null ? [
            'id' => (int) $categoriaId,
            'nombre' => $categoria?->nombre ?? "Categoría #{$categoriaId}",
        ] : null;

        $subcategoriaEnriquecida = $subcategoriaId !== null ? [
            'id' => (int) $subcategoriaId,
            'nombre' => $subcategoria?->nombre ?? "Subcategoría #{$subcategoriaId}",
        ] : null;

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
            'categoria' => $categoriaEnriquecida,
            'subcategoria' => $subcategoriaEnriquecida,
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
