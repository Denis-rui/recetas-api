<?php

namespace App\Http\Requests\Recetas;

use App\Models\Categoria;
use App\Models\Receta;
use App\Models\Subcategoria;
use App\Rules\IdentificadorEntero;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ActualizarRecetaPublicadaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $usuario = $this->user();
        $receta = $this->route('receta');

        if ($usuario === null || ! $receta instanceof Receta) {
            return false;
        }

        if (! $usuario->can('administrarListado', Receta::class)) {
            return false;
        }

        abort_unless($receta->estaPublicada(), 404);

        return $usuario->can('administrar', $receta);
    }

    protected function prepareForValidation(): void
    {
        $receta = $this->route('receta');
        if ($receta instanceof Receta && $this->user()?->can('administrarListado', Receta::class)) {
            abort_unless($receta->estaPublicada(), 404);
        }

        $fusiones = [];

        foreach (['nombre', 'descripcion', 'tips'] as $campoTexto) {
            $valor = $this->input($campoTexto);
            if (is_string($valor)) {
                $limpio = preg_replace('/\A[\s\p{Z}\p{Cf}]+|[\s\p{Z}\p{Cf}]+\z/u', '', $valor) ?? $valor;
                $fusiones[$campoTexto] = $campoTexto === 'tips' && $limpio === '' ? null : $limpio;
            }
        }

        $ingredientes = $this->input('ingredientes');
        if (is_string($ingredientes)) {
            $decodificado = json_decode($ingredientes, true);
            if (is_array($decodificado)) {
                $ingredientes = $decodificado;
            }
        }

        if (is_array($ingredientes) && array_is_list($ingredientes)) {
            foreach ($ingredientes as $indice => $ingrediente) {
                if (is_array($ingrediente)) {
                    foreach (['unidad', 'notas'] as $campoIng) {
                        if (! array_key_exists($campoIng, $ingrediente) || $ingrediente[$campoIng] === '') {
                            $ingrediente[$campoIng] = null;
                        } elseif (is_string($ingrediente[$campoIng])) {
                            $limpio = preg_replace('/\A[\s\p{Z}\p{Cf}]+|[\s\p{Z}\p{Cf}]+\z/u', '', $ingrediente[$campoIng]) ?? $ingrediente[$campoIng];
                            $ingrediente[$campoIng] = $limpio === '' ? null : $limpio;
                        }
                    }

                    if (! array_key_exists('cantidad', $ingrediente) || $ingrediente['cantidad'] === '') {
                        $ingrediente['cantidad'] = null;
                    }

                    if (! array_key_exists('orden', $ingrediente) || $ingrediente['orden'] === '' || $ingrediente['orden'] === null) {
                        $ingrediente['orden'] = $indice + 1;
                    }

                    $ingredientes[$indice] = $ingrediente;
                }
            }
            $fusiones['ingredientes'] = $ingredientes;
        }

        $pasos = $this->input('pasos');
        if (is_string($pasos)) {
            $decodificado = json_decode($pasos, true);
            if (is_array($decodificado)) {
                $pasos = $decodificado;
            }
        }

        if (is_array($pasos) && array_is_list($pasos)) {
            foreach ($pasos as $indice => $paso) {
                if (is_array($paso)) {
                    if (isset($paso['instruccion']) && is_string($paso['instruccion'])) {
                        $paso['instruccion'] = preg_replace('/\A[\s\p{Z}\p{Cf}]+|[\s\p{Z}\p{Cf}]+\z/u', '', $paso['instruccion']) ?? $paso['instruccion'];
                    }

                    if (! array_key_exists('orden', $paso) || $paso['orden'] === '' || $paso['orden'] === null) {
                        $paso['orden'] = $indice + 1;
                    }

                    $pasos[$indice] = $paso;
                }
            }
            $fusiones['pasos'] = $pasos;
        }

        if ($fusiones !== []) {
            $this->merge($fusiones);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $reglaImagen = $this->hasFile('imagen')
            ? ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048']
            : ['nullable', 'string', 'max:2048'];

        return [
            'nombre' => ['required', 'string', 'max:150'],
            'descripcion' => ['required', 'string', 'max:16000'],
            'imagen' => $reglaImagen,
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
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $categoriaId = (int) $this->input('categoria_id');
                $subcategoriaId = (int) $this->input('subcategoria_id');

                $categoria = Categoria::query()->whereKey($categoriaId)->first(['id']);
                $subcategoria = Subcategoria::query()->whereKey($subcategoriaId)->first(['id', 'categoria_id']);

                if ($categoria === null) {
                    $validator->errors()->add('categoria_id', 'La categoría seleccionada no existe.');
                }

                if ($subcategoria === null) {
                    $validator->errors()->add('subcategoria_id', 'La subcategoría seleccionada no existe.');
                }

                if ($categoria !== null && $subcategoria !== null && (int) $subcategoria->categoria_id !== (int) $categoria->id) {
                    $validator->errors()->add('subcategoria_id', 'La subcategoría seleccionada no pertenece a la categoría indicada.');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre de la receta es obligatorio.',
            'nombre.max' => 'El nombre de la receta no puede superar los 150 caracteres.',
            'descripcion.required' => 'La descripción de la receta es obligatoria.',
            'descripcion.max' => 'La descripción no puede superar los 16000 caracteres.',
            'imagen.image' => 'El archivo seleccionado debe ser una imagen válida.',
            'imagen.mimes' => 'La imagen debe estar en formato JPG, JPEG, PNG o WEBP.',
            'imagen.max' => 'La imagen no debe superar los 2 MB.',
            'porciones.required' => 'El número de porciones es obligatorio.',
            'porciones.integer' => 'El número de porciones debe ser un entero.',
            'porciones.between' => 'El número de porciones debe estar entre 1 y 65535.',
            'tiempo_preparacion.required' => 'El tiempo de preparación es obligatorio.',
            'tiempo_preparacion.integer' => 'El tiempo de preparación debe ser un entero en minutos.',
            'tiempo_preparacion.between' => 'El tiempo de preparación debe estar entre 1 y 65535 minutos.',
            'categoria_id.required' => 'Debes seleccionar una categoría.',
            'subcategoria_id.required' => 'Debes seleccionar una subcategoría perteneciente a la categoría.',
            'ingredientes.required' => 'La receta debe incluir al menos un ingrediente.',
            'ingredientes.min' => 'La receta debe incluir al menos un ingrediente.',
            'ingredientes.*.ingrediente_id.required' => 'Selecciona un ingrediente válido en cada fila.',
            'ingredientes.*.ingrediente_id.distinct' => 'No se puede repetir el mismo ingrediente varias veces.',
            'ingredientes.*.cantidad.numeric' => 'La cantidad del ingrediente debe ser numérica.',
            'ingredientes.*.cantidad.gt' => 'La cantidad del ingrediente debe ser mayor que cero.',
            'ingredientes.*.cantidad.decimal' => 'La cantidad del ingrediente admite hasta tres decimales.',
            'ingredientes.*.orden.distinct' => 'El orden de los ingredientes no puede repetirse.',
            'pasos.required' => 'La receta debe incluir al menos un paso de preparación.',
            'pasos.min' => 'La receta debe incluir al menos un paso de preparación.',
            'pasos.*.instruccion.required' => 'La instrucción de cada paso es obligatoria.',
            'pasos.*.orden.distinct' => 'El orden de los pasos no puede repetirse.',
        ];
    }
}

