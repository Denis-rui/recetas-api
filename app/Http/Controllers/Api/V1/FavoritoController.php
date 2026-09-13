<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Favoritos\AgregarFavorito;
use App\Actions\Favoritos\QuitarFavorito;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Favoritos\ListarFavoritosRequest;
use App\Http\Requests\Api\V1\Favoritos\VerificarDisponibilidadRequest;
use App\Http\Resources\Api\V1\FavoritoResource;
use App\Models\Receta;
use App\Rules\IdentificadorEntero;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class FavoritoController extends Controller
{
    /**
     * Listado paginado de favoritos del usuario autenticado.
     */
    public function index(ListarFavoritosRequest $request): AnonymousResourceCollection
    {
        $usuario = $request->user();
        $perPage = (int) $request->validated('per_page', 15);

        $favoritos = $usuario->favoritos()
            ->withTrashed()
            ->withCount('ingredientes as cantidad_ingredientes')
            ->withAvg('valoraciones as valoracion_promedio', 'puntuacion')
            ->withCount('valoraciones as cantidad_valoraciones')
            ->with([
                'categorias:id,nombre',
                'ingredientes' => fn ($subQuery) => $subQuery
                    ->select('ingredientes.id', 'ingredientes.nombre')
                    ->withPivot(['orden'])
                    ->orderByPivot('orden'),
            ])
            ->orderByDesc('favoritos.created_at')
            ->orderByDesc('favoritos.id')
            ->paginate($perPage)
            ->withQueryString();

        return FavoritoResource::collection($favoritos);
    }

    /**
     * Agrega una receta publicada a los favoritos propios de forma idempotente.
     */
    public function store(Request $request, mixed $receta, AgregarFavorito $accion): JsonResponse
    {
        $recetaModel = $accion->ejecutar($request->user(), $receta);

        return response()->json([
            'mensaje' => 'Receta agregada a favoritos exitosamente.',
            'receta_id' => $recetaModel->id,
            'es_favorito' => true,
        ], Response::HTTP_CREATED);
    }

    /**
     * Quita una receta de los favoritos propios de forma idempotente.
     * Funciona tanto para recetas vigentes como eliminadas.
     */
    public function destroy(Request $request, mixed $receta, QuitarFavorito $accion): JsonResponse
    {
        $accion->ejecutar($request->user(), $receta);

        return response()->json([
            'mensaje' => 'Receta quitada de favoritos exitosamente.',
            'es_favorito' => false,
        ], Response::HTTP_OK);
    }

    /**
     * Consulta si una receta específica está guardada por el usuario autenticado.
     */
    public function estado(Request $request, mixed $receta): JsonResponse
    {
        if (! IdentificadorEntero::esValido($receta)) {
            abort(404, 'Receta no encontrada.');
        }

        $id = (int) $receta;
        $recetaExiste = Receta::withTrashed()->whereKey($id)
            ->where(fn ($query) => $query->whereNotNull('publicada_en')
                ->orWhere('creado_por', $request->user()->id))
            ->exists();

        if (! $recetaExiste) {
            abort(404, 'Receta no encontrada.');
        }

        $esFavorito = $request->user()->favoritos()
            ->withTrashed()
            ->where('receta_id', $id)
            ->exists();

        return response()->json([
            'receta_id' => $id,
            'es_favorito' => $esFavorito,
        ], Response::HTTP_OK);
    }

    /**
     * Verifica la disponibilidad de un conjunto acotado de recetas guardadas (máx 50)
     * para confirmar si siguen vigentes o fueron eliminadas, sin revelar recetas privadas.
     */
    public function verificarDisponibilidad(VerificarDisponibilidadRequest $request): JsonResponse
    {
        $ids = $request->validated('ids');

        $recetas = Receta::withTrashed()
            ->whereIn('id', $ids)
            ->whereNotNull('publicada_en')
            ->get(['id', 'publicada_en', 'deleted_at', 'tipo_eliminacion'])
            ->keyBy('id');

        $resultados = [];
        foreach ($ids as $id) {
            $receta = $recetas->get($id);

            // No revelar existencia de recetas privadas
            if (! $receta || $receta->publicada_en === null) {
                $resultados[] = [
                    'id' => $id,
                    'estado' => 'no_disponible',
                ];

                continue;
            }

            if ($receta->trashed()) {
                $tipo = $receta->tipo_eliminacion === 'autor'
                    ? 'eliminada_autor'
                    : 'eliminada_administracion';

                $mensaje = $receta->tipo_eliminacion === 'autor'
                    ? 'Esta receta fue eliminada por su autor'
                    : 'Esta receta fue eliminada';

                $resultados[] = [
                    'id' => $id,
                    'estado' => $tipo,
                    'mensaje' => $mensaje,
                ];

                continue;
            }

            $resultados[] = [
                'id' => $id,
                'estado' => 'disponible',
            ];
        }

        return response()->json([
            'data' => $resultados,
        ], Response::HTTP_OK);
    }
}
