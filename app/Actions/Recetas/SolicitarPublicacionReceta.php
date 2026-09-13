<?php

namespace App\Actions\Recetas;

use App\Models\Receta;
use App\Models\SolicitudRevision;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SolicitarPublicacionReceta
{
    public function __construct(
        private ContenidoRevision $contenidoRevision,
    ) {}

    /**
     * Procesa la solicitud de publicación de una receta propia.
     * Si el usuario es administrador activo, se publica directamente sin crear solicitud ficticia.
     * Si es usuario normal, crea una SolicitudRevision en estado pendiente.
     *
     * @return array{tipo: 'directa'|'solicitud', receta: Receta, solicitud?: SolicitudRevision}
     *
     * @throws ValidationException
     */
    public function ejecutar(User $usuario, Receta $recetaOriginal, string $claveIdempotencia): array
    {
        if (! $usuario->estaActivo()) {
            throw ValidationException::withMessages([
                'autor' => 'La cuenta no se encuentra activa.',
            ]);
        }

        return DB::transaction(function () use ($usuario, $recetaOriginal, $claveIdempotencia) {
            // Orden de bloqueos canónico
            $userActual = User::whereKey($usuario->id)->lockForUpdate()->firstOrFail();
            if (! $userActual->estaActivo()) {
                throw ValidationException::withMessages(['autor' => 'La cuenta no se encuentra activa.']);
            }

            $receta = Receta::withTrashed()->whereKey($recetaOriginal->id)->lockForUpdate()->firstOrFail();

            if ($receta->creado_por !== $userActual->id) {
                abort(403, 'No tiene permiso para solicitar la publicación de esta receta.');
            }

            if ($receta->trashed()) {
                throw ValidationException::withMessages(['receta' => 'Una receta eliminada no se puede publicar.']);
            }

            if ($receta->publicada_en !== null) {
                throw ValidationException::withMessages(['receta' => 'La receta ya se encuentra publicada.']);
            }

            // Comprobar idempotencia antes de crear nueva solicitud
            $solicitudExistente = SolicitudRevision::where('solicitado_por', $userActual->id)
                ->where('clave_idempotencia', $claveIdempotencia)
                ->lockForUpdate()
                ->first();

            // Extraer y validar el contenido actual de la receta
            $contenido = $this->contenidoRevision->vigente($receta);
            $contenidoValidado = $this->contenidoRevision->validar($contenido, $receta, true);

            if ($solicitudExistente) {
                if ($solicitudExistente->receta_id === $receta->id
                    && $solicitudExistente->tipo === 'publicacion'
                    && $solicitudExistente->contenido === $contenidoValidado) {
                    return [
                        'tipo' => 'solicitud',
                        'receta' => $receta,
                        'solicitud' => $solicitudExistente,
                        'reintento' => true,
                    ];
                }

                throw ValidationException::withMessages([
                    'clave_idempotencia' => 'La clave de idempotencia ya fue utilizada para otra solicitud distinta.',
                ]);
            }

            // Comprobar que no exista otra solicitud pendiente para esta misma receta
            $pendienteExistente = SolicitudRevision::where('receta_id', $receta->id)
                ->where('estado', 'pendiente')
                ->lockForUpdate()
                ->exists();

            if ($pendienteExistente) {
                throw ValidationException::withMessages([
                    'receta' => 'Ya existe una solicitud pendiente de revisión para esta receta.',
                ]);
            }

            // Bifurcación por rol
            if ($userActual->esAdministrador()) {
                // Publicación directa por administrador activo: sin solicitud ficticia
                $receta->publicada_en = now();
                $receta->actualizado_por = $userActual->id;
                $receta->version++;
                $receta->save();

                return [
                    'tipo' => 'directa',
                    'receta' => $receta->fresh(['categorias', 'ingredientes', 'pasos']),
                ];
            }

            // Usuario normal: crea solicitud de revisión pendiente
            $solicitud = new SolicitudRevision;
            $solicitud->receta_id = $receta->id;
            $solicitud->solicitado_por = $userActual->id;
            $solicitud->tipo = 'publicacion';
            $solicitud->estado = 'pendiente';
            $solicitud->version_base = $receta->version;
            $solicitud->contenido = $contenidoValidado;
            $solicitud->clave_idempotencia = $claveIdempotencia;
            $solicitud->save();

            return [
                'tipo' => 'solicitud',
                'receta' => $receta,
                'solicitud' => $solicitud,
            ];
        }, attempts: 3);
    }
}
