<?php

namespace App\Actions\Valoraciones;

use App\Models\Receta;
use App\Models\User;
use App\Models\Valoracion;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RegistrarValoracion
{
    /**
     * Registra o actualiza la valoración del usuario sobre una receta publicada.
     * Solo permite valores enteros entre 1 y 5.
     * Si ya existía una puntuación, la modifica sin sumar un voto adicional.
     *
     * @return array{puntuacion: int, valoracion_promedio: float|null, cantidad_valoraciones: int}
     *
     * @throws ValidationException
     */
    public function ejecutar(User $usuario, mixed $recetaId, int $puntuacion): array
    {
        if (! $usuario->estaActivo()) {
            throw ValidationException::withMessages([
                'usuario' => 'La cuenta no se encuentra activa.',
            ]);
        }

        if ($puntuacion < 1 || $puntuacion > 5) {
            throw ValidationException::withMessages([
                'puntuacion' => 'La puntuación debe ser un número entero entre 1 y 5.',
            ]);
        }

        if (! is_numeric($recetaId) || (int) $recetaId <= 0) {
            abort(404, 'Receta no encontrada.');
        }

        $id = (int) $recetaId;

        return DB::transaction(function () use ($usuario, $id, $puntuacion) {
            // Orden canónico de bloqueos: usuario, luego receta, luego valoracion
            $userActual = User::whereKey($usuario->id)->lockForUpdate()->firstOrFail();
            if (! $userActual->estaActivo()) {
                throw ValidationException::withMessages(['usuario' => 'La cuenta no se encuentra activa.']);
            }

            $receta = Receta::withTrashed()->whereKey($id)->lockForUpdate()->first();

            if (! $receta || $receta->publicada_en === null) {
                abort(404, 'Receta no encontrada o no disponible para valorar.');
            }

            if ($receta->trashed()) {
                throw ValidationException::withMessages([
                    'receta' => 'No es posible valorar una receta que ha sido eliminada.',
                ]);
            }

            // Actualizar o crear la valoración
            $valoracion = Valoracion::where('usuario_id', $userActual->id)
                ->where('receta_id', $receta->id)
                ->lockForUpdate()
                ->first();

            if ($valoracion) {
                $valoracion->puntuacion = $puntuacion;
                $valoracion->save();
            } else {
                $valoracion = new Valoracion();
                $valoracion->usuario_id = $userActual->id;
                $valoracion->receta_id = $receta->id;
                $valoracion->puntuacion = $puntuacion;
                $valoracion->save();
            }

            // Recalcular agregados vigentes
            $promedio = $receta->valoraciones()->avg('puntuacion');
            $cantidad = (int) $receta->valoraciones()->count();

            return [
                'puntuacion' => $puntuacion,
                'valoracion_promedio' => $promedio !== null ? round((float) $promedio, 2) : null,
                'cantidad_valoraciones' => $cantidad,
            ];
        }, attempts: 3);
    }
}

