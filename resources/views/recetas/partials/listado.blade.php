@if ($recetas->isEmpty())
    <div class="p-10 text-center">
        <p class="text-base font-semibold text-slate-800">No se encontraron recetas publicadas vigentes</p>
        <p class="mt-1 text-sm text-slate-500">Prueba ajustando los filtros de búsqueda o clasificación.</p>
    </div>
@else
    <div class="overflow-x-auto">
        <table class="w-full border-collapse text-left">
            <thead>
                <tr class="border-b border-slate-200 bg-slate-50/80 text-xs font-semibold uppercase tracking-wider text-slate-700">
                    <th class="px-4 py-3.5">ID / Receta</th>
                    <th class="px-4 py-3.5">Clasificación</th>
                    <th class="px-4 py-3.5">Autor</th>
                    <th class="px-4 py-3.5">Valoraciones</th>
                    <th class="px-4 py-3.5">Publicación</th>
                    <th class="px-4 py-3.5 text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-sm">
                @foreach ($recetas as $receta)
                    <tr class="transition hover:bg-slate-50/60">
                        <td class="px-4 py-3.5">
                            <div class="font-semibold text-slate-900">#{{ $receta->id }} · {{ $receta->nombre }}</div>
                            <div class="mt-0.5 text-xs text-slate-500">
                                {{ $receta->porciones }} {{ $receta->porciones === 1 ? 'porción' : 'porciones' }} · {{ $receta->tiempo_preparacion }} min
                            </div>
                        </td>
                        <td class="px-4 py-3.5">
                            @if ($receta->categoria && $receta->subcategoria)
                                <span class="inline-flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-800">
                                    {{ $receta->categoria->nombre }} · {{ $receta->subcategoria->nombre }}
                                </span>
                            @else
                                <span class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-800">
                                    Sin clasificación (Histórica)
                                </span>
                            @endif
                        </td>
                        <td class="px-4 py-3.5 text-slate-700">
                            {{ $receta->creador?->name ?? 'Autor desconocido' }}
                        </td>
                        <td class="px-4 py-3.5 text-slate-700">
                            @if ($receta->valoraciones_count > 0)
                                <span class="font-medium text-amber-700">
                                    ★ {{ number_format((float) $receta->valoraciones_avg_puntuacion, 1) }}
                                </span>
                                <span class="text-xs text-slate-500">({{ $receta->valoraciones_count }})</span>
                            @else
                                <span class="text-xs text-slate-400">Sin valoraciones</span>
                            @endif
                        </td>
                        <td class="px-4 py-3.5 text-xs text-slate-500">
                            {{ $receta->publicada_en?->timezone('America/Lima')->format('d/m/Y H:i') }}
                        </td>
                        <td class="space-x-2 whitespace-nowrap px-4 py-3.5 text-right">
                            <a
                                href="{{ route('recetas.show', $receta) }}"
                                class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-50"
                            >
                                Ver
                            </a>
                            <a
                                href="{{ route('recetas.edit', $receta) }}"
                                class="inline-flex items-center rounded-lg bg-amber-600 px-3 py-1.5 text-xs font-semibold text-white shadow-xs transition hover:bg-amber-700"
                            >
                                Editar
                            </a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if ($recetas->hasPages())
        <div class="border-t border-slate-200 bg-slate-50/50 px-4 py-3">
            {{ $recetas->links() }}
        </div>
    @endif
@endif
