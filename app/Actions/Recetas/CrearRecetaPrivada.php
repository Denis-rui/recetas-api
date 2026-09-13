<?php

namespace App\Actions\Recetas;

use App\Models\Categoria;
use App\Models\Ingrediente;
use App\Models\Receta;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

class CrearRecetaPrivada
{
    public function __construct(
        private ContenidoRevision $contenidoRevision,
        private GuardarImagenReceta $guardarImagen,
        private ImagenRevision $imagenRevision,
    ) {}

    /**
     * Guarda una receta privada completa vinculada a la cuenta autora.
     *
     * @param  array<string, mixed>  $datos
     * @throws ValidationException
     */
    public function ejecutar(User $autor, array $datos, UploadedFile|string|null $imagen): Receta
    {
        if (! $autor->estaActivo()) {
            throw ValidationException::withMessages([
                'autor' => 'La cuenta no se encuentra activa.',
            ]);
        }

        // Normalizar textos
        $datos = $this->contenidoRevision->normalizar($datos);

        $validador = Validator::make($datos, [
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

        if ($imagen === null) {
            throw ValidationException::withMessages([
                'imagen' => 'Debe proporcionar una imagen para la receta.',
            ]);
        }

        $archivoCreado = null;
        $recetaIdCreada = null;

        try {
            return DB::transaction(function () use ($autor, $validados, $imagen, &$archivoCreado, &$recetaIdCreada) {
                // Orden de bloqueos: primero la cuenta del usuario
                $userActual = User::whereKey($autor->id)->lockForUpdate()->firstOrFail();
                if (! $userActual->estaActivo()) {
                    throw ValidationException::withMessages(['autor' => 'La cuenta no se encuentra activa.']);
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

                // Crear el registro base de la receta privada
                $receta = new Receta();
                $receta->nombre = $validados['nombre'];
                $receta->descripcion = $validados['descripcion'];
                $receta->imagen = 'pendiente.png'; // Temporal antes de asignar el archivo real
                $receta->porciones = (int) $validados['porciones'];
                $receta->tiempo_preparacion = (int) $validados['tiempo_preparacion'];
                $receta->tips = $validados['tips'] ?? null;
                $receta->creado_por = $userActual->id;
                $receta->version = 1;
                $receta->publicada_en = null; // Privada
                $receta->save();

                $recetaIdCreada = $receta->id;

                // Almacenar o validar imagen
                if ($imagen instanceof UploadedFile) {
                    $resultadoImagen = $this->guardarImagen->ejecutar($imagen, $receta->id);
                    $archivoCreado = $resultadoImagen['ruta'];
                    $receta->imagen = $resultadoImagen['ruta'];
                } elseif (is_string($imagen)) {
                    $localizada = $this->imagenRevision->localizar($imagen, $receta->id);
                    if ($localizada === null) {
                        throw ValidationException::withMessages([
                            'imagen' => 'La ruta de imagen proporcionada no es válida para esta receta.',
                        ]);
                    }
                    $receta->imagen = $imagen;
                }
                $receta->save();

                // Asociar categorías
                $categoriasOrdenadas = array_map('intval', $validados['categorias']);
                sort($categoriasOrdenadas);
                $receta->categorias()->sync($categoriasOrdenadas);

                // Asociar ingredientes con datos pivot
                $pivotes = [];
                $ingredientesOrdenados = collect($validados['ingredientes'])->sortBy('orden')->values()->all();
                foreach ($ingredientesOrdenados as $ingrediente) {
                    $pivotes[$ingrediente['ingrediente_id']] = Arr::except($ingrediente, 'ingrediente_id');
                }
                $receta->ingredientes()->sync($pivotes);

                // Crear pasos ordenados
                $pasosOrdenados = collect($validados['pasos'])->sortBy('orden')->values()->all();
                $receta->pasos()->createMany($pasosOrdenados);

                return $receta->loadMissing(['categorias', 'ingredientes', 'pasos']);
            }, attempts: 3);
        } catch (Throwable $e) {
            if ($archivoCreado !== null && $recetaIdCreada !== null) {
                $this->guardarImagen->eliminarSiExiste($archivoCreado, $recetaIdCreada);
            }

            throw $e;
        }
    }
}

