@extends('layouts.app')

@section('titulo', 'Crear Nueva Cuenta')

@section('contenido')
<div class="max-w-2xl mx-auto space-y-6">
    <div class="pb-5 border-b border-slate-200">
        <a href="{{ route('usuarios.index') }}" class="inline-flex items-center text-sm font-medium text-amber-600 hover:text-amber-700 mb-2">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            Volver a la lista de usuarios
        </a>
        <h1 class="text-2xl font-bold tracking-tight text-slate-900">Crear nueva cuenta</h1>
        <p class="text-sm text-slate-500 mt-1">
            Complete los datos de la cuenta. Las cuentas nuevas se crean en estado activo de forma automática.
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
        <form method="POST" action="{{ route('usuarios.store') }}" enctype="multipart/form-data" class="space-y-6">
            @csrf

            <!-- Nombre Completo -->
            <div>
                <label for="name" class="block text-sm font-medium text-slate-700 mb-1">
                    Nombre completo <span class="text-rose-500">*</span>
                </label>
                <input type="text" 
                       name="name" 
                       id="name" 
                       value="{{ old('name') }}" 
                       required 
                       placeholder="Ej. Juan Carlos Pérez Rojas"
                       class="w-full rounded-lg border border-slate-300 px-3.5 py-2 text-sm text-slate-900 focus:border-amber-500 focus:ring-2 focus:ring-amber-200 focus:outline-hidden transition">
                <p class="text-xs text-slate-400 mt-1">Nombres y apellidos de la persona.</p>
            </div>

            <!-- Correo Electrónico -->
            <div>
                <label for="email" class="block text-sm font-medium text-slate-700 mb-1">
                    Correo electrónico <span class="text-rose-500">*</span>
                </label>
                <input type="email" 
                       name="email" 
                       id="email" 
                       value="{{ old('email') }}" 
                       required 
                       placeholder="ejemplo@quepreparamos.com"
                       class="w-full rounded-lg border border-slate-300 px-3.5 py-2 text-sm text-slate-900 focus:border-amber-500 focus:ring-2 focus:ring-amber-200 focus:outline-hidden transition">
                <p class="text-xs text-slate-400 mt-1">Debe ser único. Se utilizará para iniciar sesión.</p>
            </div>

            <!-- Rol (RN-03: Administrador por defecto) -->
            <div>
                <label for="rol" class="block text-sm font-medium text-slate-700 mb-1">
                    Rol en el sistema <span class="text-rose-500">*</span>
                </label>
                <select name="rol" 
                        id="rol" 
                        required 
                        class="w-full rounded-lg border border-slate-300 px-3.5 py-2 text-sm text-slate-900 focus:border-amber-500 focus:ring-2 focus:ring-amber-200 focus:outline-hidden transition">
                    <option value="administrador" {{ old('rol', 'administrador') === 'administrador' ? 'selected' : '' }}>
                        Administrador (acceso completo a la plataforma web)
                    </option>
                    <option value="usuario" {{ old('rol') === 'usuario' ? 'selected' : '' }}>
                        Usuario normal (acceso a la aplicación móvil)
                    </option>
                </select>
                <p class="text-xs text-slate-400 mt-1">Seleccionado "Administrador" por defecto para este panel.</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
                <!-- Contraseña -->
                <div>
                    <label for="password" class="block text-sm font-medium text-slate-700 mb-1">
                        Contraseña <span class="text-rose-500">*</span>
                    </label>
                    <input type="password" 
                           name="password" 
                           id="password" 
                           required 
                           placeholder="Mínimo 12 caracteres"
                           class="w-full rounded-lg border border-slate-300 px-3.5 py-2 text-sm text-slate-900 focus:border-amber-500 focus:ring-2 focus:ring-amber-200 focus:outline-hidden transition">
                    <p class="text-xs text-slate-400 mt-1">Mínimo 12 caracteres. Permite frases largas.</p>
                </div>

                <!-- Confirmación de Contraseña -->
                <div>
                    <label for="password_confirmation" class="block text-sm font-medium text-slate-700 mb-1">
                        Confirmar contraseña <span class="text-rose-500">*</span>
                    </label>
                    <input type="password" 
                           name="password_confirmation" 
                           id="password_confirmation" 
                           required 
                           placeholder="Repita la contraseña"
                           class="w-full rounded-lg border border-slate-300 px-3.5 py-2 text-sm text-slate-900 focus:border-amber-500 focus:ring-2 focus:ring-amber-200 focus:outline-hidden transition">
                    <p class="text-xs text-slate-400 mt-1">Debe coincidir con la contraseña ingresada.</p>
                </div>
            </div>

            <!-- Foto de Perfil (Opcional) -->
            <div class="pt-2">
                <label for="foto_perfil" class="block text-sm font-medium text-slate-700 mb-1">
                    Foto de perfil <span class="text-slate-400 text-xs">(Opcional)</span>
                </label>
                <input type="file" 
                       name="foto_perfil" 
                       id="foto_perfil" 
                       accept="image/png, image/jpeg, image/jpg, image/webp"
                       class="block w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-amber-50 file:text-amber-700 hover:file:bg-amber-100 transition">
                <p class="text-xs text-slate-400 mt-1">Formatos permitidos: JPG, PNG o WEBP. Máximo 2 MB. Si no se sube foto, se mostrarán las iniciales.</p>
            </div>

            <!-- Botones -->
            <div class="flex items-center justify-end space-x-3 pt-4 border-t border-slate-200">
                <a href="{{ route('usuarios.index') }}" 
                   class="px-4 py-2 border border-slate-300 rounded-lg text-sm font-medium text-slate-700 hover:bg-slate-50 transition">
                    Cancelar
                </a>
                <button type="submit" 
                        class="px-5 py-2 bg-amber-600 hover:bg-amber-700 text-white text-sm font-semibold rounded-lg shadow-xs transition cursor-pointer">
                    Guardar cuenta
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

