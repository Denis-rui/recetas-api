<?php

namespace App\Http\Requests\Categorias;

use App\Models\Categoria;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GuardarCategoriaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $usuario = $this->user();
        if ($usuario === null) {
            return false;
        }

        $categoria = $this->route('categoria');

        return $categoria instanceof Categoria
            ? $usuario->can('update', $categoria)
            : $usuario->can('create', Categoria::class);
    }

    protected function prepareForValidation(): void
    {
        $categoria = $this->route('categoria');
        $categoriaId = $categoria instanceof Categoria ? $categoria->id : $categoria;

        $datos = [
            '_formulario' => $this->input(
                '_formulario',
                $categoriaId !== null ? "editar_categoria_{$categoriaId}" : 'crear_categoria'
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
        $categoria = $this->route('categoria');
        $categoriaId = $categoria instanceof Categoria ? $categoria->id : $categoria;

        return [
            'nombre' => [
                'required',
                'string',
                'max:100',
                Rule::unique('categorias', 'nombre')->ignore($categoriaId),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre de la categoría es obligatorio.',
            'nombre.string' => 'El nombre de la categoría debe ser un texto válido.',
            'nombre.max' => 'El nombre de la categoría no puede superar los 100 caracteres.',
            'nombre.unique' => 'Ya existe una categoría con este nombre.',
        ];
    }
}
