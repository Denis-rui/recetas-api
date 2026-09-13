<?php

namespace App\Actions\Recetas;

use App\Models\Receta;
use App\Models\SolicitudRevision;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SolicitarCorreccionReceta
{
    public function __construct(
        private ContenidoRevision $contenidoRevision,
    ) {}

    /**
     * Procesa una propuesta de corrección sobre una receta publicada.
     * Si es un administrador activo, aplica directamente la corrección menor sobre la receta.
     * Si es un usuario normal, genera una SolicitudRevision de tipo 'correccion' en estado pendiente.
     *
     * @param  array<string, mixed>  $propuesta
     * @return array{tipo: 'directa'|'solicitud', receta: Receta, solicitud?: SolicitudRevision}
     *
     * @throws ValidationException
     */
    public function ejecutar(User $usuario, Receta $recetaOriginal, array $propuesta, int $versionBase, string $claveIdempotencia): array
    {
        if (! $usuario->estaActivo()) {
            throw ValidationException::withMessages([
                'autor' => 'La cuenta no se encuentra activa.',
            ]);
        }

        return DB::transaction(function () use ($usuario, $recetaOriginal, $propuesta, $versionBase, $claveIdempotencia) {
            // Orden de bloqueos canónico
            $userActual = User::whereKey($usuario->id)->lockForUpdate()->firstOrFail();
            if (! $userActual->estaActivo()) {
                throw ValidationException::withMessages(['autor' => 'La cuenta no se encuentra activa.']);
            }

            $receta = Receta::withTrashed()->whereKey($recetaOriginal->id)->lockForUpdate()->firstOrFail();

            if ($receta->creado_por !== $userActual->id) {
                abort(403, 'No tiene permiso para proponer correcciones a esta receta.');
            }

            if ($receta->trashed()) {
                throw ValidationException::withMessages(['receta' => 'Una receta eliminada no se puede corregir.']);
            }

            if ($receta->publicada_en === null) {
                throw ValidationException::withMessages(['receta' => 'Solo se pueden enviar correcciones a recetas que ya están publicadas.']);
            }

            if ($receta->version !== $versionBase) {
                throw ValidationException::withMessages([
                    'version_base' => 'La propuesta está basada en una versión desactualizada de la receta.',
                ]);
            }

            // Validar la propuesta con ContenidoRevision
            $propuestaValidada = $this->contenidoRevision->validar($propuesta, $receta, true);

            // Comprobar idempotencia
            $solicitudExistente = SolicitudRevision::where('solicitado_por', $userActual->id)
                ->where('clave_idempotencia', $claveIdempotencia)
                ->lockForUpdate()
                ->first();

            if ($solicitudExistente) {
                if ($solicitudExistente->receta_id === $receta->id
                    && $solicitudExistente->tipo === 'correccion'
                    && $solicitudExistente->contenido === $propuestaValidada) {
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
                // El administrador aplica directamente su corrección menor
                $receta->fill(Arr::only($propuestaValidada, ['nombre', 'descripcion', 'imagen', 'porciones', 'tiempo_preparacion', 'tips']));
                $receta->actualizado_por = $userActual->id;
                $receta->version++;
                $receta->save();

                // Sincronizar relaciones
                $receta->categorias()->sync($propuestaValidada['categorias']);

                $pivotes = [];
                foreach ($propuestaValidada['ingredientes'] as $ingrediente) {
                    $pivotes[$ingrediente['ingrediente_id']] = Arr::except($ingrediente, 'ingrediente_id');
                }
                $receta->ingredientes()->sync($pivotes);

                $receta->pasos()->delete();
                $receta->pasos()->createMany($propuestaValidada['pasos']);

                return [
                    'tipo' => 'directa',
                    'receta' => $receta->fresh(['categorias', 'ingredientes', 'pasos']),
                ];
            }

            // Usuario normal: guarda propuesta separada en SolicitudRevision pendiente
            $solicitud = new SolicitudRevision();
            $solicitud->receta_id = $receta->id;
            $solicitud->solicitado_por = $userActual->id;
            $solicitud->tipo = 'correccion';
            $solicitud->estado = 'pendiente';
            $solicitud->version_base = $receta->version;
            $solicitud->contenido = $propuestaValidada;
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
