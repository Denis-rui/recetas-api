@extends('layouts.app')

@section('titulo', 'Gestión de categorías')

@section('contenido')
@php
    $formularioActivo = old('_formulario');
@endphp

<div class="space-y-6">
    <header class="flex flex-col gap-3 border-b border-slate-200 pb-5 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">Gestión de categorías y subcategorías</h1>
            <p class="mt-1 max-w-3xl text-sm text-slate-500">
                Administra la clasificación de recetas. Las categorías sin subcategorías se conservan aquí, pero no aparecen en los filtros públicos.
            </p>
        </div>
        <span class="inline-flex w-fit items-center rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-600 shadow-xs">
            Total de categorías: <strong class="ml-1 text-slate-900">{{ $categorias->count() }}</strong>
        </span>
    </header>

    @if ($errors->any() && !session('error'))
        <div class="rounded-r-lg border-l-4 border-rose-500 bg-rose-50 p-4 shadow-xs" role="alert">
            <p class="text-sm font-semibold text-rose-900">Revisa los datos enviados:</p>
            <ul class="mt-1 list-inside list-disc space-y-0.5 text-sm text-rose-800">
                @foreach ($errors->all() as $mensajeError)
                    <li>{{ $mensajeError }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-xs sm:p-5">
        <div class="mb-3">
            <h2 class="text-base font-semibold text-slate-900">Nueva categoría</h2>
            <p class="mt-0.5 text-xs text-slate-500">Puedes agregarle subcategorías después de crearla.</p>
        </div>

        <form method="POST" action="{{ route('categorias.store') }}" class="flex flex-col gap-2 sm:flex-row sm:items-start">
            @csrf
            <input type="hidden" name="_formulario" value="crear_categoria">

            <div class="w-full max-w-xl">
                <label for="crear_categoria_nombre" class="sr-only">Nombre de la categoría</label>
                <input
                    id="crear_categoria_nombre"
                    type="text"
                    name="nombre"
                    maxlength="100"
                    required
                    value="{{ $formularioActivo === 'crear_categoria' || $formularioActivo === null ? old('nombre', '') : '' }}"
                    placeholder="Ej. Platos de fondo"
                    class="w-full rounded-lg border {{ ($formularioActivo === 'crear_categoria' || $formularioActivo === null) && $errors->has('nombre') ? 'border-rose-400 bg-rose-50/40' : 'border-slate-300 bg-slate-50/50' }} px-3 py-2 text-sm text-slate-800 transition focus:border-amber-500 focus:ring-2 focus:ring-amber-200 focus:outline-hidden"
                >
                @if (($formularioActivo === 'crear_categoria' || $formularioActivo === null) && $errors->has('nombre'))
                    <p class="mt-1 text-xs font-medium text-rose-600">{{ $errors->first('nombre') }}</p>
                @endif
            </div>

            <button type="submit" class="inline-flex shrink-0 cursor-pointer items-center justify-center rounded-lg bg-amber-600 px-3.5 py-2 text-sm font-semibold text-white shadow-xs transition hover:bg-amber-700">
                Crear categoría
            </button>
        </form>
    </section>

    <div class="space-y-3">
        @forelse ($categorias as $categoria)
            @php
                $formEditarCat = 'editar_categoria_' . $categoria->id;
                $formCrearSub = 'crear_subcategoria_' . $categoria->id;
                $recetasEnSubcategorias = $categoria->subcategorias->sum('recetas_totales_count');
                $tieneRecetasCat = $categoria->recetas_totales_count > 0 || $recetasEnSubcategorias > 0;
                $formEditarSubActivo = $categoria->subcategorias->contains(
                    fn ($subcategoria) => $formularioActivo === 'editar_subcategoria_' . $subcategoria->id
                );
                $categoriaAbierta = (int) session('categoria_abierta') === (int) $categoria->id
                    || $formularioActivo === $formEditarCat
                    || $formularioActivo === $formCrearSub
                    || $formEditarSubActivo;
            @endphp

            <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xs">
                <div class="flex flex-col gap-3 p-3 sm:flex-row sm:items-start sm:gap-4 sm:p-4">
                    <details class="group min-w-0 flex-1" @if ($categoriaAbierta) open @endif>
                        <summary class="flex cursor-pointer list-none flex-wrap items-center gap-2 rounded-lg py-1 text-left outline-hidden focus-visible:ring-2 focus-visible:ring-amber-400">
                            <span class="text-lg font-bold text-slate-900">{{ $categoria->nombre }}</span>

                            @if ($categoria->subcategorias->isEmpty())
                                <span class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-1 text-xs font-medium text-amber-800">
                                    Sin subcategorías · Oculta en filtros públicos
                                </span>
                            @else
                                <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-medium text-emerald-800">
                                    {{ $categoria->subcategorias->count() }} {{ $categoria->subcategorias->count() === 1 ? 'subcategoría' : 'subcategorías' }}
                                </span>
                            @endif

                            <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600">
                                {{ $categoria->recetas_totales_count }} {{ $categoria->recetas_totales_count === 1 ? 'receta asociada' : 'recetas asociadas' }}
                            </span>
                            <span class="ml-auto rounded-md px-2 py-1 text-xs font-medium text-slate-500 transition group-open:rotate-90" aria-hidden="true">›</span>
                            <span class="sr-only">{{ $categoriaAbierta ? 'Ocultar' : 'Ver' }} subcategorías</span>
                        </summary>

                        <div class="mt-3 border-t border-slate-100 pt-3">
                            @if ($categoria->subcategorias->isEmpty())
                                <p class="text-sm text-slate-500">Esta categoría todavía no tiene subcategorías. Agrega una para habilitarla en los filtros públicos.</p>
                            @else
                                <ul class="space-y-2">
                                    @foreach ($categoria->subcategorias as $subcategoria)
                                        @php
                                            $formEditarSub = 'editar_subcategoria_' . $subcategoria->id;
                                            $editarSubAbierto = $formularioActivo === $formEditarSub;
                                        @endphp
                                        <li class="rounded-lg border border-slate-200 bg-slate-50/60 px-3 py-2">
                                            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                                <div class="flex min-w-0 items-center gap-2">
                                                    <span class="size-1.5 shrink-0 rounded-full bg-amber-500" aria-hidden="true"></span>
                                                    <span class="truncate text-sm font-medium text-slate-800">{{ $subcategoria->nombre }}</span>
                                                    <span class="shrink-0 rounded-full bg-white px-2 py-0.5 text-xs text-slate-500 ring-1 ring-slate-200">
                                                        {{ $subcategoria->recetas_totales_count }} {{ $subcategoria->recetas_totales_count === 1 ? 'receta' : 'recetas' }}
                                                    </span>
                                                </div>

                                                <div class="flex shrink-0 items-center gap-2">
                                                    <details class="group/editar" @if ($editarSubAbierto) open @endif>
                                                        <summary class="cursor-pointer list-none rounded-md px-2.5 py-1 text-xs font-semibold text-slate-600 outline-hidden hover:bg-white hover:text-slate-900 focus-visible:ring-2 focus-visible:ring-amber-400">
                                                            {{ $editarSubAbierto ? 'Cerrar edición' : 'Editar nombre' }}
                                                        </summary>
                                                        <form method="POST" action="{{ route('categorias.subcategorias.update', [$categoria, $subcategoria]) }}" class="mt-2 flex flex-col gap-2 border-t border-slate-200 pt-3 sm:flex-row sm:items-start">
                                                            @csrf
                                                            @method('PUT')
                                                            <input type="hidden" name="_formulario" value="{{ $formEditarSub }}">
                                                            <div class="w-full max-w-md">
                                                                <label for="subcategoria_nombre_{{ $subcategoria->id }}" class="sr-only">Nombre de subcategoría {{ $subcategoria->nombre }}</label>
                                                                <input
                                                                    id="subcategoria_nombre_{{ $subcategoria->id }}"
                                                                    type="text"
                                                                    name="nombre"
                                                                    maxlength="100"
                                                                    required
                                                                    value="{{ $editarSubAbierto ? old('nombre', $subcategoria->nombre) : $subcategoria->nombre }}"
                                                                    class="w-full rounded-lg border {{ $editarSubAbierto && $errors->has('nombre') ? 'border-rose-400 bg-rose-50/40' : 'border-slate-300 bg-white' }} px-3 py-1.5 text-sm text-slate-800 focus:border-amber-500 focus:ring-2 focus:ring-amber-200 focus:outline-hidden"
                                                                >
                                                                @if ($editarSubAbierto && $errors->has('nombre'))
                                                                    <p class="mt-1 text-xs font-medium text-rose-600">{{ $errors->first('nombre') }}</p>
                                                                @endif
                                                            </div>
                                                            <button type="submit" class="inline-flex w-fit cursor-pointer items-center justify-center rounded-lg bg-slate-800 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-slate-900">Guardar nombre</button>
                                                        </form>
                                                    </details>
                                                    <form
                                                        method="POST"
                                                        action="{{ route('categorias.subcategorias.destroy', [$categoria, $subcategoria]) }}"
                                                        onsubmit="return confirm('¿Deseas eliminar esta subcategoría?');"
                                                    >
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="cursor-pointer rounded-md px-2.5 py-1 text-xs font-semibold text-rose-700 transition hover:bg-rose-50" title="{{ $subcategoria->recetas_totales_count > 0 ? 'Tiene recetas asociadas; la eliminación será bloqueada' : 'Eliminar subcategoría' }}">
                                                            Eliminar
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>

                                        </li>
                                    @endforeach
                                </ul>
                            @endif

                            <form method="POST" action="{{ route('categorias.subcategorias.store', $categoria) }}" class="mt-3 flex flex-col gap-2 border-t border-slate-100 pt-3 sm:flex-row sm:items-start">
                                @csrf
                                <input type="hidden" name="_formulario" value="{{ $formCrearSub }}">
                                <div class="w-full max-w-md">
                                    <label for="nueva_subcategoria_{{ $categoria->id }}" class="sr-only">Nueva subcategoría en {{ $categoria->nombre }}</label>
                                    <input
                                        id="nueva_subcategoria_{{ $categoria->id }}"
                                        type="text"
                                        name="nombre"
                                        maxlength="100"
                                        required
                                        value="{{ $formularioActivo === $formCrearSub ? old('nombre', '') : '' }}"
                                        placeholder="Nueva subcategoría"
                                        class="w-full rounded-lg border {{ $formularioActivo === $formCrearSub && $errors->has('nombre') ? 'border-rose-400 bg-rose-50/40' : 'border-slate-300 bg-white' }} px-3 py-1.5 text-sm text-slate-800 focus:border-amber-500 focus:ring-2 focus:ring-amber-200 focus:outline-hidden"
                                    >
                                    @if ($formularioActivo === $formCrearSub && $errors->has('nombre'))
                                        <p class="mt-1 text-xs font-medium text-rose-600">{{ $errors->first('nombre') }}</p>
                                    @endif
                                </div>
                                <button type="submit" class="inline-flex w-fit shrink-0 cursor-pointer items-center justify-center rounded-lg bg-amber-600 px-3 py-1.5 text-xs font-semibold text-white shadow-xs transition hover:bg-amber-700">Agregar subcategoría</button>
                            </form>
                        </div>
                    </details>

                    <div class="flex shrink-0 items-start gap-2 sm:pt-0.5">
                        <details class="group/editar-categoria relative" @if ($formularioActivo === $formEditarCat) open @endif>
                            <summary class="cursor-pointer list-none rounded-md px-2.5 py-1.5 text-xs font-semibold text-slate-700 outline-hidden hover:bg-slate-100 focus-visible:ring-2 focus-visible:ring-amber-400">
                                {{ $formularioActivo === $formEditarCat ? 'Cerrar' : 'Renombrar' }}
                            </summary>
                                <form method="POST" action="{{ route('categorias.update', $categoria) }}" class="mt-2 flex flex-col gap-2 sm:absolute sm:right-0 sm:top-full sm:z-10 sm:w-80 sm:rounded-lg sm:border sm:border-slate-200 sm:bg-white sm:p-3 sm:shadow-lg">
                                    @csrf
                                    @method('PUT')
                                    <input type="hidden" name="_formulario" value="{{ $formEditarCat }}">
                                    <label for="categoria_nombre_{{ $categoria->id }}" class="text-xs font-medium text-slate-600">Nuevo nombre de categoría</label>
                                    <input
                                        id="categoria_nombre_{{ $categoria->id }}"
                                        type="text"
                                        name="nombre"
                                        maxlength="100"
                                        required
                                        value="{{ old('nombre', $categoria->nombre) }}"
                                        class="w-full rounded-lg border {{ $errors->has('nombre') ? 'border-rose-400 bg-rose-50/40' : 'border-slate-300 bg-white' }} px-3 py-1.5 text-sm text-slate-800 focus:border-amber-500 focus:ring-2 focus:ring-amber-200 focus:outline-hidden"
                                    >
                                    @if ($errors->has('nombre'))
                                        <p class="text-xs font-medium text-rose-600">{{ $errors->first('nombre') }}</p>
                                    @endif
                                    <button type="submit" class="inline-flex w-fit cursor-pointer items-center justify-center rounded-lg bg-slate-800 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-slate-900">Guardar nombre</button>
                                </form>
                        </details>

                        <form method="POST" action="{{ route('categorias.destroy', $categoria) }}" onsubmit="return confirm('¿Deseas eliminar esta categoría y sus subcategorías?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="cursor-pointer rounded-md px-2.5 py-1.5 text-xs font-semibold text-rose-700 transition hover:bg-rose-50" title="{{ $tieneRecetasCat ? 'Tiene recetas asociadas; la eliminación será bloqueada' : 'Eliminar categoría' }}">
                                Eliminar
                            </button>
                        </form>
                    </div>
                </div>
            </section>
        @empty
            <div class="rounded-xl border border-dashed border-slate-300 bg-white p-8 text-center">
                <p class="text-base font-semibold text-slate-800">El catálogo de categorías está vacío</p>
                <p class="mt-1 text-sm text-slate-500">Crea la primera categoría usando el formulario superior.</p>
            </div>
        @endforelse
    </div>
</div>
@endsection
