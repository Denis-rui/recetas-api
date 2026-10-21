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

class EliminarCategoria
{
    public function ejecutar(User $administrador, Categoria $categoria): void
    {
        Gate::forUser($administrador)->authorize('delete', $categoria);

        DB::transaction(function () use ($administrador, $categoria): void {
            $adminActual = User::query()->whereKey($administrador->id)->lockForUpdate()->first();
            $categoriaActual = Categoria::query()->whereKey($categoria->id)->lockForUpdate()->firstOrFail();

            Gate::forUser($adminActual)->authorize('delete', $categoriaActual);

            $subcategoriaIds = Subcategoria::query()
                ->where('categoria_id', $categoriaActual->id)
                ->lockForUpdate()
                ->pluck('id');

            $tieneRecetas = Receta::withTrashed()
                ->where(function ($query) use ($categoriaActual, $subcategoriaIds): void {
                    $query->where('categoria_id', $categoriaActual->id);

                    if ($subcategoriaIds->isNotEmpty()) {
                        $query->orWhereIn('subcategoria_id', $subcategoriaIds);
                    }
                })
                ->lockForUpdate()
                ->exists();

            if ($tieneRecetas) {
                throw ValidationException::withMessages([
                    'eliminacion' => "No se puede eliminar la categoría «{$categoriaActual->nombre}» porque tiene recetas asociadas.",
                ]);
            }

            try {
                $categoriaActual->delete();
            } catch (QueryException) {
                throw ValidationException::withMessages([
                    'eliminacion' => "No se puede eliminar la categoría «{$categoriaActual->nombre}» porque tiene recetas asociadas.",
                ]);
            }
        });
    }
}
