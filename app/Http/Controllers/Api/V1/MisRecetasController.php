<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Recetas\ActualizarRecetaPrivada;
use App\Actions\Recetas\CrearRecetaPrivada;
use App\Actions\Recetas\EliminarRecetaPropia;
use App\Actions\Recetas\GuardarImagenReceta;
use App\Actions\Recetas\ImagenRevision;
use App\Actions\Recetas\SolicitarCorreccionReceta;
use App\Actions\Recetas\SolicitarPublicacionReceta;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\MisRecetas\ActualizarRecetaRequest;
use App\Http\Requests\Api\V1\MisRecetas\CorregirRecetaRequest;
use App\Http\Requests\Api\V1\MisRecetas\GuardarRecetaRequest;
use App\Http\Requests\Api\V1\MisRecetas\ListarMisRecetasRequest;
use App\Http\Requests\Api\V1\MisRecetas\PublicarRecetaRequest;
use App\Http\Requests\Api\V1\MisRecetas\SubirImagenRecetaRequest;
use App\Http\Resources\Api\V1\MiRecetaCatalogoResource;
use App\Http\Resources\Api\V1\MiRecetaDetalleResource;
use App\Http\Resources\Api\V1\MiSolicitudCatalogoResource;
use App\Models\Receta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class MisRecetasController extends Controller
{
    private const CARACTER_ESCAPE = '!';

    /**
     * Listado paginado de recetas propias con filtros y búsqueda.
     */
    public function index(ListarMisRecetasRequest $request): AnonymousResourceCollection
    {
        $usuario = $request->user();

        $query = Receta::query()
            ->withTrashed()
            ->where('creado_por', $usuario->id)
            ->withCount('ingredientes as cantidad_ingredientes')
            ->with([
                'categorias:id,nombre',
                'ingredientes' => fn ($subQuery) => $subQuery
                    ->select('ingredientes.id', 'ingredientes.nombre')
                    ->withPivot(['orden'])
                    ->orderByPivot('orden'),
                'solicitudesRevision' => fn ($subQuery) => $subQuery
                    ->where('estado', 'pendiente')
                    ->orderByDesc('id'),
            ]);

        $filtro = $request->validated('filtro', 'todas');
        if ($filtro === 'privadas') {
            $query->whereNull('deleted_at')->whereNull('publicada_en');
        } elseif ($filtro === 'publicadas') {
            $query->whereNull('deleted_at')->whereNotNull('publicada_en');
        } elseif ($filtro === 'eliminadas') {
            $query->whereNotNull('deleted_at');
        }

        $buscar = $request->validated('buscar');
        if ($buscar !== null && $buscar !== '') {
            $busquedaEscapada = $this->escaparComodinesLike($buscar, self::CARACTER_ESCAPE);
            $query->whereRaw("nombre LIKE ? ESCAPE '!'", ["%{$busquedaEscapada}%"]);
        }

        $perPage = (int) $request->input('per_page', 15);

        $recetas = $query
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return MiRecetaCatalogoResource::collection($recetas);
    }

    /**
     * Guarda una receta privada completa en la cuenta propia.
     */
    public function store(GuardarRecetaRequest $request, CrearRecetaPrivada $accion): JsonResponse
    {
        $usuario = $request->user();
        $datos = $request->safe()->except('imagen');
        $imagen = $request->file('imagen') ?? $request->input('imagen');

        $receta = $accion->ejecutar($usuario, $datos, $imagen);

        return (new MiRecetaDetalleResource($receta))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Consulta el detalle de una receta propia para ver o editar.
     */
    public function show(Request $request, mixed $receta): MiRecetaDetalleResource
    {
        $recetaModel = $this->obtenerRecetaAutorizada($request, $receta);

        $recetaModel->loadMissing([
            'categorias:id,nombre',
            'ingredientes' => fn ($q) => $q->withPivot(['cantidad', 'unidad', 'notas', 'orden'])->orderByPivot('orden'),
            'pasos' => fn ($q) => $q->orderBy('orden'),
            'solicitudesRevision' => fn ($q) => $q->where('estado', 'pendiente')->orderByDesc('id'),
        ]);

        return new MiRecetaDetalleResource($recetaModel);
    }

    /**
     * Actualiza una receta privada propia mientras las reglas lo permitan.
     */
    public function update(ActualizarRecetaRequest $request, mixed $receta, ActualizarRecetaPrivada $accion): MiRecetaDetalleResource
    {
        $recetaModel = $this->obtenerRecetaAutorizada($request, $receta);

        $datos = $request->safe()->except('imagen');
        $imagen = $request->file('imagen') ?? $request->input('imagen');

        $recetaActualizada = $accion->ejecutar($request->user(), $recetaModel, $datos, $imagen);

        return new MiRecetaDetalleResource($recetaActualizada);
    }

    /**
     * Elimina lógicamente una receta propia.
     */
    public function destroy(Request $request, mixed $receta, EliminarRecetaPropia $accion): JsonResponse
    {
        $recetaModel = $this->obtenerRecetaAutorizada($request, $receta);

        $recetaEliminada = $accion->ejecutar($request->user(), $recetaModel);

        return response()->json([
            'mensaje' => 'Receta eliminada exitosamente.',
            'receta' => new MiRecetaDetalleResource($recetaEliminada),
        ], Response::HTTP_OK);
    }

    /**
     * Sube o reemplaza la imagen de una receta propia.
     */
    public function subirImagen(SubirImagenRecetaRequest $request, mixed $receta, GuardarImagenReceta $guardarImagen): JsonResponse
    {
        $recetaModel = $this->obtenerRecetaAutorizada($request, $receta);

        if ($recetaModel->trashed()) {
            abort(422, 'No se puede modificar la imagen de una receta eliminada.');
        }

        if ($recetaModel->solicitudesRevision()->where('estado', 'pendiente')->exists()) {
            abort(422, 'No se puede modificar la imagen de una receta con publicación pendiente. Debe cancelar primero la solicitud.');
        }

        $resultado = $guardarImagen->ejecutar($request->file('imagen'), $recetaModel->id);

        // Si la receta aún es privada, actualizamos su campo imagen
        if ($recetaModel->publicada_en === null) {
            $recetaModel->imagen = $resultado['ruta'];
            $recetaModel->save();
        }

        return response()->json([
            'mensaje' => 'Imagen subida exitosamente.',
            'imagen' => $resultado['ruta'],
            'imagen_url' => route('api.v1.mis-recetas.imagen', ['receta' => $recetaModel->id]),
        ], Response::HTTP_OK);
    }

    /**
     * Sirve de forma autenticada la imagen vigente de una receta del autor.
     */
    public function imagen(Request $request, mixed $receta, ImagenRevision $imagenes): BinaryFileResponse
    {
        $recetaModel = $this->obtenerRecetaAutorizada($request, $receta);

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

    /**
     * Solicita la publicación de una receta privada (o la publica directamente si es administrador).
     */
    public function publicar(PublicarRecetaRequest $request, mixed $receta, SolicitarPublicacionReceta $accion): JsonResponse
    {
        $recetaModel = $this->obtenerRecetaAutorizada($request, $receta);

        $claveIdempotencia = (string) $request->validated('clave_idempotencia');
        $resultado = $accion->ejecutar($request->user(), $recetaModel, $claveIdempotencia);

        if ($resultado['tipo'] === 'directa') {
            return response()->json([
                'mensaje' => 'Receta publicada directamente exitosamente.',
                'receta' => new MiRecetaDetalleResource($resultado['receta']),
            ], Response::HTTP_OK);
        }

        $status = ! empty($resultado['reintento']) ? Response::HTTP_OK : Response::HTTP_CREATED;

        return response()->json([
            'mensaje' => ! empty($resultado['reintento'])
                ? 'Solicitud de publicación previamente registrada recuperada exitosamente.'
                : 'Solicitud de publicación enviada a revisión exitosamente.',
            'solicitud' => new MiSolicitudCatalogoResource($resultado['solicitud']),
        ], $status);
    }

    /**
     * Envía una propuesta de corrección sobre una receta publicada (o la aplica directamente si es administrador).
     */
    public function corregir(CorregirRecetaRequest $request, mixed $receta, SolicitarCorreccionReceta $accion): JsonResponse
    {
        $recetaModel = $this->obtenerRecetaAutorizada($request, $receta);

        $propuesta = (array) $request->validated('contenido');
        $versionBase = (int) $request->validated('version_base');
        $claveIdempotencia = (string) $request->validated('clave_idempotencia');

        $resultado = $accion->ejecutar($request->user(), $recetaModel, $propuesta, $versionBase, $claveIdempotencia);

        if ($resultado['tipo'] === 'directa') {
            return response()->json([
                'mensaje' => 'Corrección menor aplicada directamente sobre la receta publicada.',
                'receta' => new MiRecetaDetalleResource($resultado['receta']),
            ], Response::HTTP_OK);
        }

        $status = ! empty($resultado['reintento']) ? Response::HTTP_OK : Response::HTTP_CREATED;

        return response()->json([
            'mensaje' => ! empty($resultado['reintento'])
                ? 'Propuesta de corrección previamente registrada recuperada exitosamente.'
                : 'Propuesta de corrección enviada a revisión exitosamente.',
            'solicitud' => new MiSolicitudCatalogoResource($resultado['solicitud']),
        ], $status);
    }

    /**
     * Busca la receta y comprueba que pertenezca al usuario autenticado.
     */
    private function obtenerRecetaAutorizada(Request $request, mixed $receta): Receta
    {
        if (! is_numeric($receta) || (int) $receta <= 0) {
            abort(404, 'Receta no encontrada.');
        }

        $id = (int) $receta;
        $recetaModel = Receta::withTrashed()->find($id);

        if (! $recetaModel) {
            abort(404, 'Receta no encontrada.');
        }

        if ($recetaModel->creado_por !== $request->user()->id) {
            abort(403, 'No tiene permiso para acceder a esta receta.');
        }

        return $recetaModel;
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
