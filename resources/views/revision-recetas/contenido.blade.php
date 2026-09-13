<section class="min-w-0 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xs">
    <h2 class="border-b border-slate-200 bg-slate-50 px-6 py-4 text-lg font-semibold">{{ $titulo }}</h2>
    <div class="flex flex-col gap-5 p-6">
        <div @class(['rounded-xl p-3', 'bg-amber-50 ring-1 ring-amber-200' => $diferencias['imagen'] ?? false])>
            @if($imagenUrl)
                <img src="{{ $imagenUrl }}" alt="Imagen de {{ $datos['nombre'] }}" class="h-48 w-full rounded-lg bg-slate-100 object-contain">
            @else
                <div class="flex h-32 items-center justify-center rounded-lg bg-slate-100 text-sm text-slate-500">Imagen no disponible</div>
            @endif
            @if($diferencias['imagen'] ?? false)<p class="mt-2 text-xs font-semibold text-amber-800">Imagen modificada</p>@endif
        </div>
        @foreach(['nombre' => 'Nombre', 'descripcion' => 'Descripción', 'porciones' => 'Porciones', 'tiempo_preparacion' => 'Tiempo de preparación (min)', 'tips' => 'Tips'] as $campo => $etiqueta)
            <div @class(['rounded-lg p-3', 'bg-amber-50 ring-1 ring-amber-200' => $diferencias[$campo] ?? false])>
                <h3 class="text-xs font-semibold uppercase text-slate-500">{{ $etiqueta }} @if($diferencias[$campo] ?? false)<span class="ml-2 text-amber-800">Modificado</span>@endif</h3>
                <p class="mt-2 whitespace-pre-line break-words text-sm">{{ $datos[$campo] ?? 'Sin tips' }}</p>
            </div>
        @endforeach
        <div @class(['rounded-lg p-3', 'bg-amber-50 ring-1 ring-amber-200' => $diferencias['categorias'] ?? false])>
            <h3 class="text-xs font-semibold uppercase text-slate-500">Categorías @if($diferencias['categorias'] ?? false)<span class="ml-2 text-amber-800">Modificado</span>@endif</h3>
            <p class="mt-2 text-sm">{{ collect($datos['categorias'])->map(fn ($id) => $categorias[$id] ?? 'Categoría no disponible')->join(', ') }}</p>
        </div>
        <div>
            <h3 class="mb-3 text-sm font-semibold">Ingredientes @if($diferencias['ingredientes'] ?? false)<span class="ml-2 text-xs text-amber-800">Modificados (incluye cantidades y orden)</span>@endif</h3>
            <ol class="flex flex-col gap-2">
                @foreach($datos['ingredientes'] as $ingrediente)
                    @php($otro = collect($comparar['ingredientes'] ?? [])->firstWhere('ingrediente_id', $ingrediente['ingrediente_id']))
                    <li @class(['rounded-lg border p-3 text-sm', 'border-amber-200 bg-amber-50' => $comparar && $otro != $ingrediente, 'border-slate-100' => ! $comparar || $otro == $ingrediente])>
                        <p class="font-medium">{{ $ingrediente['orden'] }}. {{ $ingredientes[$ingrediente['ingrediente_id']] ?? 'Ingrediente no disponible' }}</p>
                        <p class="mt-1 text-slate-600">{{ $ingrediente['cantidad'] !== null ? rtrim(rtrim(number_format((float) $ingrediente['cantidad'], 3, '.', ''), '0'), '.') : 'Al gusto' }} {{ $ingrediente['unidad'] }}</p>
                        @if($ingrediente['notas'])<p class="mt-1 text-slate-500">{{ $ingrediente['notas'] }}</p>@endif
                        @if($comparar && $otro != $ingrediente)<p class="mt-1 text-xs font-semibold text-amber-800">Modificado</p>@endif
                    </li>
                @endforeach
            </ol>
        </div>
        <div>
            <h3 class="mb-3 text-sm font-semibold">Pasos @if($diferencias['pasos'] ?? false)<span class="ml-2 text-xs text-amber-800">Modificados (incluye orden)</span>@endif</h3>
            <ol class="flex flex-col gap-2">
                @foreach($datos['pasos'] as $paso)
                    @php($otroPaso = collect($comparar['pasos'] ?? [])->firstWhere('orden', $paso['orden']))
                    <li @class(['rounded-lg border p-3 text-sm whitespace-pre-line break-words', 'border-amber-200 bg-amber-50' => $comparar && $otroPaso != $paso, 'border-slate-100' => ! $comparar || $otroPaso == $paso])><strong>{{ $paso['orden'] }}.</strong> {{ $paso['instruccion'] }}@if($comparar && $otroPaso != $paso)<span class="mt-1 block text-xs font-semibold text-amber-800">Modificado</span>@endif</li>
                @endforeach
            </ol>
        </div>
    </div>
</section>
