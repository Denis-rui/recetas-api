<?php

namespace App\Actions\Recetas;

use App\Models\OperacionReceta;
use App\Models\Receta;
use App\Models\SolicitudRevision;
use App\Models\User;
use App\Rules\IdentificadorEntero;
use Illuminate\Validation\ValidationException;

class IdempotenciaReceta
{
    public function __construct(private ContenidoRevision $contenidoRevision) {}

    /**
     * Se invoca dentro de la transacción, con el autor y la receta bloqueados.
     * Recupera la identidad del resultado y su estado actual, sin repetir efectos.
     *
     * @param  array<string, mixed>  $peticion
     * @return array{tipo: string, receta: Receta, solicitud?: SolicitudRevision, reintento: bool}|null
     */
    public function recuperar(User $usuario, Receta $receta, string $clave, string $tipo, array $peticion): ?array
    {
        $operacion = OperacionReceta::where('usuario_id', $usuario->id)
            ->where('clave_idempotencia', $clave)->lockForUpdate()->first();

        if ($operacion) {
            if ($operacion->receta_id !== $receta->id || $operacion->operacion !== $tipo
                || ! hash_equals($operacion->huella_peticion, $this->huella($peticion))) {
                $this->rechazarReutilizacion();
            }

            if ($operacion->solicitud_revision_id !== null) {
                return ['tipo' => 'solicitud', 'receta' => $receta, 'solicitud' => SolicitudRevision::findOrFail($operacion->solicitud_revision_id), 'reintento' => true];
            }

            return ['tipo' => 'directa', 'receta' => $receta->load(['categorias', 'ingredientes', 'pasos']), 'reintento' => true];
        }

        $anterior = SolicitudRevision::where('solicitado_por', $usuario->id)
            ->where('clave_idempotencia', $clave)->lockForUpdate()->first();
        if ($anterior === null) {
            return null;
        }

        if ($anterior->receta_id !== $receta->id || $anterior->tipo !== $tipo) {
            $this->rechazarReutilizacion();
        }
        if ($tipo === 'correccion'
            && ($anterior->version_base !== $peticion['version_base']
                || $this->huellaContenidoHistorico($anterior->contenido) !== $this->huellaContenidoHistorico($peticion['contenido']))) {
            $this->rechazarReutilizacion();
        }

        return ['tipo' => 'solicitud', 'receta' => $receta, 'solicitud' => $anterior, 'reintento' => true];
    }

    /** @param array<string, mixed> $peticion */
    public function registrar(User $usuario, Receta $receta, string $clave, string $tipo, array $peticion, ?SolicitudRevision $solicitud = null): void
    {
        $operacion = new OperacionReceta;
        $operacion->usuario_id = $usuario->id;
        $operacion->receta_id = $receta->id;
        $operacion->clave_idempotencia = $clave;
        $operacion->operacion = $tipo;
        $operacion->huella_peticion = $this->huella($peticion);
        $operacion->solicitud_revision_id = $solicitud?->id;
        $operacion->save();
    }

    /** @param array<string, mixed> $peticion */
    private function huella(array $peticion): string
    {
        return hash('sha256', json_encode($this->ordenar($this->contenidoRevision->normalizar($peticion)), JSON_THROW_ON_ERROR));
    }

    /**
     * Reproduce únicamente las transformaciones del contenido guardado antes del registro
     * de operaciones. No consulta referencias ni archivos que pudieron cambiar después.
     *
     * @param  array<string, mixed>  $contenido
     */
    private function huellaContenidoHistorico(array $contenido): string
    {
        $contenido = $this->contenidoRevision->normalizar($contenido);
        $contenido['tips'] = $contenido['tips'] ?? null;
        if (isset($contenido['categorias']) && is_array($contenido['categorias'])) {
            foreach ($contenido['categorias'] as $categoria) {
                if (! IdentificadorEntero::esValido($categoria)) {
                    $this->rechazarReutilizacion();
                }
            }
            $contenido['categorias'] = array_map('intval', $contenido['categorias']);
            sort($contenido['categorias']);
        }
        foreach (['ingredientes', 'pasos'] as $campo) {
            if (isset($contenido[$campo]) && is_array($contenido[$campo])) {
                $contenido[$campo] = collect($contenido[$campo])->sortBy('orden')->values()->all();
            }
        }

        return $this->huella($contenido);
    }

    private function ordenar(mixed $valor): mixed
    {
        if (! is_array($valor)) {
            return $valor;
        }
        if (! array_is_list($valor)) {
            ksort($valor);
        }

        return array_map(fn (mixed $elemento) => $this->ordenar($elemento), $valor);
    }

    private function rechazarReutilizacion(): never
    {
        throw ValidationException::withMessages(['clave_idempotencia' => 'La clave de idempotencia ya fue utilizada para otra operación o contenido distinto.']);
    }
}
