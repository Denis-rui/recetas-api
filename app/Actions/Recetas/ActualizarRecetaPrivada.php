<?php

namespace App\Actions\Recetas;

use App\Models\Categoria;
use App\Models\Ingrediente;
use App\Models\Receta;
use App\Models\SolicitudRevision;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Throwable;

class ActualizarRecetaPrivada
{
    public function __construct(
        private ContenidoRevision $contenidoRevision,
        private GuardarImagenReceta $guardarImagen,
        private ImagenRevision $imagenRevision,
    ) {}

    /**
     * Actualiza una receta privada propia garantizando comprobación de versión y ausencia de publicación pendiente.
     *
     * @param  array<string, mixed>  $datos
     *
     * @throws ValidationException|ConflictHttpException
     */
    public function ejecutar(User $autor, Receta $recetaOriginal, array $datos, UploadedFile|string|null $imagen = null): Receta
    {
        if (! $autor->estaActivo()) {
            throw ValidationException::withMessages([
                'autor' => 'La cuenta no se encuentra activa.',
            ]);
        }

        $datos = $this->contenidoRevision->normalizar($datos);

        $validador = Validator::make($datos, [
            'version' => ['required', 'integer', 'min:1'],
            'nombre' => ['required', 'string', 'max:150'],
            'descripcion' => ['required', 'string', 'max:16000'],
            'porciones' => ['required', 'integer', 'between:1,65535'],
            'tiempo_preparacion' => ['required', 'integer', 'between:1,65535'],
            'tips' => ['nullable', 'string', 'max:16000'],
            'categorias' => ['required', 'array', 'list', 'min:1', 'max:100'],
            'categorias.*' => ['required', 'integer', 'min:1', 'distinct'],
            'ingredientes' => ['required', 'array', 'list', 'min:1', 'max:500'],
            'ingredientes.*' => ['required', 'array:ingrediente_id,cantidad,unidad,notas,orden'],
            'ingredientes.*.ingrediente_id' => ['required', 'integer', 'min:1', 'distinct'],
            'ingredientes.*.cantidad' => ['present', 'nullable', 'numeric', 'gt:0', 'max:9999999.999', 'decimal:0,3'],
            'ingredientes.*.unidad' => ['present', 'nullable', 'string', 'max:50'],
            'ingredientes.*.notas' => ['present', 'nullable', 'string', 'max:16000'],
            'ingredientes.*.orden' => ['required', 'integer', 'between:1,65535', 'distinct'],
            'pasos' => ['required', 'array', 'list', 'min:1', 'max:500'],
            'pasos.*' => ['required', 'array:orden,instruccion'],
            'pasos.*.orden' => ['required', 'integer', 'between:1,65535', 'distinct'],
            'pasos.*.instruccion' => ['required', 'string', 'max:16000'],
        ], [
            'required' => 'El campo :attribute es obligatorio.',
            'array' => 'El campo :attribute tiene una estructura no válida.',
            'list' => 'El campo :attribute debe ser una lista.',
            'string' => 'El campo :attribute debe ser texto.',
            'integer' => 'El campo :attribute debe ser un número entero.',
            'numeric' => 'El campo :attribute debe ser numérico.',
            'between' => 'El campo :attribute debe estar entre :min y :max.',
            'min' => 'El campo :attribute no alcanza el mínimo requerido (:min).',
            'max' => 'El campo :attribute supera el máximo permitido (:max).',
            'distinct' => 'El campo :attribute contiene valores repetidos.',
            'present' => 'Falta el campo :attribute en los ingredientes enviados.',
            'gt' => 'El campo :attribute debe ser mayor que :value.',
            'decimal' => 'El campo :attribute admite hasta tres decimales.',
        ]);

        $validados = $validador->validate();

        $archivoNuevoCreado = null;
        $archivoAnterior = null;

        try {
            return DB::transaction(function () use ($autor, $recetaOriginal, $validados, $imagen, &$archivoNuevoCreado, &$archivoAnterior) {
                // Orden de bloqueos canónico: usuario primero
                $userActual = User::whereKey($autor->id)->lockForUpdate()->firstOrFail();
                if (! $userActual->estaActivo()) {
                    throw ValidationException::withMessages(['autor' => 'La cuenta no se encuentra activa.']);
                }

                // Bloqueo de la receta
                $receta = Receta::withTrashed()->whereKey($recetaOriginal->id)->lockForUpdate()->firstOrFail();

                if ($receta->creado_por !== $userActual->id) {
                    abort(403, 'No tiene permiso para editar esta receta.');
                }

                if ($receta->trashed()) {
                    throw ValidationException::withMessages(['receta' => 'Una receta eliminada no se puede editar.']);
                }

                if ($receta->publicada_en !== null) {
                    throw ValidationException::withMessages([
                        'receta' => 'Una receta publicada no se puede editar como privada. Debe enviar una propuesta de corrección.',
                    ]);
                }

                // Comprobar si existe publicación pendiente
                $solicitudPendiente = SolicitudRevision::where('receta_id', $receta->id)
                    ->where('estado', 'pendiente')
                    ->lockForUpdate()
                    ->exists();

                if ($solicitudPendiente) {
                    throw ValidationException::withMessages([
                        'receta' => 'Una receta con publicación pendiente no puede editarse. Debe cancelar primero la solicitud.',
                    ]);
                }

                // Detección de edición desactualizada por versión (HTTP 409 Conflict)
                if ($receta->version !== (int) $validados['version']) {
                    throw new ConflictHttpException('Conflicto de versión. La receta ha sido modificada por otra operación. Recargue los datos antes de continuar.');
                }

                // Validar existencia de categorías e ingredientes con sharedLock
                $categoriasQuery = Categoria::whereIn('id', $validados['categorias'])->orderBy('id')->sharedLock();
                $ingredientesQuery = Ingrediente::whereIn('id', array_column($validados['ingredientes'], 'ingrediente_id'))->orderBy('id')->sharedLock();

                if ($categoriasQuery->count() !== count($validados['categorias'])
                    || $ingredientesQuery->count() !== count($validados['ingredientes'])) {
                    throw ValidationException::withMessages([
                        'contenido' => 'La receta contiene categorías o ingredientes que no existen.',
                    ]);
                }

                $archivoAnterior = $receta->imagen;

                // Si se envía una nueva imagen
                if ($imagen instanceof UploadedFile) {
                    $resultado = $this->guardarImagen->ejecutar($imagen, $receta->id);
                    $archivoNuevoCreado = $resultado['ruta'];
                    $receta->imagen = $resultado['ruta'];
                } elseif (is_string($imagen) && $imagen !== $receta->imagen) {
                    $localizada = $this->imagenRevision->localizar($imagen, $receta->id);
                    if ($localizada === null) {
                        throw ValidationException::withMessages([
                            'imagen' => 'La imagen indicada no está disponible para esta receta.',
                        ]);
                    }
                    $receta->imagen = $imagen;
                }

                // Actualizar atributos de la receta
                $receta->nombre = $validados['nombre'];
                $receta->descripcion = $validados['descripcion'];
                $receta->porciones = (int) $validados['porciones'];
                $receta->tiempo_preparacion = (int) $validados['tiempo_preparacion'];
                $receta->tips = $validados['tips'] ?? null;
                $receta->actualizado_por = $userActual->id;
                $receta->version++;
                $receta->save();

                // Sincronizar categorías
                $categoriasOrdenadas = array_map('intval', $validados['categorias']);
                sort($categoriasOrdenadas);
                $receta->categorias()->sync($categoriasOrdenadas);

                // Sincronizar ingredientes
                $pivotes = [];
                $ingredientesOrdenados = collect($validados['ingredientes'])->sortBy('orden')->values()->all();
                foreach ($ingredientesOrdenados as $ingrediente) {
                    $pivotes[$ingrediente['ingrediente_id']] = Arr::except($ingrediente, 'ingrediente_id');
                }
                $receta->ingredientes()->sync($pivotes);

                // Recrear pasos
                $receta->pasos()->delete();
                $pasosOrdenados = collect($validados['pasos'])->sortBy('orden')->values()->all();
                $receta->pasos()->createMany($pasosOrdenados);

                return $receta->loadMissing(['categorias', 'ingredientes', 'pasos']);
            }, attempts: 3);
        } catch (Throwable $e) {
            if ($archivoNuevoCreado !== null) {
                $this->guardarImagen->eliminarSiExiste($archivoNuevoCreado, $recetaOriginal->id);
            }

            throw $e;
        }
    }
}
