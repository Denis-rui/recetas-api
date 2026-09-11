/**
 * Módulo para la gestión asíncrona de la tabla de usuarios con DataTables Server-Side.
 */
export function inicializarTablaUsuarios(config = {}) {
    const tablaElemento = document.getElementById("tabla-usuarios");
    if (
        !tablaElemento ||
        typeof window.$ === "undefined" ||
        typeof window.$.fn.DataTable === "undefined"
    ) {
        return;
    }

    const $ = window.$;

    // Suprimir alertas modales nativas de DataTables (tn/3) y manejar errores limpiamente
    $.fn.dataTable.ext.errMode = "none";

    // Si la tabla ya estaba inicializada (por ejemplo al recargar o navegar con caché), destruirla primero limpiamente
    if ($.fn.DataTable.isDataTable(tablaElemento)) {
        $(tablaElemento).DataTable().destroy();
    }

    const url = config.url || tablaElemento.dataset.url;
    const csrfToken =
        config.csrfToken || $('meta[name="csrf-token"]').attr("content");
    const loginUrl =
        config.loginUrl || tablaElemento.dataset.loginUrl || "/login";

    let peticionAjaxEnCurso = null;

    const tabla = $(tablaElemento).DataTable({
        destroy: true, // Permite reinicializar limpiamente sin lanzar advertencia tn/3
        serverSide: true,
        processing: true,
        searchDelay: 400, // Anti-rebote (debounce de 400 ms en búsquedas)
        ajax: {
            url: url,
            type: "GET",
            headers: {
                "X-CSRF-TOKEN": csrfToken,
                Accept: "application/json",
            },
            data: function (d) {
                // Incorporar filtros asíncronos adicionales
                d.filtro_rol = $("#filtro_rol").val();
                d.filtro_estado = $("#filtro_estado").val();
            },
            beforeSend: function (jqXHR) {
                // Cancelar petición anterior si el usuario sigue escribiendo o cambiando filtros
                if (
                    peticionAjaxEnCurso &&
                    peticionAjaxEnCurso.readyState !== 4
                ) {
                    peticionAjaxEnCurso.abort();
                }
                peticionAjaxEnCurso = jqXHR;
            },
            error: function (xhr, status, error) {
                if (status === "abort") {
                    return; // Cancelación esperada por debounce
                }
                if (xhr.status === 401 || xhr.status === 403) {
                    window.location.href = loginUrl;
                } else {
                    console.error("Error al obtener cuentas:", error);
                }
            },
        },
        columns: [
            { data: 0, orderable: true, className: "py-3.5 px-4" },
            { data: 1, orderable: true, className: "py-3.5 px-4" },
            { data: 2, orderable: true, className: "py-3.5 px-4" },
            { data: 3, orderable: true, className: "py-3.5 px-4" },
            { data: 4, orderable: false, className: "py-3.5 px-4 text-right" },
        ],
        order: [[4, "desc"]],
        lengthMenu: [10, 25, 50],
        pageLength: 10,
        language: {
            search: "Buscar:",
            searchPlaceholder: "Nombre o correo...",
            lengthMenu: "Mostrar _MENU_ cuentas",
            info: "Mostrando _START_ a _END_ de _TOTAL_ cuentas",
            infoEmpty: "No hay cuentas disponibles",
            infoFiltered: "(filtrado de un total de _MAX_ cuentas)",
            zeroRecords:
                "No se encontraron cuentas que coincidan con la búsqueda.",
            emptyTable: "No existen cuentas registradas en el sistema.",
            processing: `
                <div class="flex items-center justify-center space-x-2.5 px-4 py-2.5 bg-white/95 rounded-xl shadow-lg border border-slate-200/80 text-slate-700 text-sm font-medium backdrop-blur-xs">
                    <svg class="animate-spin h-5 w-5 text-amber-600 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span>Cargando datos...</span>
                </div>
            `,
            loadingRecords: `
                <div class="flex items-center justify-center space-x-2.5 py-6 text-slate-500 text-sm font-medium">
                    <svg class="animate-spin h-5 w-5 text-amber-600 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span>Cargando datos...</span>
                </div>
            `,
            paginate: {
                first: "Primero",
                previous: "Anterior",
                next: "Siguiente",
                last: "Último",
            },
        },
    });

    // Control visual focalizado ÚNICAMENTE en las filas del cuerpo (tbody)
    tabla.on("processing.dt", function (e, settings, processing) {
        const $tbody = $(tablaElemento).find("tbody");

        if (processing) {
            $tbody.addClass("opacity-40 pointer-events-none");
        } else {
            $tbody.removeClass("opacity-40 pointer-events-none");
        }
    });

    // Recargar tabla de forma asíncrona cuando cambie el filtro de Rol (removiendo listeners previos)
    $("#filtro_rol")
        .off("change")
        .on("change", function () {
            tabla.ajax.reload();
        });

    // Recargar tabla de forma asíncrona cuando cambie el filtro de Estado (removiendo listeners previos)
    $("#filtro_estado")
        .off("change")
        .on("change", function () {
            tabla.ajax.reload();
        });

    // Botón para restablecer filtros (removiendo listeners previos)
    $("#btn-limpiar-filtros")
        .off("click")
        .on("click", function () {
            $("#filtro_rol").val("");
            $("#filtro_estado").val("");
            tabla.search("").draw();
        });
}
