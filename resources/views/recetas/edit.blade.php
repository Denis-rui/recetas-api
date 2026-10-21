@extends('layouts.app')

@section('titulo', 'Editar Receta Publicada')

@section('contenido')
@php
    $categoriaSeleccionada = (int) old('categoria_id', $receta->categoria_id ?? 0);
    $subcategoriaSeleccionada = (int) old('subcategoria_id', $receta->subcategoria_id ?? 0);

    $ingredientesFormulario = old('ingredientes');
    if (! is_array($ingredientesFormulario) || $ingredientesFormulario === []) {
        $ingredientesFormulario = $receta->ingredientes->map(fn ($ingrediente) => [
            'ingrediente_id' => $ingrediente->id,
            'cantidad' => $ingrediente->pivot->cantidad !== null
                ? rtrim(rtrim(number_format((float) $ingrediente->pivot->cantidad, 3, '.', ''), '0'), '.')
                : '',
            'unidad' => $ingrediente->pivot->unidad ?? '',
            'notas' => $ingrediente->pivot->notas ?? '',
            'orden' => $ingrediente->pivot->orden,
        ])->values()->all();
    }
    if ($ingredientesFormulario === []) {
        $ingredientesFormulario = [[
            'ingrediente_id' => '',
            'cantidad' => '',
            'unidad' => '',
            'notas' => '',
            'orden' => 1,
        ]];
    }

    $pasosFormulario = old('pasos');
    if (! is_array($pasosFormulario) || $pasosFormulario === []) {
        $pasosFormulario = $receta->pasos->map(fn ($paso) => [
            'orden' => $paso->orden,
            'instruccion' => $paso->instruccion,
        ])->values()->all();
    }
    if ($pasosFormulario === []) {
        $pasosFormulario = [[
            'orden' => 1,
            'instruccion' => '',
        ]];
    }
@endphp

<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-5 border-b border-slate-200">
        <div>
            <div class="flex items-center gap-3 text-sm">
                <a href="{{ route('recetas.index') }}" class="font-medium text-amber-700 hover:underline">
                    ← Volver al listado
                </a>
                <span class="text-slate-300">|</span>
                <a href="{{ route('recetas.show', $receta) }}" class="font-medium text-slate-600 hover:underline">
                    Ver ficha publicada
                </a>
            </div>
            <h1 class="mt-2 text-2xl font-bold tracking-tight text-slate-900">
                Editar receta publicada #{{ $receta->id }}
            </h1>
            <p class="text-sm text-slate-500 mt-1">
                Los cambios se guardan directamente en el catálogo público sin crear solicitudes de revisión, conservando el ID (#{{ $receta->id }}) y sus {{ $receta->valoraciones_count }} {{ $receta->valoraciones_count === 1 ? 'valoración' : 'valoraciones' }}.
            </p>
        </div>
    </div>

    @if ($receta->categoria_id === null || $receta->subcategoria_id === null)
        <div role="status" class="rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
            <strong>Receta histórica sin clasificación.</strong>
            Esta receta fue publicada antes del catálogo actual. Selecciona una categoría y una subcategoría perteneciente a ella para guardar los cambios.
        </div>
    @endif

    @if ($errors->any())
        <div role="alert" class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">
            <p class="font-semibold">Corrige los siguientes campos antes de guardar:</p>
            <ul class="mt-2 list-inside list-disc space-y-0.5">
                @foreach ($errors->all() as $mensajeError)
                    <li>{{ $mensajeError }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form
        method="POST"
        action="{{ route('recetas.update', $receta) }}"
        enctype="multipart/form-data"
        class="space-y-6"
        id="formulario-editar-receta"
    >
        @csrf
        @method('PUT')

        <!-- Datos generales y clasificación -->
        <section class="bg-white rounded-xl border border-slate-200 p-6 shadow-xs space-y-5">
            <h2 class="text-lg font-bold text-slate-900 border-b border-slate-100 pb-3">Datos principales y clasificación</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div class="md:col-span-2">
                    <label for="nombre" class="block text-sm font-medium text-slate-700 mb-1">
                        Nombre de la receta <span class="text-rose-600">*</span>
                    </label>
                    <input
                        id="nombre"
                        type="text"
                        name="nombre"
                        maxlength="150"
                        required
                        value="{{ old('nombre', $receta->nombre) }}"
                        class="w-full rounded-lg border border-slate-300 px-3.5 py-2 text-sm text-slate-900 focus:border-amber-500 focus:ring-2 focus:ring-amber-200 focus:outline-hidden transition"
                    >
                </div>

                <div>
                    <label for="categoria_id" class="block text-sm font-medium text-slate-700 mb-1">
                        Categoría <span class="text-rose-600">*</span>
                    </label>
                    <select
                        id="categoria_id"
                        name="categoria_id"
                        required
                        class="w-full rounded-lg border border-slate-300 px-3.5 py-2 text-sm text-slate-900 focus:border-amber-500 focus:ring-2 focus:ring-amber-200 focus:outline-hidden transition"
                    >
                        <option value="">Selecciona una categoría...</option>
                        @foreach ($categorias as $cat)
                            <option value="{{ $cat->id }}" @selected($categoriaSeleccionada === $cat->id)>
                                {{ $cat->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="subcategoria_id" class="block text-sm font-medium text-slate-700 mb-1">
                        Subcategoría <span class="text-rose-600">*</span>
                    </label>
                    <select
                        id="subcategoria_id"
                        name="subcategoria_id"
                        required
                        class="w-full rounded-lg border border-slate-300 px-3.5 py-2 text-sm text-slate-900 focus:border-amber-500 focus:ring-2 focus:ring-amber-200 focus:outline-hidden transition"
                    >
                        <option value="">Selecciona una subcategoría...</option>
                        @foreach ($categorias as $cat)
                            @foreach ($cat->subcategorias as $sub)
                                <option
                                    value="{{ $sub->id }}"
                                    data-categoria-id="{{ $cat->id }}"
                                    @selected($subcategoriaSeleccionada === $sub->id)
                                >
                                    {{ $cat->nombre }} · {{ $sub->nombre }}
                                </option>
                            @endforeach
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="porciones" class="block text-sm font-medium text-slate-700 mb-1">
                        Porciones <span class="text-rose-600">*</span>
                    </label>
                    <input
                        id="porciones"
                        type="number"
                        name="porciones"
                        min="1"
                        max="65535"
                        required
                        value="{{ old('porciones', $receta->porciones) }}"
                        class="w-full rounded-lg border border-slate-300 px-3.5 py-2 text-sm text-slate-900 focus:border-amber-500 focus:ring-2 focus:ring-amber-200 focus:outline-hidden transition"
                    >
                </div>

                <div>
                    <label for="tiempo_preparacion" class="block text-sm font-medium text-slate-700 mb-1">
                        Tiempo de preparación (minutos) <span class="text-rose-600">*</span>
                    </label>
                    <input
                        id="tiempo_preparacion"
                        type="number"
                        name="tiempo_preparacion"
                        min="1"
                        max="65535"
                        required
                        value="{{ old('tiempo_preparacion', $receta->tiempo_preparacion) }}"
                        class="w-full rounded-lg border border-slate-300 px-3.5 py-2 text-sm text-slate-900 focus:border-amber-500 focus:ring-2 focus:ring-amber-200 focus:outline-hidden transition"
                    >
                </div>

                <div class="md:col-span-2">
                    <label for="descripcion" class="block text-sm font-medium text-slate-700 mb-1">
                        Descripción <span class="text-rose-600">*</span>
                    </label>
                    <textarea
                        id="descripcion"
                        name="descripcion"
                        rows="4"
                        maxlength="16000"
                        required
                        class="w-full rounded-lg border border-slate-300 px-3.5 py-2 text-sm text-slate-900 focus:border-amber-500 focus:ring-2 focus:ring-amber-200 focus:outline-hidden transition"
                    >{{ old('descripcion', $receta->descripcion) }}</textarea>
                </div>

                <div class="md:col-span-2">
                    <label for="tips" class="block text-sm font-medium text-slate-700 mb-1">
                        Tips y recomendaciones (opcional)
                    </label>
                    <textarea
                        id="tips"
                        name="tips"
                        rows="3"
                        maxlength="16000"
                        class="w-full rounded-lg border border-slate-300 px-3.5 py-2 text-sm text-slate-900 focus:border-amber-500 focus:ring-2 focus:ring-amber-200 focus:outline-hidden transition"
                    >{{ old('tips', $receta->tips) }}</textarea>
                </div>
            </div>
        </section>

        <!-- Imagen -->
        <section class="bg-white rounded-xl border border-slate-200 p-6 shadow-xs space-y-4">
            <h2 class="text-lg font-bold text-slate-900 border-b border-slate-100 pb-3">Imagen del plato</h2>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 items-center">
                <div>
                    @if ($imagenUrl)
                        <img
                            src="{{ $imagenUrl }}"
                            alt="Imagen actual de {{ $receta->nombre }}"
                            class="h-40 w-full rounded-lg bg-slate-100 object-contain border border-slate-200"
                        >
                    @else
                        <div class="flex h-36 items-center justify-center rounded-lg bg-slate-100 text-xs text-slate-500 border border-slate-200">
                            Imagen actual no disponible en disco
                        </div>
                    @endif
                </div>

                <div class="md:col-span-2 space-y-2">
                    <label for="imagen" class="block text-sm font-medium text-slate-700">
                        Reemplazar imagen (opcional si ya tiene una imagen vigente)
                    </label>
                    <input
                        id="imagen"
                        type="file"
                        name="imagen"
                        accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                        class="block w-full text-sm text-slate-700 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-amber-50 file:text-amber-800 hover:file:bg-amber-100"
                    >
                    <p class="text-xs text-slate-500">
                        Formatos permitidos: JPG, PNG o WEBP (máximo 2 MB). Si no seleccionas un archivo, se conserva la imagen actual.
                    </p>
                </div>
            </div>
        </section>

        <!-- Ingredientes -->
        <section class="bg-white rounded-xl border border-slate-200 p-6 shadow-xs space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div>
                    <h2 class="text-lg font-bold text-slate-900">Ingredientes</h2>
                    <p class="text-xs text-slate-500">Define el ingrediente, cantidad, unidad, notas y orden.</p>
                </div>
                <button
                    type="button"
                    id="btn-agregar-ingrediente"
                    class="inline-flex items-center px-3.5 py-1.5 rounded-lg bg-amber-50 hover:bg-amber-100 text-amber-800 border border-amber-200 text-xs font-semibold transition cursor-pointer"
                >
                    + Agregar ingrediente
                </button>
            </div>

            <div id="contenedor-ingredientes" class="space-y-3">
                @foreach ($ingredientesFormulario as $indice => $ing)
                    <div class="fila-ingrediente p-4 rounded-xl border border-slate-200 bg-slate-50/60 grid grid-cols-1 md:grid-cols-12 gap-3 items-start">
                        <div class="md:col-span-4">
                            <label class="block text-xs font-semibold text-slate-600 mb-1">Ingrediente *</label>
                            <select
                                name="ingredientes[{{ $indice }}][ingrediente_id]"
                                required
                                class="campo-ingrediente-id w-full rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-900"
                            >
                                <option value="">Seleccionar...</option>
                                @foreach ($ingredientesCatalogo as $itemCatalogo)
                                    <option
                                        value="{{ $itemCatalogo->id }}"
                                        @selected((int) ($ing['ingrediente_id'] ?? 0) === $itemCatalogo->id)
                                    >
                                        {{ $itemCatalogo->nombre }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-xs font-semibold text-slate-600 mb-1">Cantidad</label>
                            <input
                                type="number"
                                step="0.001"
                                min="0.001"
                                name="ingredientes[{{ $indice }}][cantidad]"
                                value="{{ $ing['cantidad'] ?? '' }}"
                                placeholder="Al gusto"
                                class="campo-ingrediente-cantidad w-full rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-900"
                            >
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-xs font-semibold text-slate-600 mb-1">Unidad</label>
                            <input
                                type="text"
                                maxlength="50"
                                name="ingredientes[{{ $indice }}][unidad]"
                                value="{{ $ing['unidad'] ?? '' }}"
                                placeholder="tazas, g..."
                                class="campo-ingrediente-unidad w-full rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-900"
                            >
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-xs font-semibold text-slate-600 mb-1">Notas</label>
                            <input
                                type="text"
                                name="ingredientes[{{ $indice }}][notas]"
                                value="{{ $ing['notas'] ?? '' }}"
                                placeholder="Picado..."
                                class="campo-ingrediente-notas w-full rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-900"
                            >
                        </div>

                        <div class="md:col-span-1">
                            <label class="block text-xs font-semibold text-slate-600 mb-1">Orden *</label>
                            <input
                                type="number"
                                min="1"
                                max="65535"
                                required
                                name="ingredientes[{{ $indice }}][orden]"
                                value="{{ $ing['orden'] ?? ($indice + 1) }}"
                                class="campo-ingrediente-orden w-full rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-sm text-slate-900"
                            >
                        </div>

                        <div class="md:col-span-1 flex md:justify-end pt-5">
                            <button
                                type="button"
                                class="btn-quitar-ingrediente px-2.5 py-1.5 rounded-lg border border-rose-200 text-rose-700 hover:bg-rose-50 text-xs font-semibold transition cursor-pointer"
                            >
                                Quitar
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        <!-- Pasos de preparación -->
        <section class="bg-white rounded-xl border border-slate-200 p-6 shadow-xs space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div>
                    <h2 class="text-lg font-bold text-slate-900">Pasos de preparación</h2>
                    <p class="text-xs text-slate-500">Detalla las instrucciones paso a paso en orden secuencial.</p>
                </div>
                <button
                    type="button"
                    id="btn-agregar-paso"
                    class="inline-flex items-center px-3.5 py-1.5 rounded-lg bg-amber-50 hover:bg-amber-100 text-amber-800 border border-amber-200 text-xs font-semibold transition cursor-pointer"
                >
                    + Agregar paso
                </button>
            </div>

            <div id="contenedor-pasos" class="space-y-3">
                @foreach ($pasosFormulario as $indice => $paso)
                    <div class="fila-paso p-4 rounded-xl border border-slate-200 bg-slate-50/60 grid grid-cols-1 md:grid-cols-12 gap-3 items-start">
                        <div class="md:col-span-2">
                            <label class="block text-xs font-semibold text-slate-600 mb-1">Orden *</label>
                            <input
                                type="number"
                                min="1"
                                max="65535"
                                required
                                name="pasos[{{ $indice }}][orden]"
                                value="{{ $paso['orden'] ?? ($indice + 1) }}"
                                class="campo-paso-orden w-full rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-900"
                            >
                        </div>

                        <div class="md:col-span-9">
                            <label class="block text-xs font-semibold text-slate-600 mb-1">Instrucción *</label>
                            <textarea
                                rows="2"
                                required
                                maxlength="16000"
                                name="pasos[{{ $indice }}][instruccion]"
                                class="campo-paso-instruccion w-full rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-900"
                            >{{ $paso['instruccion'] ?? '' }}</textarea>
                        </div>

                        <div class="md:col-span-1 flex md:justify-end pt-5">
                            <button
                                type="button"
                                class="btn-quitar-paso px-2.5 py-1.5 rounded-lg border border-rose-200 text-rose-700 hover:bg-rose-50 text-xs font-semibold transition cursor-pointer"
                            >
                                Quitar
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        <div class="flex items-center justify-end gap-3">
            <a
                href="{{ route('recetas.show', $receta) }}"
                class="px-4 py-2.5 rounded-lg border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 text-sm font-semibold transition"
            >
                Cancelar
            </a>
            <button
                type="submit"
                class="px-5 py-2.5 rounded-lg bg-amber-600 hover:bg-amber-700 text-white text-sm font-semibold shadow-xs transition cursor-pointer"
            >
                Guardar cambios directamente
            </button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const selectCategoria = document.getElementById('categoria_id');
    const selectSubcategoria = document.getElementById('subcategoria_id');

    function sincronizarSubcategorias() {
        if (!selectCategoria || !selectSubcategoria) {
            return;
        }
        const catId = selectCategoria.value;
        Array.from(selectSubcategoria.options).forEach(function (opcion) {
            if (!opcion.value) {
                return;
            }
            const pertenece = !catId || opcion.getAttribute('data-categoria-id') === catId;
            opcion.hidden = !pertenece;
            opcion.disabled = !pertenece;
            if (!pertenece && opcion.selected) {
                selectSubcategoria.value = '';
            }
        });
    }

    if (selectCategoria) {
        selectCategoria.addEventListener('change', sincronizarSubcategorias);
        sincronizarSubcategorias();
    }

    const contenedorIngredientes = document.getElementById('contenedor-ingredientes');
    const btnAgregarIngrediente = document.getElementById('btn-agregar-ingrediente');

    function reindexarIngredientes() {
        const filas = contenedorIngredientes.querySelectorAll('.fila-ingrediente');
        filas.forEach(function (fila, idx) {
            fila.querySelector('.campo-ingrediente-id').setAttribute('name', `ingredientes[${idx}][ingrediente_id]`);
            fila.querySelector('.campo-ingrediente-cantidad').setAttribute('name', `ingredientes[${idx}][cantidad]`);
            fila.querySelector('.campo-ingrediente-unidad').setAttribute('name', `ingredientes[${idx}][unidad]`);
            fila.querySelector('.campo-ingrediente-notas').setAttribute('name', `ingredientes[${idx}][notas]`);
            const campoOrden = fila.querySelector('.campo-ingrediente-orden');
            campoOrden.setAttribute('name', `ingredientes[${idx}][orden]`);
            campoOrden.value = idx + 1;
        });
    }

    if (btnAgregarIngrediente && contenedorIngredientes) {
        btnAgregarIngrediente.addEventListener('click', function () {
            const primera = contenedorIngredientes.querySelector('.fila-ingrediente');
            if (!primera) {
                return;
            }
            const clon = primera.cloneNode(true);
            clon.querySelector('.campo-ingrediente-id').value = '';
            clon.querySelector('.campo-ingrediente-cantidad').value = '';
            clon.querySelector('.campo-ingrediente-unidad').value = '';
            clon.querySelector('.campo-ingrediente-notas').value = '';
            contenedorIngredientes.appendChild(clon);
            reindexarIngredientes();
        });

        contenedorIngredientes.addEventListener('click', function (evento) {
            const boton = evento.target.closest('.btn-quitar-ingrediente');
            if (!boton) {
                return;
            }
            const filas = contenedorIngredientes.querySelectorAll('.fila-ingrediente');
            if (filas.length <= 1) {
                return;
            }
            boton.closest('.fila-ingrediente').remove();
            reindexarIngredientes();
        });
    }

    const contenedorPasos = document.getElementById('contenedor-pasos');
    const btnAgregarPaso = document.getElementById('btn-agregar-paso');

    function reindexarPasos() {
        const filas = contenedorPasos.querySelectorAll('.fila-paso');
        filas.forEach(function (fila, idx) {
            const campoOrden = fila.querySelector('.campo-paso-orden');
            campoOrden.setAttribute('name', `pasos[${idx}][orden]`);
            campoOrden.value = idx + 1;
            fila.querySelector('.campo-paso-instruccion').setAttribute('name', `pasos[${idx}][instruccion]`);
        });
    }

    if (btnAgregarPaso && contenedorPasos) {
        btnAgregarPaso.addEventListener('click', function () {
            const primera = contenedorPasos.querySelector('.fila-paso');
            if (!primera) {
                return;
            }
            const clon = primera.cloneNode(true);
            clon.querySelector('.campo-paso-instruccion').value = '';
            contenedorPasos.appendChild(clon);
            reindexarPasos();
        });

        contenedorPasos.addEventListener('click', function (evento) {
            const boton = evento.target.closest('.btn-quitar-paso');
            if (!boton) {
                return;
            }
            const filas = contenedorPasos.querySelectorAll('.fila-paso');
            if (filas.length <= 1) {
                return;
            }
            boton.closest('.fila-paso').remove();
            reindexarPasos();
        });
    }
});
</script>
@endsection

