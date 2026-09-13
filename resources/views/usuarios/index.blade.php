@extends('layouts.app')

@section('titulo', 'Gestión de Usuarios')

@section('contenido')
<div class="space-y-6">
    <!-- Encabezado y Botón Crear -->
    <div class="sm:flex sm:items-center sm:justify-between pb-5 border-b border-slate-200">
        <div>
            
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">Gestión de usuarios</h1>
            <p class="text-sm text-slate-500 mt-1">Administración de cuentas con filtrado asíncrono en tiempo real.</p>
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

    <!-- Barra de Filtros Asíncronos -->
    <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-xs">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-center">
            <!-- Filtro por Rol -->
            <div>
                <label for="filtro_rol" class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-1.5">
                    Filtrar por rol:
                </label>
                <select id="filtro_rol" 
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-800 focus:border-amber-500 focus:ring-2 focus:ring-amber-200 focus:outline-hidden transition bg-slate-50/50">
                    <option value="">Todos los roles</option>
                    <option value="administrador">Solo Administradores</option>
                    <option value="usuario">Solo Usuarios normales</option>
                </select>
            </div>

            <!-- Filtro por Estado -->
            <div>
                <label for="filtro_estado" class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-1.5">
                    Filtrar por estado:
                </label>
                <select id="filtro_estado" 
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-800 focus:border-amber-500 focus:ring-2 focus:ring-amber-200 focus:outline-hidden transition bg-slate-50/50">
                    <option value="">Todos los estados</option>
                    <option value="activo">Solo Cuentas activas</option>
                    <option value="deshabilitado">Solo Cuentas deshabilitadas</option>
                </select>
            </div>

            <!-- Botón Limpiar Filtros -->
            <div class="flex md:justify-end items-end h-full pt-2 md:pt-0">
                <button type="button" 
                        id="btn-limpiar-filtros" 
                        class="inline-flex items-center px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-medium rounded-lg transition cursor-pointer">
                    <svg class="w-4 h-4 mr-1.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    Restablecer filtros
                </button>
            </div>
        </div>
    </div>

    <!-- Contenedor Estable de la Tabla DataTables Server-Side -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-xs p-6 overflow-hidden">
        <div class="overflow-x-auto min-h-[360px]">
            <table id="tabla-usuarios" 
                   data-url="{{ route('usuarios.index') }}" 
                   data-login-url="{{ route('login') }}" 
                   class="w-full text-left border-collapse" 
                   style="width:100%">
                <thead>
                    <tr class="border-b border-slate-200 bg-slate-50/80 text-slate-700">
                        <th class="py-3.5 px-4 text-xs font-semibold uppercase tracking-wider">Usuario</th>
                        <th class="py-3.5 px-4 text-xs font-semibold uppercase tracking-wider">Correo electrónico</th>
                        <th class="py-3.5 px-4 text-xs font-semibold uppercase tracking-wider">Rol</th>
                        <th class="py-3.5 px-4 text-xs font-semibold uppercase tracking-wider">Estado</th>
                        <th class="py-3.5 px-4 text-xs font-semibold uppercase tracking-wider text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm">
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
