@extends('layouts.app')

@section('titulo', 'Gestión de Usuarios')

@section('contenido')
<div class="space-y-6">
    <!-- Encabezado y Botón Crear -->
    <div class="sm:flex sm:items-center sm:justify-between pb-5 border-b border-slate-200">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">Gestión de usuarios</h1>
            <p class="text-sm text-slate-500 mt-1">Administración de cuentas con búsqueda y filtrado asíncrono en tiempo real.</p>
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

    <!-- Contenedor de la Tabla DataTables Server-Side -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-xs p-6 overflow-hidden">
        <div class="overflow-x-auto">
            <table id="tabla-usuarios" class="w-full text-left border-collapse" style="width:100%">
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
                    <!-- DataTables cargará las filas de forma asíncrona -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Estilos para integrar DataTables con la estética de la aplicación -->
<style>
    /* Personalización visual de DataTables con Tailwind */
    .dataTables_wrapper .dataTables_length,
    .dataTables_wrapper .dataTables_filter {
        margin-bottom: 1.25rem;
    }
    .dataTables_wrapper .dataTables_length label,
    .dataTables_wrapper .dataTables_filter label {
        font-size: 0.875rem;
        color: #475569;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    .dataTables_wrapper .dataTables_length select {
        border-radius: 0.5rem;
        border: 1px solid #cbd5e1;
        padding: 0.35rem 0.75rem;
        font-size: 0.875rem;
        background-color: #f8fafc;
        outline: none;
    }
    .dataTables_wrapper .dataTables_filter input {
        border-radius: 0.5rem;
        border: 1px solid #cbd5e1;
        padding: 0.35rem 0.75rem;
        font-size: 0.875rem;
        width: 16rem;
        outline: none;
        transition: all 0.2s;
    }
    .dataTables_wrapper .dataTables_filter input:focus {
        border-color: #f59e0b;
        box-shadow: 0 0 0 2px rgba(245, 158, 11, 0.2);
    }
    .dataTables_wrapper .dataTables_info {
        font-size: 0.875rem;
        color: #64748b;
        padding-top: 1rem;
    }
    .dataTables_wrapper .dataTables_paginate {
        padding-top: 1rem;
        display: flex;
        justify-content: flex-end;
        gap: 0.25rem;
    }
    .dataTables_wrapper .dataTables_paginate .paginate_button {
        padding: 0.35rem 0.75rem;
        font-size: 0.875rem;
        border-radius: 0.5rem;
        border: 1px solid #e2e8f0;
        background: #ffffff;
        color: #334155 !important;
        cursor: pointer;
        transition: all 0.15s;
    }
    .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
        background: #f1f5f9 !important;
        color: #0f172a !important;
        border-color: #cbd5e1;
    }
    .dataTables_wrapper .dataTables_paginate .paginate_button.current {
        background: #f59e0b !important;
        color: #ffffff !important;
        border-color: #f59e0b !important;
        font-weight: 600;
    }
    .dataTables_wrapper .dataTables_paginate .paginate_button.disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }
    .dataTables_wrapper .dataTables_processing {
        background: rgba(255, 255, 255, 0.85);
        border: 1px solid #f1f5f9;
        border-radius: 0.75rem;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
    }
</style>

<!-- Inclusión de jQuery y DataTables CDN -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>

<script>
$(document).ready(function() {
    let peticionAjaxEnCurso = null;

    // Inicialización de DataTables en modo Server-Side
    const tabla = $('#tabla-usuarios').DataTable({
        serverSide: true,
        processing: true,
        searchDelay: 400, // Regla de rendimiento: Anti-rebote (debounce de 400 ms)
        ajax: {
            url: "{{ route('usuarios.index') }}",
            type: "GET",
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                'Accept': 'application/json'
            },
            data: function(d) {
                // Incorporar filtros asíncronos adicionales en la petición
                d.filtro_rol = $('#filtro_rol').val();
                d.filtro_estado = $('#filtro_estado').val();
            },
            beforeSend: function(jqXHR) {
                // Regla de rendimiento: Cancelar petición obsoleta si aún está en vuelo
                if (peticionAjaxEnCurso && peticionAjaxEnCurso.readyState !== 4) {
                    peticionAjaxEnCurso.abort();
                }
                peticionAjaxEnCurso = jqXHR;
            },
            error: function(xhr, status, error) {
                if (status === 'abort') {
                    // Petición cancelada intencionalmente por debounce; no es un error
                    return;
                }
                // Si la sesión expiró o la cuenta fue deshabilitada en tiempo real
                if (xhr.status === 401 || xhr.status === 403) {
                    window.location.href = "{{ route('login') }}";
                } else {
                    console.error("Error al obtener cuentas:", error);
                }
            }
        },
        columns: [
            { data: 0, orderable: true, className: "py-3.5 px-4" },
            { data: 1, orderable: true, className: "py-3.5 px-4" },
            { data: 2, orderable: true, className: "py-3.5 px-4" },
            { data: 3, orderable: true, className: "py-3.5 px-4" },
            { data: 4, orderable: false, className: "py-3.5 px-4 text-right" } // Columna de acciones no ordenable
        ],
        order: [[4, 'desc']], // Ordenar inicialmente por ID descendente
        lengthMenu: [10, 25, 50], // Opciones de paginación controladas
        pageLength: 10,
        language: {
            processing: '<div class="flex items-center justify-center py-4 text-amber-600 font-medium text-sm"><svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-amber-600" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg> Cargando cuentas...</div>',
            search: "Buscar:",
            searchPlaceholder: "Nombre o correo...",
            lengthMenu: "Mostrar _MENU_ cuentas",
            info: "Mostrando _START_ a _END_ de _TOTAL_ cuentas",
            infoEmpty: "No hay cuentas disponibles",
            infoFiltered: "(filtrado de un total de _MAX_ cuentas)",
            zeroRecords: "No se encontraron cuentas que coincidan con la búsqueda.",
            emptyTable: "No existen cuentas registradas en el sistema.",
            paginate: {
                first: "Primero",
                previous: "Anterior",
                next: "Siguiente",
                last: "Último"
            }
        }
    });

    // Recargar tabla de forma asíncrona cuando cambie el filtro de Rol
    $('#filtro_rol').on('change', function() {
        tabla.ajax.reload();
    });

    // Recargar tabla de forma asíncrona cuando cambie el filtro de Estado
    $('#filtro_estado').on('change', function() {
        tabla.ajax.reload();
    });

    // Botón para restablecer filtros
    $('#btn-limpiar-filtros').on('click', function() {
        $('#filtro_rol').val('');
        $('#filtro_estado').val('');
        tabla.search('').draw();
    });
});
</script>
@endsection
