<?php

namespace App\Actions\Recetas;

use App\Models\Receta;
use App\Models\SolicitudRevision;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CancelarSolicitudRevision
{
    /**
     * Cancela una solicitud propia mientras se encuentre pendiente.
     * Permite reintentos idempotentes si la solicitud ya estaba cancelada.
     *
     * @throws ValidationException
     */
    public function ejecutar(User $usuario, SolicitudRevision $solicitudReferencia): SolicitudRevision
    {
        if (! $usuario->estaActivo()) {
            throw ValidationException::withMessages([
                'autor' => 'La cuenta no se encuentra activa.',
            ]);
        }

        return DB::transaction(function () use ($usuario, $solicitudReferencia) {
            // Orden canónico de bloqueos: usuario, receta, solicitud
            $userActual = User::whereKey($usuario->id)->lockForUpdate()->firstOrFail();
            if (! $userActual->estaActivo()) {
                throw ValidationException::withMessages(['autor' => 'La cuenta no se encuentra activa.']);
            }

            $receta = Receta::withTrashed()->whereKey($solicitudReferencia->receta_id)->lockForUpdate()->firstOrFail();
            $solicitud = SolicitudRevision::whereKey($solicitudReferencia->id)->lockForUpdate()->firstOrFail();

            if ($solicitud->solicitado_por !== $userActual->id) {
                abort(403, 'No tiene permiso para cancelar esta solicitud.');
            }

            // Si ya está cancelada, responde de forma idempotente consistente
            if ($solicitud->estado === 'cancelada') {
                return $solicitud;
            }

            // No permite cancelar estados terminales (aprobada o rechazada)
            if (in_array($solicitud->estado, ['aprobada', 'rechazada'], true)) {
                throw ValidationException::withMessages([
                    'solicitud' => "No se puede cancelar una solicitud que ya fue {$solicitud->estado}.",
                ]);
            }

            if ($solicitud->estado !== 'pendiente') {
                throw ValidationException::withMessages([
                    'solicitud' => 'El estado actual de la solicitud no permite cancelarla.',
                ]);
            }

            $solicitud->estado = 'cancelada';
            $solicitud->cancelada_en = now();
            $solicitud->save();

            return $solicitud;
        }, attempts: 3);
    }
}
