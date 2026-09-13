<?php

namespace App\Actions\Favoritos;

use App\Models\Receta;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AgregarFavorito
{
    /**
     * Agrega una receta publicada a los favoritos del usuario autenticado.
     * Operación idempotente: si ya está guardada, se conserva sin duplicar.
     *
     * @throws ValidationException
     */
    public function ejecutar(User $usuario, mixed $recetaId): Receta
    {
        if (! $usuario->estaActivo()) {
            throw ValidationException::withMessages([
                'usuario' => 'La cuenta no se encuentra activa.',
            ]);
        }

        if (! is_numeric($recetaId) || (int) $recetaId <= 0) {
            abort(404, 'Receta no encontrada.');
        }

        $id = (int) $recetaId;

        return DB::transaction(function () use ($usuario, $id) {
            // Orden canónico de bloqueos: usuario, luego receta
            $userActual = User::whereKey($usuario->id)->lockForUpdate()->firstOrFail();
            if (! $userActual->estaActivo()) {
                throw ValidationException::withMessages(['usuario' => 'La cuenta no se encuentra activa.']);
            }

            $receta = Receta::withTrashed()->whereKey($id)->lockForUpdate()->first();

            if (! $receta || $receta->publicada_en === null) {
                abort(404, 'Receta no encontrada o no disponible para agregar a favoritos.');
            }

            if ($receta->trashed()) {
                throw ValidationException::withMessages([
                    'receta' => 'Una receta eliminada no se puede agregar a favoritos.',
                ]);
            }

            // Inserción idempotente que no duplica el favorito
            $userActual->favoritos()->syncWithoutDetaching([
                $receta->id => [
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);

            return $receta;
        }, attempts: 3);
    }
}

