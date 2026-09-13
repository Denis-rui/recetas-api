<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CategoriaResource;
use App\Models\Categoria;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CatalogoCategoriaController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $categorias = Categoria::query()
            ->orderBy('nombre')
            ->orderBy('id')
            ->get();

        return CategoriaResource::collection($categorias);
    }
}
