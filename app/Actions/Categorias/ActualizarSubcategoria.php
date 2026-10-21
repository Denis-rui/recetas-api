<?php

namespace App\Actions\Categorias;

use App\Models\Categoria;
use App\Models\Subcategoria;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class ActualizarSubcategoria
{
    /**
     * @param  array<string, mixed>  $datos
     */
    public function ejecutar(User $administrador, Categoria $categoria, Subcategoria $subcategoria, array $datos): Subcategoria
    {
        abort_if((int) $subcategoria->categoria_id !== (int) $categoria->id, 404);
        Gate::forUser($administrador)->authorize('update', $subcategoria);

        return DB::transaction(function () use ($administrador, $categoria, $subcategoria, $datos): Subcategoria {
            $adminActual = User::query()->whereKey($administrador->id)->lockForUpdate()->first();
            $categoriaActual = Categoria::query()->whereKey($categoria->id)->lockForUpdate()->firstOrFail();
            $subcategoriaActual = Subcategoria::query()->whereKey($subcategoria->id)->lockForUpdate()->firstOrFail();

            abort_if((int) $subcategoriaActual->categoria_id !== (int) $categoriaActual->id, 404);
            Gate::forUser($adminActual)->authorize('update', $subcategoriaActual);

            $nombre = isset($datos['nombre']) && is_string($datos['nombre'])
                ? (preg_replace('/\A[\s\p{Z}\p{Cf}]+|[\s\p{Z}\p{Cf}]+\z/u', '', $datos['nombre']) ?? $datos['nombre'])
                : ($datos['nombre'] ?? null);

            $validados = Validator::make(['nombre' => $nombre], [
                'nombre' => ['required', 'string', 'max:100'],
            ], [
                'nombre.required' => 'El nombre de la subcategoría es obligatorio.',
                'nombre.string' => 'El nombre de la subcategoría debe ser un texto válido.',
                'nombre.max' => 'El nombre de la subcategoría no puede superar los 100 caracteres.',
            ])->validate();

            $subcategoriaActual->update([
                'nombre' => $validados['nombre'],
            ]);

            return $subcategoriaActual;
        });
    }
}
