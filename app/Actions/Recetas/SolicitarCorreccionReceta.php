<?php

namespace App\Actions\Recetas;

use App\Models\Receta;
use App\Models\SolicitudRevision;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SolicitarCorreccionReceta
{
    public function __construct(
        private ContenidoRevision $contenidoRevision,
        private IdempotenciaReceta $idempotencia,
    ) {}

    /**
     * Procesa una propuesta de corrección sobre una receta publicada.
     * Toda corrección, incluida la de un administrador, genera una solicitud pendiente.
     *
     * @param  array<string, mixed>  $propuesta
     * @return array{tipo: 'solicitud', receta: Receta, solicitud: SolicitudRevision}
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

            $peticion = ['version_base' => $versionBase, 'contenido' => $propuesta];
            $reintento = $this->idempotencia->recuperar($userActual, $receta, $claveIdempotencia, 'correccion', $peticion);
            if ($reintento !== null) {
                return $reintento;
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
            if (! array_key_exists('imagen', $propuesta)) {
                $propuesta['imagen'] = $receta->imagen;
            }
            $propuestaValidada = $this->contenidoRevision->validar($propuesta, $receta, true);

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

            $solicitud = new SolicitudRevision;
            $solicitud->receta_id = $receta->id;
            $solicitud->solicitado_por = $userActual->id;
            $solicitud->tipo = 'correccion';
            $solicitud->estado = 'pendiente';
            $solicitud->version_base = $receta->version;
            $solicitud->contenido = $propuestaValidada;
            $solicitud->clave_idempotencia = $claveIdempotencia;
            $solicitud->save();
            $this->idempotencia->registrar($userActual, $receta, $claveIdempotencia, 'correccion', $peticion, $solicitud);

            return [
                'tipo' => 'solicitud',
                'receta' => $receta,
                'solicitud' => $solicitud,
            ];
        }, attempts: 3);
    }
}
