<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CategoriaResource;
use App\Models\Categoria;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CatalogoCategoriaController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $categorias = Categoria::query()
            ->whereHas('subcategorias')
            ->with([
                'subcategorias' => fn ($query) => $query
                    ->select('id', 'categoria_id', 'nombre')
                    ->orderBy('nombre')
                    ->orderBy('id'),
            ])
            ->orderBy('nombre')
            ->orderBy('id')
            ->get(['id', 'nombre']);

        return CategoriaResource::collection($categorias);
    }

    public function adminIndex(Request $request): AnonymousResourceCollection
    {
        if (! $request->user()?->esAdministrador()) {
            abort(403, 'Acceso exclusivo para administradores.');
        }

        $categorias = Categoria::query()
            ->with([
                'subcategorias' => fn ($query) => $query
                    ->select('id', 'categoria_id', 'nombre')
                    ->orderBy('nombre')
                    ->orderBy('id'),
            ])
            ->orderBy('nombre')
            ->orderBy('id')
            ->get(['id', 'nombre']);

        return CategoriaResource::collection($categorias);
    }
}
