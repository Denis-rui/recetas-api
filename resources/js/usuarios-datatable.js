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
    const url = config.url || tablaElemento.dataset.url;
    const csrfToken =
        config.csrfToken || $('meta[name="csrf-token"]').attr("content");
    const loginUrl =
        config.loginUrl || tablaElemento.dataset.loginUrl || "/login";

    let peticionAjaxEnCurso = null;

    const tabla = $(tablaElemento).DataTable({
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
        const $indicador = $("#indicador-carga-datos");

        if (processing) {
            $tbody.addClass("opacity-40 pointer-events-none");
            $indicador.removeClass("hidden");
        } else {
            $tbody.removeClass("opacity-40 pointer-events-none");
            $indicador.addClass("hidden");
        }
    });

    // Recargar tabla de forma asíncrona cuando cambie el filtro de Rol
    $("#filtro_rol").on("change", function () {
        tabla.ajax.reload();
    });

    // Recargar tabla de forma asíncrona cuando cambie el filtro de Estado
    $("#filtro_estado").on("change", function () {
        tabla.ajax.reload();
    });

    // Botón para restablecer filtros
    $("#btn-limpiar-filtros").on("click", function () {
        $("#filtro_rol").val("");
        $("#filtro_estado").val("");
        tabla.search("").draw();
    });
}
