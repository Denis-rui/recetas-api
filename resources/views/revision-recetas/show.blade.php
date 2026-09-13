@extends('layouts.app')
@section('titulo', 'Detalle de revisión')
@section('contenido')
<div class="flex flex-col gap-6" data-revision>
    <header class="flex flex-col gap-3">
        <a href="{{ route('revision-recetas.index') }}" class="text-sm font-medium text-amber-700 hover:underline">← Volver a solicitudes</a>
        <h1 class="text-2xl font-bold text-slate-900">{{ $solicitud->tipo === 'correccion' ? 'Revisar corrección' : 'Revisar publicación' }}</h1>
        <p class="text-sm text-slate-500">Solicitud #{{ $solicitud->id }} · {{ ucfirst($solicitud->estado) }}</p>
    </header>
    @if($errors->any())
        <div role="alert" class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">
            <p class="font-semibold">No se pudo registrar la decisión.</p>
            <ul class="mt-2 list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif
    <dl class="grid gap-4 rounded-2xl border border-slate-200 bg-white p-5 text-sm sm:grid-cols-2 lg:grid-cols-3">
        <div><dt class="text-slate-500">Autor</dt><dd class="mt-1 font-semibold">{{ $solicitud->solicitante->name }} @unless($solicitud->solicitante->estaActivo())<span class="text-rose-700">(Deshabilitado)</span>@endunless</dd></div>
        <div><dt class="text-slate-500">Enviada</dt><dd class="mt-1">{{ $solicitud->created_at->timezone('America/Lima')->format('d/m/Y H:i') }}</dd></div>
        <div><dt class="text-slate-500">Versión de origen</dt><dd class="mt-1">{{ $solicitud->version_base }} · Versión actual: {{ $solicitud->receta->version }}</dd></div>
        @if($solicitud->revisada_en)
            <div><dt class="text-slate-500">Revisada por</dt><dd class="mt-1">{{ $solicitud->revisor?->name ?? 'Administrador no disponible' }}</dd></div>
            <div><dt class="text-slate-500">Fecha de decisión</dt><dd class="mt-1">{{ $solicitud->revisada_en->timezone('America/Lima')->format('d/m/Y H:i') }}</dd></div>
        @endif
        @if($solicitud->cancelada_en)
            <div><dt class="text-slate-500">Cancelada</dt><dd class="mt-1">{{ $solicitud->cancelada_en->timezone('America/Lima')->format('d/m/Y H:i') }}</dd></div>
        @endif
        @if($solicitud->motivo_rechazo)
            <div class="sm:col-span-2"><dt class="text-slate-500">Motivo de rechazo</dt><dd class="mt-1 whitespace-pre-line break-words">{{ $solicitud->motivo_rechazo }}</dd></div>
        @endif
    </dl>
    @if($bloqueo && $solicitud->estado === 'pendiente')
        <div role="status" class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900"><strong>Aprobación bloqueada.</strong> {{ $bloqueo }}</div>
    @endif
    @if($publicado)
        <p class="text-sm text-slate-600">Se resaltan los campos modificados. Mientras la corrección esté pendiente, se conserva el contenido publicado.</p>
    @endif
    <div @class(['grid items-start gap-6', 'lg:grid-cols-2' => $publicado !== null])>
        @if($publicado)
            @include('revision-recetas.contenido', ['titulo' => 'Versión publicada', 'datos' => $publicado, 'comparar' => $propuesta, 'imagenUrl' => $imagenPublicada])
        @endif
        @if($propuesta)
            @include('revision-recetas.contenido', ['titulo' => 'Propuesta enviada', 'datos' => $propuesta, 'comparar' => $publicado, 'imagenUrl' => $imagenPropuesta])
        @else
            <section class="rounded-2xl border border-rose-200 bg-white p-6">
                <h2 class="text-lg font-semibold">Propuesta no válida</h2>
                <ul class="mt-3 list-inside list-disc text-sm text-rose-700">@foreach($erroresContenido as $error)<li>{{ $error }}</li>@endforeach</ul>
            </section>
        @endif
    </div>
    @if($solicitud->estado === 'pendiente')
        <section class="rounded-2xl border border-slate-200 bg-white p-6">
            <h2 class="text-lg font-semibold">Decisión de revisión</h2>
            <div class="mt-4 flex flex-col gap-6">
                <form method="POST" action="{{ route('revision-recetas.aprobar', $solicitud) }}" data-decision="aprobar">
                    @csrf
                    @if($solicitud->tipo === 'correccion')
                        <label class="mb-4 flex items-start gap-3 text-sm text-slate-700">
                            <input type="checkbox" name="confirmar_correccion_menor" value="1" required class="mt-1">
                            <span>He revisado título, ingredientes, cantidades y pasos: es una corrección menor que conserva la preparación. Si la transforma (por ejemplo, de arroz a ceviche), debo rechazarla y solicitar una receta nueva, con sus propias valoraciones.</span>
                        </label>
                    @endif
                    <button type="submit" disabled data-bloqueado="{{ $bloqueo !== null ? 'true' : 'false' }}" class="cursor-pointer rounded-lg bg-emerald-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-emerald-800 disabled:cursor-not-allowed disabled:opacity-50">Aprobar {{ $solicitud->tipo === 'correccion' ? 'corrección' : 'publicación' }}</button>
                    <noscript><p class="mt-2 text-sm text-amber-800">Activa JavaScript para confirmar la aprobación.</p></noscript>
                </form>
                <form method="POST" action="{{ route('revision-recetas.rechazar', $solicitud) }}" data-decision="rechazar" class="flex flex-col items-start gap-3">
                    @csrf
                    <label for="motivo_rechazo" class="text-sm font-medium">Motivo de rechazo <span class="text-rose-700">(obligatorio)</span></label>
                    <textarea id="motivo_rechazo" name="motivo_rechazo" required maxlength="16000" rows="3" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Explica al autor qué debe corregir.">{{ old('motivo_rechazo') }}</textarea>
                    <button type="submit" class="cursor-pointer rounded-lg border border-rose-200 bg-rose-50 px-5 py-2.5 text-sm font-semibold text-rose-700 hover:bg-rose-100 disabled:opacity-50">Rechazar solicitud</button>
                </form>
                <p class="text-sm text-slate-500" role="status" data-estado-envio></p>
            </div>
        </section>
        <dialog data-confirmacion class="m-auto w-full max-w-md rounded-2xl border border-slate-200 bg-white p-6 text-slate-800 shadow-xl backdrop:bg-slate-900/40">
            <h2 class="text-lg font-bold">Confirmar aprobación</h2>
            <p class="mt-3 text-sm text-slate-600">Se publicará el contenido de esta propuesta. Las valoraciones y los favoritos existentes se conservarán.</p>
            <div class="mt-6 flex justify-end gap-3">
                <button type="button" data-cancelar class="cursor-pointer rounded-lg border border-slate-300 px-4 py-2 text-sm">Volver a revisar</button>
                <button type="button" data-confirmar class="cursor-pointer rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white">Sí, aprobar</button>
            </div>
        </dialog>
    @endif
</div>
@endsection
