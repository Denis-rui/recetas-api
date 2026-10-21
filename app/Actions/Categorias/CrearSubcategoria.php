<?php

namespace App\Actions\Categorias;

use App\Models\Categoria;
use App\Models\Subcategoria;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class CrearSubcategoria
{
    /**
     * @param  array<string, mixed>  $datos
     */
    public function ejecutar(User $administrador, Categoria $categoria, array $datos): Subcategoria
    {
        Gate::forUser($administrador)->authorize('create', Subcategoria::class);

        return DB::transaction(function () use ($administrador, $categoria, $datos): Subcategoria {
            $adminActual = User::query()->whereKey($administrador->id)->lockForUpdate()->first();
            $categoriaActual = Categoria::query()->whereKey($categoria->id)->lockForUpdate()->firstOrFail();

            Gate::forUser($adminActual)->authorize('create', Subcategoria::class);

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

            return $categoriaActual->subcategorias()->create([
                'nombre' => $validados['nombre'],
            ]);
        });
    }
}
