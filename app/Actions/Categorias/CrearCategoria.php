<?php

namespace App\Actions\Categorias;

use App\Models\Categoria;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CrearCategoria
{
    /**
     * @param  array<string, mixed>  $datos
     */
    public function ejecutar(User $administrador, array $datos): Categoria
    {
        Gate::forUser($administrador)->authorize('create', Categoria::class);

        return DB::transaction(function () use ($administrador, $datos): Categoria {
            $adminActual = User::query()->whereKey($administrador->id)->lockForUpdate()->first();
            Gate::forUser($adminActual)->authorize('create', Categoria::class);

            $nombre = isset($datos['nombre']) && is_string($datos['nombre'])
                ? (preg_replace('/\A[\s\p{Z}\p{Cf}]+|[\s\p{Z}\p{Cf}]+\z/u', '', $datos['nombre']) ?? $datos['nombre'])
                : ($datos['nombre'] ?? null);

            $validados = Validator::make(['nombre' => $nombre], [
                'nombre' => ['required', 'string', 'max:100', Rule::unique('categorias', 'nombre')],
            ], [
                'nombre.required' => 'El nombre de la categoría es obligatorio.',
                'nombre.string' => 'El nombre de la categoría debe ser un texto válido.',
                'nombre.max' => 'El nombre de la categoría no puede superar los 100 caracteres.',
                'nombre.unique' => 'Ya existe una categoría con este nombre.',
            ])->validate();

            try {
                return Categoria::query()->create([
                    'nombre' => $validados['nombre'],
                ]);
            } catch (UniqueConstraintViolationException) {
                throw ValidationException::withMessages([
                    'nombre' => 'Ya existe una categoría con este nombre.',
                ]);
            }
        });
    }
}
