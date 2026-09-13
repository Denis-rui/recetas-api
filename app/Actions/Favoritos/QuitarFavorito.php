<?php

namespace App\Actions\Favoritos;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QuitarFavorito
{
    /**
     * Quita una receta de los favoritos del usuario autenticado.
     * Funciona tanto para recetas vigentes como eliminadas (withTrashed).
     * Operación idempotente: si no estaba guardada o ya se quitó, responde con éxito.
     *
     * @throws ValidationException
     */
    public function ejecutar(User $usuario, mixed $recetaId): void
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

        DB::transaction(function () use ($usuario, $id) {
            $userActual = User::whereKey($usuario->id)->lockForUpdate()->firstOrFail();
            if (! $userActual->estaActivo()) {
                throw ValidationException::withMessages(['usuario' => 'La cuenta no se encuentra activa.']);
            }

            // Desvincular de favoritos de forma segura e idempotente
            $userActual->favoritos()->detach($id);
        }, attempts: 3);
    }
}

