@extends('layouts.app')

@section('titulo', 'Editar Cuenta - ' . $usuario->name)

@section('contenido')
<div class="max-w-2xl mx-auto space-y-6">
    <div class="pb-5 border-b border-slate-200">
        <a href="{{ route('usuarios.index') }}" class="inline-flex items-center text-sm font-medium text-amber-600 hover:text-amber-700 mb-2">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            Volver a la lista de usuarios
        </a>
        <h1 class="text-2xl font-bold tracking-tight text-slate-900">Editar cuenta</h1>
        <p class="text-sm text-slate-500 mt-1">
            Modifique los datos personales de la cuenta de <strong>{{ $usuario->name }}</strong>.
        </p>
    </div>

    @if ($errors->any())
        <div class="rounded-lg bg-rose-50 border border-rose-200 p-4">
            <div class="flex items-start">
                <svg class="h-5 w-5 text-rose-500 mt-0.5 mr-2 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                </svg>
                <div class="text-sm text-rose-700">
                    <p class="font-semibold mb-1">Por favor corrija los siguientes errores:</p>
                    <ul class="list-disc pl-4 space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    @endif

    <div class="bg-white rounded-xl border border-slate-200 shadow-xs p-6 sm:p-8">
        <form method="POST" action="{{ route('usuarios.update', $usuario) }}" enctype="multipart/form-data" class="space-y-6">
            @csrf
            @method('PUT')

            <!-- Foto Actual / Iniciales -->
            <div class="flex items-center space-x-4 pb-2">
                @if($usuario->foto_perfil)
                    <img src="{{ asset('storage/' . $usuario->foto_perfil) }}" alt="{{ $usuario->name }}" class="w-16 h-16 rounded-full object-cover border-2 border-slate-200">
                @else
                    <div class="w-16 h-16 rounded-full bg-amber-100 text-amber-800 font-bold text-lg flex items-center justify-center border-2 border-amber-200">
                        {{ $usuario->iniciales }}
                    </div>
                @endif
                <div>
                    <h3 class="text-sm font-semibold text-slate-900">{{ $usuario->name }}</h3>
                    <p class="text-xs text-slate-500">
                        Rol: <span class="font-medium text-slate-700">{{ $usuario->rol === 'administrador' ? 'Administrador' : 'Usuario normal' }}</span> &bull; 
                        Estado: <span class="font-medium {{ $usuario->activo ? 'text-emerald-600' : 'text-rose-600' }}">{{ $usuario->activo ? 'Activo' : 'Deshabilitado' }}</span>
                    </p>
                </div>
            </div>

            <!-- Nombre Completo -->
            <div>
                <label for="name" class="block text-sm font-medium text-slate-700 mb-1">
                    Nombre completo <span class="text-rose-500">*</span>
                </label>
                <input type="text" 
                       name="name" 
                       id="name" 
                       value="{{ old('name', $usuario->name) }}" 
                       required 
                       class="w-full rounded-lg border border-slate-300 px-3.5 py-2 text-sm text-slate-900 focus:border-amber-500 focus:ring-2 focus:ring-amber-200 focus:outline-hidden transition">
            </div>

            <!-- Correo Electrónico -->
            <div>
                <label for="email" class="block text-sm font-medium text-slate-700 mb-1">
                    Correo electrónico <span class="text-rose-500">*</span>
                </label>
                <input type="email" 
                       name="email" 
                       id="email" 
                       value="{{ old('email', $usuario->email) }}" 
                       required 
                       class="w-full rounded-lg border border-slate-300 px-3.5 py-2 text-sm text-slate-900 focus:border-amber-500 focus:ring-2 focus:ring-amber-200 focus:outline-hidden transition">
                <p class="text-xs text-slate-400 mt-1">El correo debe ser único. Se permite conservar el actual.</p>
            </div>

            <!-- Foto de Perfil (Opcional) -->
            <div>
                <label for="foto_perfil" class="block text-sm font-medium text-slate-700 mb-1">
                    Cambiar foto de perfil <span class="text-slate-400 text-xs">(Opcional)</span>
                </label>
                <input type="file" 
                       name="foto_perfil" 
                       id="foto_perfil" 
                       accept="image/png, image/jpeg, image/jpg, image/webp"
                       class="block w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-amber-50 file:text-amber-700 hover:file:bg-amber-100 transition">
                <p class="text-xs text-slate-400 mt-1">Seleccione un archivo solo si desea reemplazar la foto actual.</p>
            </div>

            <!-- Botones -->
            <div class="flex items-center justify-end space-x-3 pt-4 border-t border-slate-200">
                <a href="{{ route('usuarios.index') }}" 
                   class="px-4 py-2 border border-slate-300 rounded-lg text-sm font-medium text-slate-700 hover:bg-slate-50 transition">
                    Cancelar
                </a>
                <button type="submit" 
                        class="px-5 py-2 bg-amber-600 hover:bg-amber-700 text-white text-sm font-semibold rounded-lg shadow-xs transition cursor-pointer">
                    Actualizar cuenta
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

