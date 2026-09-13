<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Recetas\ImagenRevision;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListarRecetasRequest;
use App\Http\Resources\Api\V1\RecetaCatalogoResource;
use App\Http\Resources\Api\V1\RecetaDetalleResource;
use App\Models\Ingrediente;
use App\Models\Receta;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CatalogoRecetaController extends Controller
{
    private const CARACTER_ESCAPE = '!';

    public function index(ListarRecetasRequest $request): AnonymousResourceCollection
    {
        $query = Receta::query()
            ->publicada()
            ->withAvg('valoraciones as valoracion_promedio', 'puntuacion')
            ->withCount('valoraciones as cantidad_valoraciones')
            ->withCount('ingredientes as cantidad_ingredientes')
            ->with([
                'categorias:id,nombre',
                'ingredientes' => fn ($subQuery) => $subQuery
                    ->select('ingredientes.id', 'ingredientes.nombre')
                    ->withPivot(['orden'])
                    ->orderByPivot('orden'),
            ]);

        $buscar = $request->validated('buscar');
        if ($buscar !== null && $buscar !== '') {
            $busquedaEscapada = $this->escaparComodinesLike($buscar, self::CARACTER_ESCAPE);
            $terminoLike = "%{$busquedaEscapada}%";
            $query->whereRaw("nombre LIKE ? ESCAPE '!'", [$terminoLike]);
        }

        $categoriaId = $request->validated('categoria_id');
        if ($categoriaId !== null) {
            $query->whereHas('categorias', fn ($subQuery) => $subQuery->where('categorias.id', $categoriaId));
        }

        $ingredientesSeleccionados = array_map('intval', $request->validated('ingredientes', []));
        if ($ingredientesSeleccionados !== []) {
            $query->whereHas('ingredientes', fn ($subQuery) => $subQuery->whereIn('ingredientes.id', $ingredientesSeleccionados))
                ->withCount([
                    'ingredientes as cantidad_coincidencias' => fn ($subQuery) => $subQuery->whereIn('ingredientes.id', $ingredientesSeleccionados),
                    'ingredientes as cantidad_faltantes' => fn ($subQuery) => $subQuery->whereNotIn('ingredientes.id', $ingredientesSeleccionados),
                ])
                ->orderBy('cantidad_faltantes');
        }

        $perPage = (int) $request->validated('per_page', 15);

        $recetas = $query
            ->orderByDesc('publicada_en')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        if ($ingredientesSeleccionados !== []) {
            foreach ($recetas as $receta) {
                [$disponibles, $faltantes] = $receta->ingredientes->partition(
                    fn (Ingrediente $ingrediente): bool => in_array((int) $ingrediente->id, $ingredientesSeleccionados, true),
                );
                $receta->setRelation('ingredientesDisponibles', $disponibles->values());
                $receta->setRelation('ingredientesFaltantes', $faltantes->values());
            }
        }

        return RecetaCatalogoResource::collection($recetas);
    }

    public function show(mixed $receta): RecetaDetalleResource
    {
        if (! is_numeric($receta) || (int) $receta <= 0) {
            abort(404, 'Receta no encontrada.');
        }

        $id = (int) $receta;

        $recetaModel = Receta::query()
            ->publicada()
            ->withAvg('valoraciones as valoracion_promedio', 'puntuacion')
            ->withCount('valoraciones as cantidad_valoraciones')
            ->withCount('ingredientes as cantidad_ingredientes')
            ->with([
                'categorias:id,nombre',
                'ingredientes' => fn ($subQuery) => $subQuery
                    ->select('ingredientes.id', 'ingredientes.nombre')
                    ->withPivot(['cantidad', 'unidad', 'notas', 'orden'])
                    ->orderByPivot('orden'),
                'pasos' => fn ($subQuery) => $subQuery
                    ->select('id', 'receta_id', 'orden', 'instruccion')
                    ->orderBy('orden'),
            ])
            ->find($id);

        if (! $recetaModel) {
            abort(404, 'Receta no encontrada.');
        }

        return new RecetaDetalleResource($recetaModel);
    }

    public function imagen(mixed $receta, ImagenRevision $imagenes): BinaryFileResponse
    {
        if (! is_numeric($receta) || (int) $receta <= 0) {
            abort(404, 'Receta no encontrada.');
        }

        $id = (int) $receta;

        $recetaModel = Receta::query()
            ->publicada()
            ->find($id);

        if (! $recetaModel) {
            abort(404, 'Receta no encontrada.');
        }

        $imagen = $imagenes->localizar($recetaModel->imagen, $recetaModel->id);
        if (! $imagen) {
            abort(404, 'Imagen no encontrada.');
        }

        return response()->file($imagen['ruta'], [
            'Content-Type' => $imagen['mime'],
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ])->setPrivate();
    }

    private function escaparComodinesLike(string $valor, string $caracterEscape = '!'): string
    {
        return str_replace(
            [$caracterEscape, '%', '_'],
            [$caracterEscape.$caracterEscape, $caracterEscape.'%', $caracterEscape.'_'],
            $valor
        );
    }
}
