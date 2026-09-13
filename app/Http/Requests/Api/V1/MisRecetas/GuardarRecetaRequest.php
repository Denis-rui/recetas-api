<?php

namespace App\Http\Requests\Api\V1\MisRecetas;

use Illuminate\Foundation\Http\FormRequest;

class GuardarRecetaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->estaActivo();
    }

    protected function prepareForValidation(): void
    {
        foreach (['categorias', 'ingredientes', 'pasos'] as $campo) {
            if ($this->has($campo) && is_string($this->input($campo))) {
                $decodificado = json_decode($this->input($campo), true);
                if (is_array($decodificado)) {
                    $this->merge([$campo => $decodificado]);
                }
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $reglaImagen = $this->hasFile('imagen')
            ? ['required', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048']
            : ['required', 'string', 'max:2048'];

        return [
            'nombre' => ['required', 'string', 'max:150'],
            'descripcion' => ['required', 'string', 'max:16000'],
            'imagen' => $reglaImagen,
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
        ];
    }
}

