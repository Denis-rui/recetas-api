<?php

namespace App\Actions\Categorias;

use App\Models\Categoria;
use App\Models\Receta;
use App\Models\Subcategoria;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class EliminarSubcategoria
{
    public function ejecutar(User $administrador, Categoria $categoria, Subcategoria $subcategoria): void
    {
        abort_if((int) $subcategoria->categoria_id !== (int) $categoria->id, 404);
        Gate::forUser($administrador)->authorize('delete', $subcategoria);

        DB::transaction(function () use ($administrador, $categoria, $subcategoria): void {
            $adminActual = User::query()->whereKey($administrador->id)->lockForUpdate()->first();
            $categoriaActual = Categoria::query()->whereKey($categoria->id)->lockForUpdate()->firstOrFail();
            $subcategoriaActual = Subcategoria::query()->whereKey($subcategoria->id)->lockForUpdate()->firstOrFail();

            abort_if((int) $subcategoriaActual->categoria_id !== (int) $categoriaActual->id, 404);
            Gate::forUser($adminActual)->authorize('delete', $subcategoriaActual);

            $tieneRecetas = Receta::withTrashed()
                ->where('subcategoria_id', $subcategoriaActual->id)
                ->lockForUpdate()
                ->exists();

            if ($tieneRecetas) {
                throw ValidationException::withMessages([
                    'eliminacion' => "No se puede eliminar la subcategoría «{$subcategoriaActual->nombre}» porque tiene recetas asociadas.",
                ]);
            }

            try {
                $subcategoriaActual->delete();
            } catch (QueryException) {
                throw ValidationException::withMessages([
                    'eliminacion' => "No se puede eliminar la subcategoría «{$subcategoriaActual->nombre}» porque tiene recetas asociadas.",
                ]);
            }
        });
    }
}
