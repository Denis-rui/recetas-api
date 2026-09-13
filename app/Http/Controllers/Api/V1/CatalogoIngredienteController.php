<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListarIngredientesRequest;
use App\Http\Resources\Api\V1\IngredienteResource;
use App\Models\Ingrediente;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CatalogoIngredienteController extends Controller
{
    private const CARACTER_ESCAPE = '!';

    public function index(ListarIngredientesRequest $request): AnonymousResourceCollection
    {
        $query = Ingrediente::query();

        $buscar = $request->validated('buscar');
        if ($buscar !== null && $buscar !== '') {
            $busquedaEscapada = $this->escaparComodinesLike($buscar, self::CARACTER_ESCAPE);
            $terminoLike = "%{$busquedaEscapada}%";
            $query->whereRaw("nombre LIKE ? ESCAPE '!'", [$terminoLike]);
        }

        $perPage = (int) $request->input('per_page', 15);

        $ingredientes = $query
            ->orderBy('nombre')
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();

        return IngredienteResource::collection($ingredientes);
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

