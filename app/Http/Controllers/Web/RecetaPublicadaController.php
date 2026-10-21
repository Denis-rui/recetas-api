<?php

namespace App\Http\Controllers\Web;

use App\Actions\Recetas\ActualizarRecetaPublicada;
use App\Actions\Recetas\ImagenRevision;
use App\Http\Controllers\Controller;
use App\Http\Requests\Recetas\ActualizarRecetaPublicadaRequest;
use App\Models\Categoria;
use App\Models\Ingrediente;
use App\Models\Receta;
use App\Models\Subcategoria;
use App\Rules\IdentificadorEntero;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class RecetaPublicadaController extends Controller
{
    public function index(Request $request): Response|RedirectResponse|JsonResponse
    {
        Gate::authorize('administrarListado', Receta::class);
        $terminoBusqueda = trim((string) $request->query('buscar', ''));
        $request->merge(['buscar' => $terminoBusqueda === '' ? null : $terminoBusqueda]);

        try {
            $filtros = $request->validate([
                'page' => ['sometimes', 'bail', new IdentificadorEntero, 'integer', 'max:'.intdiv(PHP_INT_MAX, 15)],
                'buscar' => ['nullable', 'string', 'min:2', 'max:100'],
                'categoria_id' => ['nullable', 'bail', new IdentificadorEntero, 'exists:categorias,id'],
                'subcategoria_id' => ['nullable', 'bail', new IdentificadorEntero, 'exists:subcategorias,id'],
                'clasificacion' => ['nullable', Rule::in(['todas', 'clasificadas', 'sin_clasificar'])],
            ], [
                'in' => 'El filtro :attribute seleccionado no es válido.',
                'exists' => 'El filtro :attribute seleccionado no existe.',
            ]);

            if (isset($filtros['categoria_id'], $filtros['subcategoria_id'])) {
                $corresponde = Subcategoria::query()
                    ->whereKey((int) $filtros['subcategoria_id'])
                    ->where('categoria_id', (int) $filtros['categoria_id'])
                    ->exists();

                if (! $corresponde) {
                    throw ValidationException::withMessages([
                        'subcategoria_id' => 'La subcategoría de filtro no pertenece a la categoría seleccionada.',
                    ]);
                }
            }
        } catch (ValidationException $excepcion) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Revisa los filtros seleccionados.',
                    'errors' => $excepcion->errors(),
                ], 422);
            }

            return redirect()
                ->route('recetas.index')
                ->withErrors($excepcion->errors());
        }

        $buscar = isset($filtros['buscar']) ? trim((string) $filtros['buscar']) : '';
        $categoriaId = isset($filtros['categoria_id']) ? (int) $filtros['categoria_id'] : null;
        $subcategoriaId = isset($filtros['subcategoria_id']) ? (int) $filtros['subcategoria_id'] : null;
        $clasificacion = $filtros['clasificacion'] ?? 'todas';
        $buscarEscapado = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $buscar);

        if ($subcategoriaId !== null && $categoriaId === null) {
            $subcategoriaFiltro = Subcategoria::query()->find($subcategoriaId, ['id', 'categoria_id']);
            $categoriaId = $subcategoriaFiltro?->categoria_id;
        }

        $recetas = Receta::query()
            ->publicada()
            ->with([
                'creador:id,name',
                'categoria:id,nombre',
                'subcategoria:id,nombre',
            ])
            ->withCount('valoraciones')
            ->withAvg('valoraciones', 'puntuacion')
            ->when($buscar !== '', function ($consulta) use ($buscarEscapado): void {
                $consulta->where(function ($subconsulta) use ($buscarEscapado): void {
                    $patron = '%'.$buscarEscapado.'%';
                    $subconsulta->whereRaw("nombre LIKE ? ESCAPE '!'", [$patron])
                        ->orWhereRaw("descripcion LIKE ? ESCAPE '!'", [$patron]);
                });
            })
            ->when($clasificacion === 'sin_clasificar', fn ($consulta) => $consulta->whereNull('categoria_id'))
            ->when($clasificacion === 'clasificadas', fn ($consulta) => $consulta->whereNotNull('categoria_id'))
            ->when($categoriaId !== null, fn ($consulta) => $consulta->where('categoria_id', $categoriaId))
            ->when($subcategoriaId !== null, fn ($consulta) => $consulta->where('subcategoria_id', $subcategoriaId))
            ->orderByDesc('publicada_en')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        if ($request->ajax()) {
            return response()
                ->view('recetas.partials.listado', compact('recetas'))
                ->header('Cache-Control', 'private, no-store')
                ->header('X-Total-Count', (string) $recetas->total());
        }

        $categorias = Categoria::query()
            ->with([
                'subcategorias' => fn ($consulta) => $consulta->orderBy('nombre')->orderBy('id'),
            ])
            ->orderBy('nombre')
            ->orderBy('id')
            ->get();

        return response()
            ->view('recetas.index', compact(
                'recetas',
                'categorias',
                'buscar',
                'categoriaId',
                'subcategoriaId',
                'clasificacion',
            ))
            ->header('Cache-Control', 'private, no-store');
    }

    public function show(Receta $receta, ImagenRevision $imagenes): Response
    {
        Gate::authorize('administrarListado', Receta::class);
        abort_unless($receta->estaPublicada(), 404);
        Gate::authorize('administrar', $receta);

        $receta->loadMissing([
            'creador:id,name,activo',
            'ultimoEditor:id,name',
            'categoria:id,nombre',
            'subcategoria:id,nombre',
            'ingredientes',
            'pasos',
        ])
            ->loadCount(['valoraciones', 'usuariosQueLaGuardaron'])
            ->loadAvg('valoraciones', 'puntuacion');

        $imagenUrl = $imagenes->localizar($receta->imagen, $receta->id)
            ? route('recetas.imagen', $receta)
            : null;

        return response()
            ->view('recetas.show', compact('receta', 'imagenUrl'))
            ->header('Cache-Control', 'private, no-store');
    }

    public function edit(Receta $receta, ImagenRevision $imagenes): Response
    {
        Gate::authorize('administrarListado', Receta::class);
        abort_unless($receta->estaPublicada(), 404);
        Gate::authorize('administrar', $receta);

        $receta->loadMissing([
            'creador:id,name,activo',
            'ultimoEditor:id,name',
            'categoria:id,nombre',
            'subcategoria:id,nombre',
            'ingredientes',
            'pasos',
        ])
            ->loadCount(['valoraciones', 'usuariosQueLaGuardaron'])
            ->loadAvg('valoraciones', 'puntuacion');

        $categorias = Categoria::query()
            ->whereHas('subcategorias')
            ->with([
                'subcategorias' => fn ($consulta) => $consulta->orderBy('nombre')->orderBy('id'),
            ])
            ->orderBy('nombre')
            ->orderBy('id')
            ->get();

        $ingredientesCatalogo = Ingrediente::query()
            ->orderBy('nombre')
            ->orderBy('id')
            ->get(['id', 'nombre']);

        $imagenUrl = $imagenes->localizar($receta->imagen, $receta->id)
            ? route('recetas.imagen', $receta)
            : null;

        return response()
            ->view('recetas.edit', compact('receta', 'categorias', 'ingredientesCatalogo', 'imagenUrl'))
            ->header('Cache-Control', 'private, no-store');
    }

    public function update(
        ActualizarRecetaPublicadaRequest $request,
        Receta $receta,
        ActualizarRecetaPublicada $accion,
    ): RedirectResponse {
        Gate::authorize('administrarListado', Receta::class);
        abort_unless($receta->estaPublicada(), 404);
        Gate::authorize('administrar', $receta);

        $destino = redirect()->route('recetas.edit', $receta);
        $imagenEntrada = $request->hasFile('imagen')
            ? $request->file('imagen')
            : ($request->filled('imagen') ? (string) $request->input('imagen') : null);

        try {
            $accion->ejecutar($request->user(), $receta, $request->validated(), $imagenEntrada);
        } catch (ValidationException $excepcion) {
            return $destino
                ->withErrors($excepcion->errors())
                ->withInput($request->except('imagen'));
        } catch (AuthorizationException $excepcion) {
            throw $excepcion;
        } catch (Throwable $excepcion) {
            report($excepcion);

            return $destino
                ->with('error', 'No se pudieron guardar los cambios. No se aplicaron modificaciones parciales.')
                ->withInput($request->except('imagen'));
        }

        return $destino->with('exito', 'La receta publicada fue actualizada directamente.');
    }

    public function imagen(Receta $receta, ImagenRevision $imagenes): BinaryFileResponse
    {
        Gate::authorize('administrarListado', Receta::class);
        abort_unless($receta->estaPublicada(), 404);
        Gate::authorize('administrar', $receta);

        $imagen = $imagenes->localizar($receta->imagen, $receta->id);
        abort_unless($imagen, 404);

        return response()->file($imagen['ruta'], [
            'Content-Type' => $imagen['mime'],
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ])->setPrivate();
    }
}

