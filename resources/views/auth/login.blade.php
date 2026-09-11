@extends('layouts.guest')

@section('titulo', 'Iniciar Sesión')

@section('contenido')

    <h2 class="text-xl font-bold text-slate-900 mb-6 text-center">Iniciar sesión</h2>

    @if ($errors->any())
        <div class="mb-5 rounded-lg bg-rose-50 border border-rose-200 p-4">
            <div class="flex items-start">
                <svg class="h-5 w-5 text-rose-500 mt-0.5 mr-2 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                </svg>
                <div class="text-sm text-rose-700">
                    <ul class="list-disc pl-4 space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    @endif

    <form method="POST" action="{{ route('login') }}" class="space-y-5">
        @csrf

        <!-- Correo Electrónico -->
        <div>
            <label for="email" class="block text-sm font-medium text-slate-700 mb-1">Correo electrónico</label>
            <input type="email" 
                   name="email" 
                   id="email" 
                   value="{{ old('email') }}" 
                   required 
                   autofocus 
                   placeholder="ejemplo@quecocinamos.com"
                   class="w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm text-slate-900 focus:border-amber-500 focus:ring-2 focus:ring-amber-200 focus:outline-hidden transition">
        </div>

        <!-- Contraseña -->
        <div>
            <label for="password" class="block text-sm font-medium text-slate-700 mb-1">Contraseña</label>
            <input type="password" 
                   name="password" 
                   id="password" 
                   required 
                   placeholder="••••••••••••"
                   class="w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm text-slate-900 focus:border-amber-500 focus:ring-2 focus:ring-amber-200 focus:outline-hidden transition">
        </div>

        <!-- Recordar Sesión -->
        <div class="flex items-center">
            <input type="checkbox" 
                   name="remember" 
                   id="remember" 
                   class="h-4 w-4 rounded border-slate-300 text-amber-600 focus:ring-amber-500">
            <label for="remember" class="ml-2 block text-sm text-slate-600 cursor-pointer">
                Mantener mi sesión iniciada
            </label>
        </div>

        <!-- Botón Iniciar Sesión -->
        <div>
            <button type="submit" 
                    class="w-full flex justify-center py-2.5 px-4 border border-transparent rounded-lg shadow-sm text-sm font-semibold text-white bg-amber-600 hover:bg-amber-700 focus:outline-hidden focus:ring-2 focus:ring-offset-2 focus:ring-amber-500 transition cursor-pointer">
                Iniciar sesión
            </button>
        </div>
    </form>
@endsection
