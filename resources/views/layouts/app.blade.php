<!DOCTYPE html>
<html lang="es" class="h-full bg-slate-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('titulo', 'Panel Administrativo') - ¿Qué Cocinamos?</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full font-sans antialiased text-slate-800 bg-slate-50 flex flex-col md:flex-row">
    <!-- Barra de Navegación Lateral Izquierda (Sidebar) -->
    <aside class="w-full md:w-64 bg-white border-b md:border-b-0 md:border-r border-slate-200 flex flex-col md:min-h-screen shrink-0 sticky md:top-0 z-30">
        <!-- Marca / Logotipo -->
        <div class="p-5 border-b border-slate-100 flex items-center justify-between">
            <a href="{{ route('usuarios.index') }}" class="flex items-center space-x-3 group">
                <div class="w-10 h-10 rounded-xl bg-amber-500 text-white flex items-center justify-center shadow-xs group-hover:bg-amber-600 transition">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                    </svg>
                </div>
                <div>
                    <span class="font-bold text-slate-900 text-base tracking-tight block leading-tight">¿Qué Cocinamos?</span>
                    <span class="text-xs text-amber-600 font-medium tracking-wide">Panel Web</span>
                </div>
            </a>
        </div>

        <!-- Enlaces de Navegación -->
        <nav class="flex-1 p-4 space-y-1.5 overflow-y-auto">
            <a href="{{ route('usuarios.index') }}" 
               class="flex items-center px-3.5 py-2.5 rounded-xl text-sm font-medium transition {{ request()->routeIs('usuarios.*') ? 'bg-amber-50 text-amber-800 font-semibold shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' }}">
                <svg class="w-5 h-5 mr-3 {{ request()->routeIs('usuarios.*') ? 'text-amber-600' : 'text-slate-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                </svg>
                Gestión de usuarios
            </a>

            <a href="{{ route('perfil.edit') }}" 
               class="flex items-center px-3.5 py-2.5 rounded-xl text-sm font-medium transition {{ request()->routeIs('perfil.*') ? 'bg-amber-50 text-amber-800 font-semibold shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' }}">
                <svg class="w-5 h-5 mr-3 {{ request()->routeIs('perfil.*') ? 'text-amber-600' : 'text-slate-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
                Mi perfil
            </a>
        </nav>

        <!-- Pie del Sidebar: Información del Administrador y Cerrar Sesión -->
        <div class="p-4 border-t border-slate-200 bg-slate-50/70 space-y-3">
            <div class="flex items-center space-x-3">
                @if(auth()->user()->foto_perfil)
                    <img src="{{ asset('storage/' . auth()->user()->foto_perfil) }}" alt="{{ auth()->user()->name }}" class="w-10 h-10 rounded-full object-cover border border-slate-200 shrink-0">
                @else
                    <div class="w-10 h-10 rounded-full bg-amber-100 text-amber-800 font-bold text-sm flex items-center justify-center border border-amber-200 shrink-0">
                        {{ auth()->user()->iniciales }}
                    </div>
                @endif
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-medium text-slate-900 truncate leading-tight">{{ auth()->user()->name }}</p>
                    <p class="text-xs text-slate-500 truncate">Administrador</p>
                </div>
            </div>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" 
                        class="w-full flex items-center justify-center px-3 py-2 text-xs font-medium rounded-lg text-rose-700 bg-rose-50 hover:bg-rose-100 border border-rose-200 transition cursor-pointer" 
                        title="Cerrar sesión">
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                    </svg>
                    <span>Cerrar sesión</span>
                </button>
            </form>
        </div>
    </aside>

    <!-- Área de Contenido Principal -->
    <div class="flex-1 flex flex-col min-w-0 min-h-screen">
        <main class="flex-1 p-4 sm:p-6 lg:p-8">
            <div class="max-w-6xl mx-auto">
                <!-- Mensajes de Estado Flash -->
                @if(session('exito'))
                    <div class="mb-6 bg-emerald-50 border-l-4 border-emerald-500 p-4 rounded-r-lg shadow-xs flex items-center justify-between">
                        <div class="flex items-center">
                            <svg class="w-5 h-5 text-emerald-500 mr-3 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                            </svg>
                            <p class="text-sm font-medium text-emerald-800">{{ session('exito') }}</p>
                        </div>
                    </div>
                @endif

                @if(session('error'))
                    <div class="mb-6 bg-rose-50 border-l-4 border-rose-500 p-4 rounded-r-lg shadow-xs flex items-center justify-between">
                        <div class="flex items-center">
                            <svg class="w-5 h-5 text-rose-500 mr-3 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                            </svg>
                            <p class="text-sm font-medium text-rose-800">{{ session('error') }}</p>
                        </div>
                    </div>
                @endif

                @yield('contenido')
            </div>
        </main>

        <!-- Pie de Página -->
        <footer class="bg-white border-t border-slate-200 py-4 text-center text-xs text-slate-500">
            <div class="max-w-6xl mx-auto px-4">
                &copy; {{ date('Y') }} ¿Qué Cocinamos? &bull; Módulo Web Administrativo de Usuarios
            </div>
        </footer>
    </div>
</body>
</html>
