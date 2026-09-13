@extends('layouts.app')
@section('titulo', 'Revisión de recetas')
@section('contenido')
<div class="flex flex-col gap-6">
    <header>
        <h1 class="text-2xl font-bold text-slate-900">Revisión de recetas</h1>
        <p class="mt-2 text-sm text-slate-600">Revisa las publicaciones y correcciones enviadas por los autores.</p>
    </header>
    @if($errors->any())
        <div role="alert" class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">
            Los filtros no son válidos. <a href="{{ route('revision-recetas.index') }}" class="underline">Volver a pendientes</a>
        </div>
    @endif
    <form method="GET" action="{{ route('revision-recetas.index') }}" class="flex flex-wrap items-end gap-4 rounded-2xl border border-slate-200 bg-white p-5">
        <div>
            <label for="estado" class="mb-1 block text-sm font-medium">Estado</label>
            <select id="estado" name="estado" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
                @foreach(['pendiente' => 'Pendientes', 'aprobada' => 'Aprobadas', 'rechazada' => 'Rechazadas', 'cancelada' => 'Canceladas', 'todos' => 'Todos los estados'] as $valor => $etiqueta)
                    <option value="{{ $valor }}" @selected($estado === $valor)>{{ $etiqueta }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="tipo" class="mb-1 block text-sm font-medium">Tipo</label>
            <select id="tipo" name="tipo" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
                @foreach(['todos' => 'Todos los tipos', 'publicacion' => 'Publicación', 'correccion' => 'Corrección'] as $valor => $etiqueta)
                    <option value="{{ $valor }}" @selected($tipo === $valor)>{{ $etiqueta }}</option>
                @endforeach
            </select>
        </div>
        <button class="cursor-pointer rounded-lg bg-amber-600 px-5 py-2 text-sm font-semibold text-white hover:bg-amber-700">Filtrar</button>
        <a href="{{ route('revision-recetas.index') }}" class="py-2 text-sm text-slate-600 underline">Ver pendientes</a>
    </form>
    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xs">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <caption class="sr-only">Solicitudes de revisión de recetas</caption>
                <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase text-slate-500">
                    <tr>@foreach(['Receta', 'Autor', 'Tipo', 'Estado', 'Fecha de envío', 'Acción'] as $columna)<th scope="col" class="px-5 py-4">{{ $columna }}</th>@endforeach</tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($solicitudes as $solicitud)
                        <tr class="hover:bg-slate-50">
                            <td class="max-w-xs px-5 py-4 font-semibold text-slate-900 break-words">{{ is_string(data_get($solicitud->contenido, 'nombre')) ? data_get($solicitud->contenido, 'nombre') : 'Contenido no válido' }}</td>
                            <td class="px-5 py-4">{{ $solicitud->solicitante->name }}</td>
                            <td class="px-5 py-4">{{ $solicitud->tipo === 'correccion' ? 'Corrección' : 'Publicación' }}</td>
                            <td class="px-5 py-4"><span @class(['inline-flex rounded-full px-3 py-1 text-xs font-medium', 'bg-amber-50 text-amber-800' => $solicitud->estado === 'pendiente', 'bg-emerald-50 text-emerald-800' => $solicitud->estado === 'aprobada', 'bg-rose-50 text-rose-800' => $solicitud->estado === 'rechazada', 'bg-slate-100 text-slate-600' => $solicitud->estado === 'cancelada'])>{{ ucfirst($solicitud->estado) }}</span></td>
                            <td class="whitespace-nowrap px-5 py-4 text-slate-500">{{ $solicitud->created_at->timezone('America/Lima')->format('d/m/Y H:i') }}</td>
                            <td class="px-5 py-4"><a href="{{ route('revision-recetas.show', $solicitud) }}" class="font-semibold text-amber-700 hover:underline">Ver detalle<span class="sr-only"> de solicitud {{ $solicitud->id }}</span></a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-6 py-14 text-center text-slate-500">No hay solicitudes que coincidan con estos filtros.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-100 px-5 py-4">{{ $solicitudes->links() }}</div>
    </div>
</div>
@endsection
