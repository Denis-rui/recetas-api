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

    <!-- Contenedor de Alertas Asíncronas (Límite 429, Errores de Conexión) -->
    <div id="contenedor-alerta-datatable" class="hidden">
        <div class="bg-amber-50 border-l-4 border-amber-500 p-4 rounded-r-xl shadow-xs flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div class="flex items-start sm:items-center space-x-3">
                <svg id="alerta-datatable-icono" class="w-5 h-5 text-amber-600 shrink-0 mt-0.5 sm:mt-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                </svg>
                <div>
                    <p id="alerta-datatable-mensaje" class="text-sm font-medium text-amber-900 leading-tight"></p>
                    <p id="alerta-datatable-espera" class="text-xs text-amber-700 mt-1 hidden"></p>
                </div>
            </div>
            <div class="flex items-center space-x-2 shrink-0">
                <button type="button" 
                        id="btn-reintentar-datatable" 
                        class="hidden items-center px-3 py-1.5 rounded-lg bg-amber-600 hover:bg-amber-700 text-white text-xs font-semibold shadow-xs transition cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed">
                    <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    <span>Reintentar</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Aviso Informativo para Búsqueda (Umbral mínimo de 2 caracteres y consulta previa) -->
    <div id="aviso-busqueda" class="hidden px-4 py-2.5 bg-amber-50/90 border border-amber-200 rounded-xl text-xs font-medium text-amber-800 flex items-center space-x-2.5 shadow-xs">
        <svg class="w-4 h-4 text-amber-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <span id="texto-aviso-busqueda"></span>
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
