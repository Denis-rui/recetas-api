<?php

namespace App\Actions\Recetas;

use App\Models\Categoria;
use App\Models\Ingrediente;
use App\Models\Receta;
use App\Models\Subcategoria;
use App\Models\User;
use App\Rules\IdentificadorEntero;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

class ActualizarRecetaPublicada
{
    public function __construct(
        private ContenidoRevision $contenidoRevision,
        private GuardarImagenReceta $guardarImagen,
        private ImagenRevision $imagenRevision,
    ) {}

    /**
     * Actualiza directamente una receta publicada y vigente desde la administración web,
     * conservando su ID, autor original, fecha de publicación, favoritos y valoraciones.
     *
     * @param  array<string, mixed>  $datos
     *
     * @throws ValidationException
     */
    public function ejecutar(
        User $administrador,
        Receta $recetaOriginal,
        array $datos,
        UploadedFile|string|null $imagen = null,
    ): Receta {
        Gate::forUser($administrador)->authorize('administrarListado', Receta::class);
        Gate::forUser($administrador)->authorize('administrar', $recetaOriginal);

        $datos = $this->normalizarColecciones($this->contenidoRevision->normalizar($datos));

        $archivoNuevoCreado = null;

        try {
            return DB::transaction(function () use (
                $administrador,
                $recetaOriginal,
                $datos,
                $imagen,
                &$archivoNuevoCreado,
            ): Receta {
                $adminActual = User::query()->whereKey($administrador->id)->lockForUpdate()->first();
                Gate::forUser($adminActual)->authorize('administrarListado', Receta::class);

                $receta = Receta::withTrashed()->whereKey($recetaOriginal->id)->lockForUpdate()->firstOrFail();
                Gate::forUser($adminActual)->authorize('administrar', $receta);

                $validados = Validator::make($datos, [
                    'nombre' => ['required', 'string', 'max:150'],
                    'descripcion' => ['required', 'string', 'max:16000'],
                    'porciones' => ['required', 'integer', 'between:1,65535'],
                    'tiempo_preparacion' => ['required', 'integer', 'between:1,65535'],
                    'tips' => ['nullable', 'string', 'max:16000'],
                    'categoria_id' => ['bail', 'required', new IdentificadorEntero],
                    'subcategoria_id' => ['bail', 'required', new IdentificadorEntero],
                    'ingredientes' => ['required', 'array', 'list', 'min:1', 'max:500'],
                    'ingredientes.*' => ['required', 'array:ingrediente_id,cantidad,unidad,notas,orden'],
                    'ingredientes.*.ingrediente_id' => ['bail', 'required', new IdentificadorEntero, 'distinct'],
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
                    'array' => 'El campo :attribute tiene una estructura no válida o campos no permitidos.',
                    'list' => 'El campo :attribute debe ser una lista ordenada.',
                    'string' => 'El campo :attribute debe ser texto.',
                    'integer' => 'El campo :attribute debe ser un número entero.',
                    'numeric' => 'El campo :attribute debe ser un número.',
                    'between' => 'El campo :attribute debe estar entre :min y :max.',
                    'min' => 'El campo :attribute no alcanza el mínimo requerido (:min).',
                    'max' => 'El campo :attribute supera el máximo permitido (:max).',
                    'distinct' => 'El campo :attribute contiene valores repetidos.',
                    'present' => 'Falta el campo :attribute en los ingredientes enviados.',
                    'gt' => 'El campo :attribute debe ser mayor que :value.',
                    'decimal' => 'El campo :attribute admite hasta tres decimales.',
                ])->validate();

                $categoria = Categoria::query()
                    ->whereKey((int) $validados['categoria_id'])
                    ->sharedLock()
                    ->first(['id']);
                $subcategoria = Subcategoria::query()
                    ->whereKey((int) $validados['subcategoria_id'])
                    ->sharedLock()
                    ->first(['id', 'categoria_id']);

                if ($categoria === null) {
                    throw ValidationException::withMessages([
                        'categoria_id' => 'La categoría seleccionada no existe.',
                    ]);
                }

                if ($subcategoria === null) {
                    throw ValidationException::withMessages([
                        'subcategoria_id' => 'La subcategoría seleccionada no existe.',
                    ]);
                }

                if ((int) $subcategoria->categoria_id !== (int) $categoria->id) {
                    throw ValidationException::withMessages([
                        'subcategoria_id' => 'La subcategoría seleccionada no pertenece a la categoría indicada.',
                    ]);
                }

                $idsIngredientes = array_map('intval', array_column($validados['ingredientes'], 'ingrediente_id'));
                $totalIngredientesValidos = Ingrediente::query()
                    ->whereIn('id', $idsIngredientes)
                    ->orderBy('id')
                    ->sharedLock()
                    ->count();

                if ($totalIngredientesValidos !== count($idsIngredientes)) {
                    throw ValidationException::withMessages([
                        'ingredientes' => 'Uno o más ingredientes seleccionados no existen en el catálogo.',
                    ]);
                }

                if ($imagen instanceof UploadedFile) {
                    $resultado = $this->guardarImagen->ejecutar($imagen, $receta->id);
                    $archivoNuevoCreado = $resultado['ruta'];
                    $receta->imagen = $resultado['ruta'];
                } elseif (is_string($imagen) && $imagen !== '') {
                    if ($imagen !== $receta->imagen && $this->imagenRevision->localizar($imagen, $receta->id) === null) {
                        throw ValidationException::withMessages([
                            'imagen' => 'La imagen indicada no está disponible en el almacenamiento privado de esta receta.',
                        ]);
                    }
                    $receta->imagen = $imagen;
                } elseif (! is_string($receta->imagen) || $receta->imagen === '') {
                    throw ValidationException::withMessages([
                        'imagen' => 'La imagen de la receta es obligatoria.',
                    ]);
                }

                $receta->nombre = $validados['nombre'];
                $receta->descripcion = $validados['descripcion'];
                $receta->porciones = (int) $validados['porciones'];
                $receta->tiempo_preparacion = (int) $validados['tiempo_preparacion'];
                $receta->tips = $validados['tips'] ?? null;
                $receta->categoria_id = (int) $categoria->id;
                $receta->subcategoria_id = (int) $subcategoria->id;
                $receta->actualizado_por = $adminActual->id;
                $receta->version++;
                $receta->save();

                $pivotes = [];
                $ingredientesOrdenados = collect($validados['ingredientes'])->sortBy('orden')->values()->all();
                foreach ($ingredientesOrdenados as $ingrediente) {
                    $pivotes[(int) $ingrediente['ingrediente_id']] = Arr::except($ingrediente, 'ingrediente_id');
                }
                $receta->ingredientes()->sync($pivotes);

                $receta->pasos()->delete();
                $pasosOrdenados = collect($validados['pasos'])->sortBy('orden')->values()->all();
                $receta->pasos()->createMany($pasosOrdenados);

                return $receta->load(['categoria:id,nombre', 'subcategoria:id,nombre', 'ingredientes', 'pasos']);
            }, attempts: 3);
        } catch (Throwable $excepcion) {
            if ($archivoNuevoCreado !== null) {
                $this->guardarImagen->eliminarSiExiste($archivoNuevoCreado, $recetaOriginal->id);
            }

            throw $excepcion;
        }
    }

    /**
     * Completa claves opcionales (`cantidad`, `unidad`, `notas`, `orden`) cuando provienen de formularios web,
     * preservando valores explícitos para que la validación detecte estructuras u órdenes inválidos.
     *
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    private function normalizarColecciones(array $datos): array
    {
        if (isset($datos['ingredientes']) && is_array($datos['ingredientes']) && array_is_list($datos['ingredientes'])) {
            foreach ($datos['ingredientes'] as $indice => $ingrediente) {
                if (is_array($ingrediente)) {
                    if (! array_key_exists('cantidad', $ingrediente)) {
                        $ingrediente['cantidad'] = null;
                    } elseif ($ingrediente['cantidad'] === '') {
                        $ingrediente['cantidad'] = null;
                    }

                    if (! array_key_exists('unidad', $ingrediente)) {
                        $ingrediente['unidad'] = null;
                    } elseif ($ingrediente['unidad'] === '') {
                        $ingrediente['unidad'] = null;
                    }

                    if (! array_key_exists('notas', $ingrediente)) {
                        $ingrediente['notas'] = null;
                    } elseif ($ingrediente['notas'] === '') {
                        $ingrediente['notas'] = null;
                    }

                    if (! array_key_exists('orden', $ingrediente) || $ingrediente['orden'] === '' || $ingrediente['orden'] === null) {
                        $ingrediente['orden'] = $indice + 1;
                    }

                    $datos['ingredientes'][$indice] = $ingrediente;
                }
            }
        }

        if (isset($datos['pasos']) && is_array($datos['pasos']) && array_is_list($datos['pasos'])) {
            foreach ($datos['pasos'] as $indice => $paso) {
                if (is_array($paso)) {
                    if (! array_key_exists('orden', $paso) || $paso['orden'] === '' || $paso['orden'] === null) {
                        $paso['orden'] = $indice + 1;
                    }

                    $datos['pasos'][$indice] = $paso;
                }
            }
        }

        return $datos;
    }
}
