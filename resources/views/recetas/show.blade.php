@extends('layouts.app')

@section('titulo', 'Detalle de Receta Publicada')

@section('contenido')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-5 border-b border-slate-200">
        <div>
            <a href="{{ route('recetas.index') }}" class="text-sm font-medium text-amber-700 hover:underline">
                ← Volver a recetas publicadas
            </a>
            <h1 class="mt-2 text-2xl font-bold tracking-tight text-slate-900">
                Receta #{{ $receta->id }} · {{ $receta->nombre }}
            </h1>
            <p class="text-sm text-slate-500 mt-1">
                Publicada el {{ $receta->publicada_en?->timezone('America/Lima')->format('d/m/Y H:i') }} · Versión {{ $receta->version }}
            </p>
        </div>

        <div class="flex items-center gap-3">
            <a
                href="{{ route('recetas.edit', $receta) }}"
                class="inline-flex items-center px-4 py-2 rounded-lg bg-amber-600 hover:bg-amber-700 text-white text-sm font-semibold shadow-xs transition"
            >
                Editar receta publicada
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Resumen lateral -->
        <div class="space-y-6">
            <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-xs space-y-4">
                @if ($imagenUrl)
                    <img
                        src="{{ $imagenUrl }}"
                        alt="Imagen de {{ $receta->nombre }}"
                        class="h-52 w-full rounded-lg bg-slate-100 object-contain"
                    >
                @else
                    <div class="flex h-40 items-center justify-center rounded-lg bg-slate-100 text-sm text-slate-500">
                        Imagen no disponible
                    </div>
                @endif

                <dl class="divide-y divide-slate-100 text-sm">
                    <div class="py-2.5 flex justify-between gap-2">
                        <dt class="text-slate-500">Clasificación</dt>
                        <dd class="font-semibold text-right text-slate-900">
                            @if ($receta->categoria && $receta->subcategoria)
                                {{ $receta->categoria->nombre }} · {{ $receta->subcategoria->nombre }}
                            @else
                                <span class="text-amber-700">Sin clasificación (Histórica)</span>
                            @endif
                        </dd>
                    </div>
                    <div class="py-2.5 flex justify-between gap-2">
                        <dt class="text-slate-500">Autor</dt>
                        <dd class="font-medium text-slate-900">{{ $receta->creador?->name ?? 'No disponible' }}</dd>
                    </div>
                    <div class="py-2.5 flex justify-between gap-2">
                        <dt class="text-slate-500">Porciones</dt>
                        <dd class="font-medium text-slate-900">{{ $receta->porciones }}</dd>
                    </div>
                    <div class="py-2.5 flex justify-between gap-2">
                        <dt class="text-slate-500">Tiempo de preparación</dt>
                        <dd class="font-medium text-slate-900">{{ $receta->tiempo_preparacion }} min</dd>
                    </div>
                    <div class="py-2.5 flex justify-between gap-2">
                        <dt class="text-slate-500">Valoraciones</dt>
                        <dd class="font-medium text-slate-900">
                            @if ($receta->valoraciones_count > 0)
                                ★ {{ number_format((float) $receta->valoraciones_avg_puntuacion, 1) }} ({{ $receta->valoraciones_count }})
                            @else
                                Sin valoraciones (0)
                            @endif
                        </dd>
                    </div>
                    <div class="py-2.5 flex justify-between gap-2">
                        <dt class="text-slate-500">Guardada en favoritos</dt>
                        <dd class="font-medium text-slate-900">{{ $receta->usuarios_que_la_guardaron_count }}</dd>
                    </div>
                </dl>
            </div>
        </div>

        <!-- Contenido principal -->
        <div class="lg:col-span-2 space-y-6">
            <section class="bg-white rounded-xl border border-slate-200 p-6 shadow-xs space-y-4">
                <div>
                    <h2 class="text-xs font-semibold uppercase tracking-wider text-slate-500">Descripción</h2>
                    <p class="mt-2 text-sm text-slate-800 whitespace-pre-line break-words">{{ $receta->descripcion }}</p>
                </div>

                <div class="pt-4 border-t border-slate-100">
                    <h2 class="text-xs font-semibold uppercase tracking-wider text-slate-500">Tips y recomendaciones</h2>
                    <p class="mt-2 text-sm text-slate-800 whitespace-pre-line break-words">
                        {{ $receta->tips ?? 'Sin tips registrados.' }}
                    </p>
                </div>
            </section>

            <section class="bg-white rounded-xl border border-slate-200 p-6 shadow-xs">
                <h2 class="text-base font-bold text-slate-900">Ingredientes ({{ $receta->ingredientes->count() }})</h2>
                <ol class="mt-4 divide-y divide-slate-100">
                    @foreach ($receta->ingredientes as $ingrediente)
                        <li class="py-3 flex flex-col sm:flex-row sm:items-center justify-between gap-2 text-sm">
                            <div>
                                <span class="font-semibold text-slate-900">
                                    {{ $ingrediente->pivot->orden }}. {{ $ingrediente->nombre }}
                                </span>
                                @if ($ingrediente->pivot->notas)
                                    <p class="text-xs text-slate-500 mt-0.5">{{ $ingrediente->pivot->notas }}</p>
                                @endif
                            </div>
                            <div class="text-xs font-medium text-slate-700 bg-slate-100 px-2.5 py-1 rounded-md shrink-0">
                                {{ $ingrediente->pivot->cantidad !== null ? rtrim(rtrim(number_format((float) $ingrediente->pivot->cantidad, 3, '.', ''), '0'), '.') : 'Al gusto' }}
                                {{ $ingrediente->pivot->unidad }}
                            </div>
                        </li>
                    @endforeach
                </ol>
            </section>

            <section class="bg-white rounded-xl border border-slate-200 p-6 shadow-xs">
                <h2 class="text-base font-bold text-slate-900">Pasos de preparación ({{ $receta->pasos->count() }})</h2>
                <ol class="mt-4 space-y-3">
                    @foreach ($receta->pasos as $paso)
                        <li class="p-3.5 rounded-lg border border-slate-100 bg-slate-50/60 text-sm whitespace-pre-line break-words">
                            <strong class="text-slate-900">{{ $paso->orden }}.</strong> {{ $paso->instruccion }}
                        </li>
                    @endforeach
                </ol>
            </section>
        </div>
    </div>
</div>
@endsection

