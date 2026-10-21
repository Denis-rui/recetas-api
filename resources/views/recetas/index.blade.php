@extends('layouts.app')

@section('titulo', 'Recetas Publicadas')

@section('contenido')
<div class="space-y-6">
    <div class="sm:flex sm:items-center sm:justify-between pb-5 border-b border-slate-200">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">Recetas publicadas y vigentes</h1>
            <p class="text-sm text-slate-500 mt-1">
                Localiza, consulta y edita directamente cualquier receta publicada vigente conservando su ID y valoraciones.
            </p>
        </div>
        <div class="mt-3 sm:mt-0 text-xs font-medium text-slate-600">
            <span class="px-3 py-1.5 rounded-lg bg-white border border-slate-200 shadow-xs">
                Total encontradas: <strong id="total-recetas-encontradas" class="text-slate-900">{{ $recetas->total() }}</strong>
            </span>
        </div>
    </div>

    @if ($errors->any())
        <div role="alert" class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">
            <p class="font-semibold">Filtros no válidos:</p>
            <ul class="mt-1 list-inside list-disc">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- Buscador y Filtros -->
    <form
        method="GET"
        action="{{ route('recetas.index') }}"
        class="rounded-xl border border-slate-200 bg-white p-4 shadow-xs sm:p-5"
        id="filtros-recetas"
        data-login-url="{{ route('login') }}"
    >
        <div id="aviso-filtros-recetas" class="mb-3 hidden items-center justify-between gap-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900" role="status" aria-live="polite">
            <span id="texto-aviso-filtros-recetas"></span>
            <button id="reintentar-filtros-recetas" type="button" class="hidden shrink-0 font-semibold underline underline-offset-2 disabled:cursor-wait disabled:opacity-60">Reintentar</button>
        </div>
        <div class="grid grid-cols-1 items-end gap-3 md:grid-cols-2 xl:grid-cols-12">
            <div class="xl:col-span-3">
                <label for="buscar" class="mb-1 block text-[11px] font-semibold uppercase tracking-wider text-slate-600">
                    Buscar receta
                </label>
                <input
                    id="buscar"
                    type="text"
                    name="buscar"
                    value="{{ $buscar }}"
                    maxlength="100"
                    autocomplete="off"
                    placeholder="Nombre o descripción..."
                    class="w-full rounded-lg border border-slate-300 bg-slate-50/50 px-3 py-1.5 text-sm text-slate-800 transition focus:border-amber-500 focus:ring-2 focus:ring-amber-200 focus:outline-hidden"
                >
            </div>

            <div class="xl:col-span-2">
                <label for="clasificacion" class="mb-1 block truncate text-[11px] font-semibold uppercase tracking-wider text-slate-600">
                    Estado de clasificación
                </label>
                <select
                    id="clasificacion"
                    name="clasificacion"
                    class="w-full rounded-lg border border-slate-300 bg-slate-50/50 px-3 py-1.5 text-sm text-slate-800 transition focus:border-amber-500 focus:ring-2 focus:ring-amber-200 focus:outline-hidden"
                >
                    <option value="todas" @selected($clasificacion === 'todas')>Todas las publicadas</option>
                    <option value="clasificadas" @selected($clasificacion === 'clasificadas')>Con categoría asignada</option>
                    <option value="sin_clasificar" @selected($clasificacion === 'sin_clasificar')>Históricas sin clasificar</option>
                </select>
            </div>

            <div class="xl:col-span-2">
                <label for="categoria_id" class="mb-1 block text-[11px] font-semibold uppercase tracking-wider text-slate-600">
                    Categoría
                </label>
                <select
                    id="categoria_id"
                    name="categoria_id"
                    class="w-full rounded-lg border border-slate-300 bg-slate-50/50 px-3 py-1.5 text-sm text-slate-800 transition focus:border-amber-500 focus:ring-2 focus:ring-amber-200 focus:outline-hidden"
                >
                    <option value="">Todas las categorías</option>
                    @foreach ($categorias as $cat)
                        <option value="{{ $cat->id }}" @selected($categoriaId === $cat->id)>{{ $cat->nombre }}</option>
                    @endforeach
                </select>
            </div>

            <div class="xl:col-span-2">
                <label for="subcategoria_id" class="mb-1 block text-[11px] font-semibold uppercase tracking-wider text-slate-600">
                    Subcategoría
                </label>
                <select
                    id="subcategoria_id"
                    name="subcategoria_id"
                    class="w-full rounded-lg border border-slate-300 bg-slate-50/50 px-3 py-1.5 text-sm text-slate-800 transition focus:border-amber-500 focus:ring-2 focus:ring-amber-200 focus:outline-hidden"
                >
                    <option value="">Selecciona una categoría</option>
                    @foreach ($categorias as $cat)
                        @foreach ($cat->subcategorias as $sub)
                            <option
                                value="{{ $sub->id }}"
                                data-categoria-id="{{ $cat->id }}"
                                @selected($subcategoriaId === $sub->id)
                            >
                                {{ $sub->nombre }}
                            </option>
                        @endforeach
                    @endforeach
                </select>
            </div>

            <div class="flex justify-end md:col-span-2 xl:col-span-3">
                <button
                    id="limpiar-filtros-recetas"
                    type="button"
                    class="inline-flex items-center rounded-lg bg-slate-100 px-4 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-200"
                >
                    Limpiar filtros
                </button>
            </div>
        </div>
    </form>

    <!-- Tabla de Recetas Publicadas -->
    <div id="listado-recetas" class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xs" aria-busy="false">
        @include('recetas.partials.listado')
    </div>
</div>
@endsection

