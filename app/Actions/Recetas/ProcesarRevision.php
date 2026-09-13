<?php

namespace App\Actions\Recetas;

use App\Models\Receta;
use App\Models\SolicitudRevision;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ProcesarRevision
{
    public function __construct(private ContenidoRevision $contenido) {}

    public function ejecutar(User $administrador, SolicitudRevision $solicitud, string $decision, ?string $motivo = null): SolicitudRevision
    {
        Gate::forUser($administrador)->authorize('decidir', $solicitud);
        Validator::make(['decision' => $decision], ['decision' => ['required', 'in:aprobar,rechazar']])->validate();

        return DB::transaction(function () use ($administrador, $solicitud, $decision, $motivo) {
            $referencia = SolicitudRevision::findOrFail($solicitud->id);
            /** Orden global: cuentas por id, receta, solicitud, categorías, ingredientes.
             * Los futuros escritores del móvil deberán respetar este mismo orden.
             * Las lecturas con bloqueo recuperan el estado vigente incluso en REPEATABLE READ.
             */
            $cuentas = User::whereIn('id', [$administrador->id, $referencia->solicitado_por])
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $adminActual = $cuentas->get($administrador->id);
            abort_unless($adminActual, 403);
            $receta = Receta::withTrashed()->whereKey($referencia->receta_id)->lockForUpdate()->firstOrFail();
            $actual = SolicitudRevision::whereKey($referencia->id)->lockForUpdate()->firstOrFail();
            if ($actual->receta_id !== $receta->id || $actual->solicitado_por !== $referencia->solicitado_por) {
                throw ValidationException::withMessages(['revision' => 'La solicitud cambió. La pantalla se ha actualizado; revisa su estado.']);
            }
            $actual->setRelation('receta', $receta);
            $actual->setRelation('solicitante', $cuentas->get($actual->solicitado_por));
            Gate::forUser($adminActual)->authorize('decidir', $actual);
            if ($actual->estado !== 'pendiente') {
                throw ValidationException::withMessages(['revision' => 'Esta solicitud ya fue procesada o cancelada. La pantalla se ha actualizado.']);
            }

            if ($decision === 'aprobar') {
                if ($bloqueo = $this->motivoBloqueo($actual)) {
                    throw ValidationException::withMessages(['revision' => $bloqueo]);
                }
                $datos = $this->contenido->validar($actual->contenido, $receta, true);
                $receta->fill(Arr::only($datos, ['nombre', 'descripcion', 'imagen', 'porciones', 'tiempo_preparacion', 'tips']));
                $receta->actualizado_por = $adminActual->id;
                $receta->publicada_en ??= now();
                $receta->version++;
                $receta->save();

                $pivotes = [];
                foreach ($datos['ingredientes'] as $ingrediente) {
                    $pivotes[$ingrediente['ingrediente_id']] = Arr::except($ingrediente, 'ingrediente_id');
                }
                $receta->categorias()->sync($datos['categorias']);
                $receta->ingredientes()->sync($pivotes);
                $receta->pasos()->delete();
                $receta->pasos()->createMany($datos['pasos']);
            } else {
                $motivo = $this->contenido->normalizar($motivo);
                Validator::make(['motivo_rechazo' => $motivo], [
                    'motivo_rechazo' => ['required', 'string', 'max:16000'],
                ], [
                    'motivo_rechazo.required' => 'Escribe un motivo de rechazo con contenido real.',
                    'motivo_rechazo.max' => 'El motivo no puede superar los 16000 caracteres.',
                ])->validate();
            }
            $actual->estado = $decision === 'aprobar' ? 'aprobada' : 'rechazada';
            $actual->motivo_rechazo = $decision === 'rechazar' ? $motivo : null;
            $actual->revisado_por = $adminActual->id;
            $actual->revisada_en = now();
            $actual->save();

            return $actual;
        }, attempts: 3);
    }

    public function motivoBloqueo(SolicitudRevision $solicitud): ?string
    {
        if ($solicitud->estado !== 'pendiente') {
            return 'La solicitud ya fue procesada o cancelada.';
        }
        if (! $solicitud->solicitante?->estaActivo()) {
            return 'El autor está deshabilitado. Su solicitud permanece pendiente hasta que se reactive la cuenta.';
        }
        $receta = $solicitud->receta;
        if (! $receta || $receta->trashed()) {
            return 'La receta fue eliminada y no se puede aprobar.';
        }
        if ($receta->version !== $solicitud->version_base) {
            return 'La propuesta está basada en una versión desactualizada de la receta.';
        }
        if (($solicitud->tipo === 'publicacion' && $receta->publicada_en !== null)
            || ($solicitud->tipo === 'correccion' && $receta->publicada_en === null)
            || ! in_array($solicitud->tipo, ['publicacion', 'correccion'], true)) {
            return 'El tipo de solicitud no corresponde al estado actual de publicación.';
        }

        return null;
    }
}
