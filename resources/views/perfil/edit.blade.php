@extends('layouts.app')

@section('titulo', 'Mi Perfil')

@section('contenido')
<div class="max-w-4xl mx-auto space-y-8">
    <!-- Encabezado -->
    <div class="pb-5 border-b border-slate-200">
        <h1 class="text-2xl font-bold tracking-tight text-slate-900">Mi perfil</h1>
    </div>

    <!-- Sección 1: Datos Personales (RF-10) -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-xs overflow-hidden">
        <div class="p-6 sm:p-8 border-b border-slate-100">
            <h2 class="text-lg font-bold text-slate-900">Datos personales</h2>
            <p class="text-xs text-slate-500 mt-0.5">
                Actualice su nombre completo, correo de acceso y foto de perfil.
            </p>
        </div>

        @if(session('exito_perfil'))
            <div class="mx-6 sm:mx-8 mt-6 bg-emerald-50 border border-emerald-200 p-4 rounded-lg flex items-center text-sm text-emerald-800">
                <svg class="w-5 h-5 text-emerald-500 mr-2 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                {{ session('exito_perfil') }}
            </div>
        @endif

        <form method="POST" action="{{ route('perfil.update') }}" enctype="multipart/form-data" class="p-6 sm:p-8 space-y-6">
            @csrf
            @method('PUT')

            <!-- Foto de Perfil o Iniciales -->
            <div class="flex items-center space-x-5">
                @if($usuario->foto_perfil)
                    <img src="{{ asset('storage/' . $usuario->foto_perfil) }}" alt="{{ $usuario->name }}" class="w-20 h-20 rounded-full object-cover border-2 border-amber-300 shadow-xs">
                @else
                    <div class="w-20 h-20 rounded-full bg-amber-100 text-amber-800 font-bold text-2xl flex items-center justify-center border-2 border-amber-300 shadow-xs">
                        {{ $usuario->iniciales }}
                    </div>
                @endif
                <div>
                    <h3 class="text-base font-semibold text-slate-900">{{ $usuario->name }}</h3>
                    <div class="flex items-center space-x-2 mt-1">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-semibold bg-indigo-50 text-indigo-700 border border-indigo-200">
                            Administrador
                        </span>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                            Activo
                        </span>
                    </div>
                    <p class="text-xs text-slate-400 mt-1">
                        Su rol y estado están protegidos y no pueden modificarse desde esta sección.
                    </p>
                </div>
            </div>

            <!-- Nombre Completo -->
            <div>
                <label for="name" class="block text-sm font-medium text-slate-700 mb-1">Nombre completo</label>
                <input type="text" 
                       name="name" 
                       id="name" 
                       value="{{ old('name', $usuario->name) }}" 
                       required 
                       class="w-full rounded-lg border border-slate-300 px-3.5 py-2 text-sm text-slate-900 focus:border-amber-500 focus:ring-2 focus:ring-amber-200 focus:outline-hidden transition">
                @error('name')
                    <p class="text-xs text-rose-500 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- Correo Electrónico -->
            <div>
                <label for="email" class="block text-sm font-medium text-slate-700 mb-1">Correo electrónico</label>
                <input type="email" 
                       name="email" 
                       id="email" 
                       value="{{ old('email', $usuario->email) }}" 
                       required 
                       class="w-full rounded-lg border border-slate-300 px-3.5 py-2 text-sm text-slate-900 focus:border-amber-500 focus:ring-2 focus:ring-amber-200 focus:outline-hidden transition">
                @error('email')
                    <p class="text-xs text-rose-500 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- Cambiar Foto -->
            <div>
                <label for="foto_perfil" class="block text-sm font-medium text-slate-700 mb-1">Cambiar fotografía</label>
                <input type="file" 
                       name="foto_perfil" 
                       id="foto_perfil" 
                       accept="image/png, image/jpeg, image/jpg, image/webp"
                       class="block w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-amber-50 file:text-amber-700 hover:file:bg-amber-100 transition">
                @error('foto_perfil')
                    <p class="text-xs text-rose-500 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex justify-end pt-3">
                <button type="submit" 
                        class="px-5 py-2 bg-amber-600 hover:bg-amber-700 text-white text-sm font-semibold rounded-lg shadow-xs transition cursor-pointer">
                    Guardar datos personales
                </button>
            </div>
        </form>
    </div>

    <!-- Sección 2: Cambiar Contraseña (RF-11) -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-xs overflow-hidden">
        <div class="p-6 sm:p-8 border-b border-slate-100">
            <h2 class="text-lg font-bold text-slate-900">Cambiar contraseña</h2>
            <p class="text-xs text-slate-500 mt-0.5">
                Para mayor seguridad, use al menos 12 caracteres y combine letras, números o frases largas.
            </p>
        </div>

        @if(session('exito_password'))
            <div class="mx-6 sm:mx-8 mt-6 bg-emerald-50 border border-emerald-200 p-4 rounded-lg flex items-center text-sm text-emerald-800">
                <svg class="w-5 h-5 text-emerald-500 mr-2 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                {{ session('exito_password') }}
            </div>
        @endif

        <form method="POST" action="{{ route('perfil.password') }}" class="p-6 sm:p-8 space-y-5">
            @csrf
            @method('PUT')

            <!-- Contraseña Actual -->
            <div>
                <label for="current_password" class="block text-sm font-medium text-slate-700 mb-1">
                    Contraseña actual <span class="text-rose-500">*</span>
                </label>
                <input type="password" 
                       name="current_password" 
                       id="current_password" 
                       required 
                       placeholder="Ingrese su contraseña actual"
                       class="w-full sm:w-2/3 rounded-lg border border-slate-300 px-3.5 py-2 text-sm text-slate-900 focus:border-amber-500 focus:ring-2 focus:ring-amber-200 focus:outline-hidden transition">
                @error('current_password')
                    <p class="text-xs text-rose-500 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- Nueva Contraseña -->
            <div>
                <label for="password" class="block text-sm font-medium text-slate-700 mb-1">
                    Nueva contraseña <span class="text-rose-500">*</span>
                </label>
                <input type="password" 
                       name="password" 
                       id="password" 
                       required 
                       placeholder="Mínimo 12 caracteres"
                       class="w-full sm:w-2/3 rounded-lg border border-slate-300 px-3.5 py-2 text-sm text-slate-900 focus:border-amber-500 focus:ring-2 focus:ring-amber-200 focus:outline-hidden transition">
                <p class="text-xs text-slate-400 mt-1">Debe tener un mínimo de 12 caracteres.</p>
                @error('password')
                    <p class="text-xs text-rose-500 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- Confirmar Nueva Contraseña -->
            <div>
                <label for="password_confirmation" class="block text-sm font-medium text-slate-700 mb-1">
                    Confirmar nueva contraseña <span class="text-rose-500">*</span>
                </label>
                <input type="password" 
                       name="password_confirmation" 
                       id="password_confirmation" 
                       required 
                       placeholder="Repita la nueva contraseña"
                       class="w-full sm:w-2/3 rounded-lg border border-slate-300 px-3.5 py-2 text-sm text-slate-900 focus:border-amber-500 focus:ring-2 focus:ring-amber-200 focus:outline-hidden transition">
                @error('password_confirmation')
                    <p class="text-xs text-rose-500 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex justify-end pt-3">
                <button type="submit" 
                        class="px-5 py-2 bg-slate-800 hover:bg-slate-900 text-white text-sm font-semibold rounded-lg shadow-xs transition cursor-pointer">
                    Actualizar contraseña
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

