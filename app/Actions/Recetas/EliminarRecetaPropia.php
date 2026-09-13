<?php

namespace App\Actions\Recetas;

use App\Models\Receta;
use App\Models\SolicitudRevision;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EliminarRecetaPropia
{
    /**
     * Realiza la eliminación lógica de una receta propia, registrando auditoría
     * y cancelando automáticamente cualquier solicitud abierta de revisión (Opción A).
     *
     * @throws ValidationException
     */
    public function ejecutar(User $usuario, Receta $recetaOriginal): Receta
    {
        if (! $usuario->estaActivo()) {
            throw ValidationException::withMessages([
                'autor' => 'La cuenta no se encuentra activa.',
            ]);
        }

        return DB::transaction(function () use ($usuario, $recetaOriginal) {
            // Orden canónico de bloqueos: usuario, receta, solicitudes
            $userActual = User::whereKey($usuario->id)->lockForUpdate()->firstOrFail();
            if (! $userActual->estaActivo()) {
                throw ValidationException::withMessages(['autor' => 'La cuenta no se encuentra activa.']);
            }

            $receta = Receta::withTrashed()->whereKey($recetaOriginal->id)->lockForUpdate()->firstOrFail();

            if ($receta->creado_por !== $userActual->id) {
                abort(403, 'No tiene permiso para eliminar esta receta.');
            }

            if ($receta->trashed()) {
                throw ValidationException::withMessages([
                    'receta' => 'La receta ya se encuentra eliminada.',
                ]);
            }

            // Cancelar automáticamente cualquier solicitud pendiente asociada (Opción A confirmada)
            $solicitudesAbiertas = SolicitudRevision::where('receta_id', $receta->id)
                ->where('estado', 'pendiente')
                ->lockForUpdate()
                ->get();

            foreach ($solicitudesAbiertas as $solicitud) {
                $solicitud->estado = 'cancelada';
                $solicitud->cancelada_en = now();
                $solicitud->save();
            }

            // Registrar auditoría de eliminación por el autor
            $receta->eliminado_por = $userActual->id;
            $receta->tipo_eliminacion = 'autor';
            $receta->save();

            // Soft-delete
            $receta->delete();

            return $receta;
        }, attempts: 3);
    }
}

