<?php

namespace App\Actions\Recetas;

use App\Models\Categoria;
use App\Models\Ingrediente;
use App\Models\SolicitudRevision;
use App\Models\Subcategoria;
use Illuminate\Validation\ValidationException;

class PrepararDetalleRevision
{
    public function __construct(
        private ContenidoRevision $contenido,
        private ProcesarRevision $revision,
        private ImagenRevision $imagenes,
    ) {}

    /** @return array<string, mixed> */
    public function ejecutar(SolicitudRevision $solicitud): array
    {
        $solicitud->loadMissing('solicitante', 'revisor', 'receta.ingredientes', 'receta.categoria', 'receta.subcategoria', 'receta.pasos');
        $bloqueo = $this->revision->motivoBloqueo($solicitud);
        $erroresContenido = [];
        $propuesta = null;
        try {
            $propuesta = $this->contenido->validar($solicitud->contenido, $solicitud->receta);
        } catch (ValidationException $exception) {
            $erroresContenido = $exception->validator?->errors()->all() ?? collect($exception->errors())->flatten()->all();
            $bloqueo ??= 'El contenido enviado contiene errores. No se puede aprobar hasta contar con una solicitud válida.';
        }
        $publicado = $solicitud->tipo === 'correccion' && $solicitud->receta->publicada_en !== null
            ? $this->contenido->vigente($solicitud->receta) : null;
        $categoriasIds = array_values(array_filter([
            $publicado['categoria_id'] ?? null,
            $propuesta['categoria_id'] ?? null,
        ], fn ($id) => $id !== null));
        $subcategoriasIds = array_values(array_filter([
            $publicado['subcategoria_id'] ?? null,
            $propuesta['subcategoria_id'] ?? null,
        ], fn ($id) => $id !== null));
        $ingredientesIds = array_merge(
            array_column($publicado['ingredientes'] ?? [], 'ingrediente_id'),
            array_column($propuesta['ingredientes'] ?? [], 'ingrediente_id'),
        );
        $categorias = Categoria::whereIn('id', $categoriasIds)->pluck('nombre', 'id')->all();
        $subcategorias = Subcategoria::whereIn('id', $subcategoriasIds)->pluck('nombre', 'id')->all();
        $ingredientes = Ingrediente::whereIn('id', $ingredientesIds)->pluck('nombre', 'id')->all();
        $diferencias = [];
        if ($publicado && $propuesta) {
            foreach ($propuesta as $campo => $valor) {
                $diferencias[$campo] = ($publicado[$campo] ?? null) != $valor;
            }
        }
        $imagenPublicada = $publicado && $this->imagenes->localizar($publicado['imagen'], $solicitud->receta_id)
            ? route('revision-recetas.imagen', [$solicitud, 'publicada']) : null;
        $imagenPropuesta = $propuesta
            ? route('revision-recetas.imagen', [$solicitud, 'propuesta']) : null;

        return compact('solicitud', 'propuesta', 'publicado', 'bloqueo', 'erroresContenido', 'categorias', 'subcategorias', 'ingredientes', 'diferencias', 'imagenPublicada', 'imagenPropuesta');
    }
}
