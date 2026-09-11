@extends('layouts.app')

@section('titulo', 'Gestión de Usuarios')

@section('contenido')
<div class="space-y-6">
    <!-- Encabezado y Barra de Acciones -->
    <div class="sm:flex sm:items-center sm:justify-between pb-5 border-b border-slate-200">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">Gestión de usuarios</h1>
            <p class="text-sm text-slate-500 mt-1">Administración de cuentas, roles y control de acceso a la plataforma.</p>
        </div>
        <div class="mt-4 sm:mt-0">
            <a href="{{ route('usuarios.create') }}" 
               class="inline-flex items-center px-4 py-2 rounded-lg bg-amber-600 hover:bg-amber-700 text-white text-sm font-semibold shadow-xs hover:shadow transition">
                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Crear cuenta
            </a>
        </div>
    </div>

    <!-- Buscador -->
    <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs">
        <form method="GET" action="{{ route('usuarios.index') }}" class="flex flex-col sm:flex-row gap-3">
            <div class="relative flex-1">
                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
                <input type="text" 
                       name="buscar" 
                       value="{{ $buscar }}" 
                       placeholder="Buscar por nombre o correo electrónico..." 
                       class="w-full pl-10 pr-4 py-2 text-sm rounded-lg border border-slate-300 focus:border-amber-500 focus:ring-2 focus:ring-amber-200 focus:outline-hidden transition">
            </div>
            <div class="flex items-center space-x-2">
                <button type="submit" 
                        class="px-4 py-2 bg-slate-800 hover:bg-slate-900 text-white text-sm font-medium rounded-lg transition cursor-pointer">
                    Buscar
                </button>
                @if($buscar !== '')
                    <a href="{{ route('usuarios.index') }}" 
                       class="px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-medium rounded-lg transition">
                        Limpiar
                    </a>
                @endif
            </div>
        </form>
    </div>

    <!-- Tabla de Cuentas -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-xs overflow-hidden">
        @if($usuarios->isEmpty())
            <div class="text-center py-12 px-4">
                <div class="w-12 h-12 rounded-full bg-slate-100 text-slate-400 flex items-center justify-center mx-auto mb-3">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                    </svg>
                </div>
                <h3 class="text-sm font-semibold text-slate-800">No se encontraron cuentas</h3>
                <p class="text-sm text-slate-500 mt-1">
                    @if($buscar !== '')
                        No existen cuentas que coincidan con "{{ $buscar }}". Intente con otro término.
                    @else
                        No hay cuentas registradas en el sistema.
                    @endif
                </p>
                @if($buscar !== '')
                    <div class="mt-4">
                        <a href="{{ route('usuarios.index') }}" class="text-sm text-amber-600 font-medium hover:underline">
                            Ver todas las cuentas
                        </a>
                    </div>
                @endif
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th scope="col" class="py-3.5 pl-6 pr-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">
                                Usuario
                            </th>
                            <th scope="col" class="px-3 py-3.5 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">
                                Correo electrónico
                            </th>
                            <th scope="col" class="px-3 py-3.5 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">
                                Rol
                            </th>
                            <th scope="col" class="px-3 py-3.5 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">
                                Estado
                            </th>
                            <th scope="col" class="py-3.5 pl-3 pr-6 text-right text-xs font-semibold text-slate-600 uppercase tracking-wider">
                                Acciones
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 bg-white">
                        @foreach($usuarios as $u)
                            <tr class="hover:bg-slate-50 transition">
                                <!-- Foto / Iniciales y Nombre -->
                                <td class="py-4 pl-6 pr-3 whitespace-nowrap">
                                    <div class="flex items-center space-x-3">
                                        @if($u->foto_perfil)
                                            <img src="{{ asset('storage/' . $u->foto_perfil) }}" alt="{{ $u->name }}" class="w-10 h-10 rounded-full object-cover border border-slate-200 shrink-0">
                                        @else
                                            <div class="w-10 h-10 rounded-full bg-amber-100 text-amber-800 font-bold text-xs flex items-center justify-center border border-amber-200 shrink-0">
                                                {{ $u->iniciales }}
                                            </div>
                                        @endif
                                        <div>
                                            <div class="font-semibold text-slate-900 text-sm flex items-center">
                                                {{ $u->name }}
                                                @if($u->id === auth()->id())
                                                    <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800">
                                                        Tú
                                                    </span>
                                                @endif
                                            </div>
                                            <div class="text-xs text-slate-400">ID: #{{ $u->id }}</div>
                                        </div>
                                    </div>
                                </td>

                                <!-- Correo Electrónico -->
                                <td class="px-3 py-4 whitespace-nowrap text-sm text-slate-600">
                                    {{ $u->email }}
                                </td>

                                <!-- Rol -->
                                <td class="px-3 py-4 whitespace-nowrap text-sm">
                                    @if($u->rol === 'administrador')
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-indigo-50 text-indigo-700 border border-indigo-200">
                                            Administrador
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-700 border border-slate-200">
                                            Usuario normal
                                        </span>
                                    @endif
                                </td>

                                <!-- Estado -->
                                <td class="px-3 py-4 whitespace-nowrap text-sm">
                                    @if($u->activo)
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 mr-1.5"></span>
                                            Activo
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-rose-50 text-rose-700 border border-rose-200">
                                            <span class="w-1.5 h-1.5 rounded-full bg-rose-500 mr-1.5"></span>
                                            Deshabilitado
                                        </span>
                                    @endif
                                </td>

                                <!-- Acciones -->
                                <td class="py-4 pl-3 pr-6 whitespace-nowrap text-right text-sm">
                                    <div class="flex items-center justify-end space-x-2">
                                        <!-- Editar Datos -->
                                        <a href="{{ route('usuarios.edit', $u) }}" 
                                           class="inline-flex items-center px-2.5 py-1.5 text-xs font-medium rounded-lg text-slate-700 bg-white hover:bg-slate-100 border border-slate-300 transition"
                                           title="Editar cuenta">
                                            Editar
                                        </a>

                                        @if($u->id === auth()->id())
                                            <!-- Protección de Cuenta Propia (RN-06, RN-07) -->
                                            <span class="text-xs text-slate-400 italic px-2">
                                                Cuenta protegida
                                            </span>
                                        @else
                                            <!-- Cambiar Rol (RF-07) -->
                                            <form method="POST" action="{{ route('usuarios.cambiar-rol', $u) }}" class="inline"
                                                  onsubmit="return confirm('¿Confirma que desea cambiar el rol de {{ $u->name }} a {{ $u->rol === 'administrador' ? 'Usuario normal' : 'Administrador' }}?');">
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="rol" value="{{ $u->rol === 'administrador' ? 'usuario' : 'administrador' }}">
                                                <button type="submit" 
                                                        class="inline-flex items-center px-2.5 py-1.5 text-xs font-medium rounded-lg text-indigo-700 bg-indigo-50 hover:bg-indigo-100 border border-indigo-200 transition cursor-pointer"
                                                        title="Cambiar rol">
                                                    {{ $u->rol === 'administrador' ? 'Hacer Usuario normal' : 'Hacer Administrador' }}
                                                </button>
                                            </form>

                                            <!-- Deshabilitar o Reactivar (RF-08, RF-09) -->
                                            @if($u->activo)
                                                <form method="POST" action="{{ route('usuarios.deshabilitar', $u) }}" class="inline"
                                                      onsubmit="return confirm('¿Está seguro de deshabilitar la cuenta de {{ $u->name }}? Esta acción cerrará sus sesiones y bloqueará su acceso.');">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit" 
                                                            class="inline-flex items-center px-2.5 py-1.5 text-xs font-medium rounded-lg text-rose-700 bg-rose-50 hover:bg-rose-100 border border-rose-200 transition cursor-pointer"
                                                            title="Deshabilitar cuenta">
                                                        Deshabilitar
                                                    </button>
                                                </form>
                                            @else
                                                <form method="POST" action="{{ route('usuarios.reactivar', $u) }}" class="inline"
                                                      onsubmit="return confirm('¿Desea reactivar la cuenta de {{ $u->name }}?');">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit" 
                                                            class="inline-flex items-center px-2.5 py-1.5 text-xs font-medium rounded-lg text-emerald-700 bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 transition cursor-pointer"
                                                            title="Reactivar cuenta">
                                                        Reactivar
                                                    </button>
                                                </form>
                                            @endif
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Paginación -->
            @if($usuarios->hasPages())
                <div class="px-6 py-4 border-t border-slate-200 bg-slate-50">
                    {{ $usuarios->links() }}
                </div>
            @endif
        @endif
    </div>
</div>
@endsection

