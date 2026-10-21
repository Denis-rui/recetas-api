<?php

namespace App\Actions\Recetas;

use App\Models\Categoria;
use App\Models\Ingrediente;
use App\Models\Receta;
use App\Models\Subcategoria;
use App\Rules\IdentificadorEntero;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ContenidoRevision
{
    public function __construct(private ImagenRevision $imagenes) {}

    /** @return array<string, mixed> */
    public function validar(mixed $contenido, Receta $receta, bool $bloquearReferencias = false): array
    {
        $contenido = $this->normalizar($contenido);
        $datos = Validator::make(['contenido' => $contenido], [
            'contenido' => ['required', 'array:nombre,descripcion,imagen,porciones,tiempo_preparacion,tips,categoria_id,subcategoria_id,ingredientes,pasos'],
            'contenido.nombre' => ['required', 'string', 'max:150'],
            'contenido.descripcion' => ['required', 'string', 'max:16000'],
            'contenido.imagen' => ['required', 'string', 'max:2048'],
            'contenido.porciones' => ['required', 'integer', 'between:1,65535'],
            'contenido.tiempo_preparacion' => ['required', 'integer', 'between:1,65535'],
            'contenido.tips' => ['nullable', 'string', 'max:16000'],
            'contenido.categoria_id' => ['required', new IdentificadorEntero],
            'contenido.subcategoria_id' => ['required', new IdentificadorEntero],
            'contenido.ingredientes' => ['required', 'array', 'list', 'min:1', 'max:500'],
            'contenido.ingredientes.*' => ['required', 'array:ingrediente_id,cantidad,unidad,notas,orden'],
            'contenido.ingredientes.*.ingrediente_id' => ['required', new IdentificadorEntero, 'distinct'],
            'contenido.ingredientes.*.cantidad' => ['present', 'nullable', 'numeric', 'gt:0', 'max:9999999.999', 'decimal:0,3'],
            'contenido.ingredientes.*.unidad' => ['present', 'nullable', 'string', 'max:50'],
            'contenido.ingredientes.*.notas' => ['present', 'nullable', 'string', 'max:16000'],
            'contenido.ingredientes.*.orden' => ['required', 'integer', 'between:1,65535', 'distinct'],
            'contenido.pasos' => ['required', 'array', 'list', 'min:1', 'max:500'],
            'contenido.pasos.*' => ['required', 'array:orden,instruccion'],
            'contenido.pasos.*.orden' => ['required', 'integer', 'between:1,65535', 'distinct'],
            'contenido.pasos.*.instruccion' => ['required', 'string', 'max:16000'],
        ], [
            'required' => 'El campo :attribute debe tener contenido.',
            'array' => 'El campo :attribute tiene una estructura no válida o campos no permitidos.',
            'list' => 'El campo :attribute debe ser una lista.',
            'string' => 'El campo :attribute debe ser texto.',
            'integer' => 'El campo :attribute debe ser un número entero.',
            'numeric' => 'El campo :attribute debe ser un número.',
            'between' => 'El campo :attribute debe estar entre :min y :max.',
            'min' => 'El campo :attribute no alcanza el mínimo permitido (:min).',
            'max' => 'El campo :attribute supera el máximo permitido (:max).',
            'distinct' => 'El campo :attribute contiene valores repetidos.',
            'present' => 'Falta el campo :attribute en el contenido enviado.',
            'gt' => 'El campo :attribute debe ser mayor que :value.',
            'decimal' => 'El campo :attribute admite hasta tres decimales.',
        ])->validate()['contenido'];

        $categoriaQuery = Categoria::whereKey((int) $datos['categoria_id']);
        $subcategoriaQuery = Subcategoria::whereKey((int) $datos['subcategoria_id']);
        $ingredientes = Ingrediente::whereIn('id', array_column($datos['ingredientes'], 'ingrediente_id'))->orderBy('id');
        if ($bloquearReferencias) {
            $categoriaQuery->sharedLock();
            $subcategoriaQuery->sharedLock();
            $ingredientes->sharedLock();
        }
        $categoria = $categoriaQuery->first(['id']);
        $subcategoria = $subcategoriaQuery->first(['id', 'categoria_id']);
        if ($categoria === null || $subcategoria === null
            || $ingredientes->get(['id'])->count() !== count($datos['ingredientes'])) {
            throw ValidationException::withMessages(['contenido' => 'La propuesta contiene categoría, subcategoría o ingredientes que ya no existen.']);
        }
        if ((int) $subcategoria->categoria_id !== (int) $categoria->id) {
            throw ValidationException::withMessages(['contenido.subcategoria_id' => 'La subcategoría seleccionada no pertenece a la categoría indicada.']);
        }
        if ($this->imagenes->localizar($datos['imagen'], $receta->id) === null) {
            throw ValidationException::withMessages(['contenido.imagen' => 'La imagen no está disponible en el almacenamiento privado de esta receta.']);
        }
        $datos['tips'] = $datos['tips'] ?? null;
        $datos['categoria_id'] = (int) $datos['categoria_id'];
        $datos['subcategoria_id'] = (int) $datos['subcategoria_id'];
        $datos['ingredientes'] = collect($datos['ingredientes'])->sortBy('orden')->values()->all();
        $datos['pasos'] = collect($datos['pasos'])->sortBy('orden')->values()->all();

        return $datos;
    }

    /** @return array<string, mixed> */
    public function vigente(Receta $receta): array
    {
        $receta->loadMissing('ingredientes', 'pasos');

        return [
            ...$receta->only(['nombre', 'descripcion', 'imagen', 'porciones', 'tiempo_preparacion', 'tips']),
            'categoria_id' => $receta->categoria_id !== null ? (int) $receta->categoria_id : null,
            'subcategoria_id' => $receta->subcategoria_id !== null ? (int) $receta->subcategoria_id : null,
            'ingredientes' => $receta->ingredientes->map(fn (Ingrediente $ingrediente) => [
                'ingrediente_id' => $ingrediente->id, 'cantidad' => $ingrediente->pivot->cantidad,
                'unidad' => $ingrediente->pivot->unidad, 'notas' => $ingrediente->pivot->notas, 'orden' => $ingrediente->pivot->orden,
            ])->all(),
            'pasos' => $receta->pasos->map(fn ($paso) => $paso->only('orden', 'instruccion'))->all(),
        ];
    }

    public function normalizar(mixed $valor): mixed
    {
        if (is_string($valor)) {
            return preg_replace('/\\A[\\s\\p{Z}\\p{Cf}]+|[\\s\\p{Z}\\p{Cf}]+\\z/u', '', $valor) ?? $valor;
        }
        if (is_array($valor)) {
            foreach ($valor as $clave => $elemento) {
                if (! in_array($clave, ['categoria_id', 'subcategoria_id', 'categorias', 'ingrediente_id'], true)) {
                    $valor[$clave] = $this->normalizar($elemento);
                }
            }

            return $valor;
        }

        return $valor;
    }
}
