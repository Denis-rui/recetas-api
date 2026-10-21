<?php

namespace App\Http\Requests\Categorias;

use App\Models\Categoria;
use App\Models\Subcategoria;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class GuardarSubcategoriaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $usuario = $this->user();
        if ($usuario === null) {
            return false;
        }

        $subcategoria = $this->route('subcategoria');

        return $subcategoria instanceof Subcategoria
            ? $usuario->can('update', $subcategoria)
            : $usuario->can('create', Subcategoria::class);
    }

    protected function prepareForValidation(): void
    {
        $categoria = $this->route('categoria');
        $categoriaId = $categoria instanceof Categoria ? $categoria->id : $categoria;

        $subcategoria = $this->route('subcategoria');
        $subcategoriaId = $subcategoria instanceof Subcategoria ? $subcategoria->id : $subcategoria;

        if ($categoria instanceof Categoria && $subcategoria instanceof Subcategoria) {
            abort_if((int) $subcategoria->categoria_id !== (int) $categoria->id, 404);
        }

        $datos = [
            '_formulario' => $this->input(
                '_formulario',
                $subcategoriaId !== null
                    ? "editar_subcategoria_{$subcategoriaId}"
                    : "crear_subcategoria_{$categoriaId}"
            ),
        ];

        $nombre = $this->input('nombre');
        if (is_string($nombre)) {
            $datos['nombre'] = preg_replace('/\A[\s\p{Z}\p{Cf}]+|[\s\p{Z}\p{Cf}]+\z/u', '', $nombre) ?? $nombre;
        }

        $this->merge($datos);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nombre' => [
                'required',
                'string',
                'max:100',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre de la subcategoría es obligatorio.',
            'nombre.string' => 'El nombre de la subcategoría debe ser un texto válido.',
            'nombre.max' => 'El nombre de la subcategoría no puede superar los 100 caracteres.',
        ];
    }
}
