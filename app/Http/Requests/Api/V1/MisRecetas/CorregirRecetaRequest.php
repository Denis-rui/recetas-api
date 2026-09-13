<?php

namespace App\Http\Requests\Api\V1\MisRecetas;

use App\Rules\IdentificadorEntero;
use Illuminate\Foundation\Http\FormRequest;

class CorregirRecetaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->estaActivo();
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('contenido') && is_string($this->input('contenido'))) {
            $decodificado = json_decode($this->input('contenido'), true);
            if (is_array($decodificado)) {
                $this->merge(['contenido' => $decodificado]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'clave_idempotencia' => ['required', 'uuid'],
            'version_base' => ['required', 'integer', 'min:1'],
            'contenido' => ['required', 'array:nombre,descripcion,imagen,porciones,tiempo_preparacion,tips,categorias,ingredientes,pasos'],
            'contenido.nombre' => ['required', 'string', 'max:150'],
            'contenido.descripcion' => ['required', 'string', 'max:16000'],
            'contenido.imagen' => ['sometimes', 'required', 'string', 'max:2048'],
            'contenido.porciones' => ['required', 'integer', 'between:1,65535'],
            'contenido.tiempo_preparacion' => ['required', 'integer', 'between:1,65535'],
            'contenido.tips' => ['nullable', 'string', 'max:16000'],
            'contenido.categorias' => ['required', 'array', 'list', 'min:1', 'max:100'],
            'contenido.categorias.*' => ['required', new IdentificadorEntero, 'distinct'],
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
        ];
    }
}
