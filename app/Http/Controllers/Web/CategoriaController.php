<?php

namespace App\Http\Controllers\Web;

use App\Actions\Categorias\ActualizarCategoria;
use App\Actions\Categorias\ActualizarSubcategoria;
use App\Actions\Categorias\CrearCategoria;
use App\Actions\Categorias\CrearSubcategoria;
use App\Actions\Categorias\EliminarCategoria;
use App\Actions\Categorias\EliminarSubcategoria;
use App\Http\Controllers\Controller;
use App\Http\Requests\Categorias\GuardarCategoriaRequest;
use App\Http\Requests\Categorias\GuardarSubcategoriaRequest;
use App\Models\Categoria;
use App\Models\Subcategoria;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CategoriaController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', Categoria::class);

        $categorias = Categoria::query()
            ->with([
                'subcategorias' => fn ($query) => $query
                    ->withCount([
                        'recetas as recetas_totales_count' => fn ($recetaQuery) => $recetaQuery->withTrashed(),
                    ])
                    ->orderBy('nombre')
                    ->orderBy('id'),
            ])
            ->withCount([
                'recetas as recetas_totales_count' => fn ($recetaQuery) => $recetaQuery->withTrashed(),
            ])
            ->orderBy('nombre')
            ->orderBy('id')
            ->get();

        return view('categorias.index', compact('categorias'));
    }

    public function store(GuardarCategoriaRequest $request, CrearCategoria $accion): RedirectResponse
    {
        Gate::authorize('create', Categoria::class);

        $categoria = $accion->ejecutar($request->user(), $request->validated());

        return redirect()
            ->route('categorias.index')
            ->with('categoria_abierta', $categoria->id)
            ->with('exito', "La categoría «{$categoria->nombre}» fue creada correctamente.");
    }

    public function update(
        GuardarCategoriaRequest $request,
        Categoria $categoria,
        ActualizarCategoria $accion
    ): RedirectResponse {
        Gate::authorize('update', $categoria);

        $categoriaActualizada = $accion->ejecutar($request->user(), $categoria, $request->validated());

        return redirect()
            ->route('categorias.index')
            ->with('categoria_abierta', $categoria->id)
            ->with('exito', "La categoría fue renombrada a «{$categoriaActualizada->nombre}» correctamente.");
    }

    public function destroy(Request $request, Categoria $categoria, EliminarCategoria $accion): RedirectResponse
    {
        Gate::authorize('delete', $categoria);

        $nombre = $categoria->nombre;

        try {
            $accion->ejecutar($request->user(), $categoria);
        } catch (ValidationException $excepcion) {
            return redirect()
                ->route('categorias.index')
                ->withErrors($excepcion->errors())
                ->with('categoria_abierta', $categoria->id)
                ->with('error', (string) collect($excepcion->errors())->flatten()->first());
        }

        return redirect()
            ->route('categorias.index')
            ->with('exito', "La categoría «{$nombre}» fue eliminada correctamente.");
    }

    public function storeSubcategoria(
        GuardarSubcategoriaRequest $request,
        Categoria $categoria,
        CrearSubcategoria $accion
    ): RedirectResponse {
        Gate::authorize('create', Subcategoria::class);

        $subcategoria = $accion->ejecutar($request->user(), $categoria, $request->validated());

        return redirect()
            ->route('categorias.index')
            ->with('categoria_abierta', $categoria->id)
            ->with('exito', "La subcategoría «{$subcategoria->nombre}» fue agregada a «{$categoria->nombre}».");
    }

    public function updateSubcategoria(
        GuardarSubcategoriaRequest $request,
        Categoria $categoria,
        Subcategoria $subcategoria,
        ActualizarSubcategoria $accion
    ): RedirectResponse {
        abort_if((int) $subcategoria->categoria_id !== (int) $categoria->id, 404);
        Gate::authorize('update', $subcategoria);

        $subcategoriaActualizada = $accion->ejecutar(
            $request->user(),
            $categoria,
            $subcategoria,
            $request->validated()
        );

        return redirect()
            ->route('categorias.index')
            ->with('categoria_abierta', $categoria->id)
            ->with('exito', "La subcategoría fue renombrada a «{$subcategoriaActualizada->nombre}» correctamente.");
    }

    public function destroySubcategoria(
        Request $request,
        Categoria $categoria,
        Subcategoria $subcategoria,
        EliminarSubcategoria $accion
    ): RedirectResponse {
        abort_if((int) $subcategoria->categoria_id !== (int) $categoria->id, 404);
        Gate::authorize('delete', $subcategoria);

        $nombre = $subcategoria->nombre;

        try {
            $accion->ejecutar($request->user(), $categoria, $subcategoria);
        } catch (ValidationException $excepcion) {
            return redirect()
                ->route('categorias.index')
                ->withErrors($excepcion->errors())
                ->with('categoria_abierta', $categoria->id)
                ->with('error', (string) collect($excepcion->errors())->flatten()->first());
        }

        return redirect()
            ->route('categorias.index')
            ->with('categoria_abierta', $categoria->id)
            ->with('exito', "La subcategoría «{$nombre}» fue eliminada correctamente.");
    }
}
