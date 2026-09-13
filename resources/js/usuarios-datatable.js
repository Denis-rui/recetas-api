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
    let timerDebounce = null;
    let intervaloCuentaRegresiva = null;
    let ultimaBusquedaEnviada = "";

    function limpiarEstadoCarga() {
        $(tablaElemento)
            .find("tbody")
            .removeClass("opacity-40 pointer-events-none");
        $("#tabla-usuarios_processing").hide();
    }

    function mostrarAvisoBusqueda(mensaje) {
        const $aviso = $("#aviso-busqueda");
        const $texto = $("#texto-aviso-busqueda");
        if ($aviso.length && $texto.length) {
            $texto.text(mensaje);
            $aviso.removeClass("hidden");
        }
    }

    function ocultarAvisoBusqueda() {
        const $aviso = $("#aviso-busqueda");
        const $texto = $("#texto-aviso-busqueda");
        if ($aviso.length) {
            $aviso.addClass("hidden");
            $texto.text("");
        }
    }

    function mostrarAlertaLimite(segundosEspera, mensajeError) {
        if (intervaloCuentaRegresiva) {
            clearInterval(intervaloCuentaRegresiva);
            intervaloCuentaRegresiva = null;
        }

        const $contenedor = $("#contenedor-alerta-datatable");
        const $mensaje = $("#alerta-datatable-mensaje");
        const $espera = $("#alerta-datatable-espera");
        const $btnReintentar = $("#btn-reintentar-datatable");

        $mensaje.text(
            mensajeError ||
                "Has superado el límite de consultas permitidas. Por favor, espera antes de reintentar.",
        );
        $contenedor.removeClass("hidden");

        let restante = segundosEspera;
        $espera
            .text(`Podrás reintentar en ${restante} segundos.`)
            .removeClass("hidden");
        $btnReintentar.removeClass("hidden").prop("disabled", true);

        intervaloCuentaRegresiva = setInterval(function () {
            restante--;
            if (restante > 0) {
                $espera.text(`Podrás reintentar en ${restante} segundos.`);
            } else {
                clearInterval(intervaloCuentaRegresiva);
                intervaloCuentaRegresiva = null;
                $espera.text(
                    "El tiempo de espera ha concluido. Ya puedes reintentar la consulta.",
                );
                $btnReintentar.prop("disabled", false);
            }
        }, 1000);

        $btnReintentar.off("click").on("click", function () {
            if (intervaloCuentaRegresiva) {
                return; // Período de espera en curso
            }
            ocultarAlertaLimite();
            tabla.ajax.reload(null, false);
        });
    }

    function ocultarAlertaLimite() {
        if (intervaloCuentaRegresiva) {
            clearInterval(intervaloCuentaRegresiva);
            intervaloCuentaRegresiva = null;
        }
        $("#contenedor-alerta-datatable").addClass("hidden");
        $("#alerta-datatable-espera").addClass("hidden").text("");
        $("#btn-reintentar-datatable")
            .addClass("hidden")
            .prop("disabled", true);
    }

    function ejecutarBusquedaConUmbral(valorOriginal) {
        const valorLimpio = (valorOriginal || "").trim();
        // Contar caracteres Unicode correctamente (soporte acentos, eñes y caracteres multibyte)
        const longitud = Array.from(valorLimpio).length;

        if (longitud === 0) {
            ocultarAvisoBusqueda();
            if (ultimaBusquedaEnviada !== "") {
                ultimaBusquedaEnviada = "";
                tabla.search("").draw();
            }
        } else if (longitud === 1) {
            // No enviar petición asíncrona innecesaria al servidor
            if (ultimaBusquedaEnviada !== "") {
                mostrarAvisoBusqueda(
                    `Escribe al menos 2 caracteres para buscar (mostrando resultados de la consulta anterior: "${ultimaBusquedaEnviada}")`,
                );
            } else {
                mostrarAvisoBusqueda(
                    "Escribe al menos 2 caracteres para buscar",
                );
            }
        } else {
            // 2 o más caracteres: sanear tope de 100 caracteres y buscar
            ocultarAvisoBusqueda();
            const terminoSanitizado = Array.from(valorLimpio).slice(0, 100).join("");
            if (terminoSanitizado !== ultimaBusquedaEnviada) {
                ultimaBusquedaEnviada = terminoSanitizado;
                tabla.search(terminoSanitizado).draw();
            }
        }
    }

    const tabla = $(tablaElemento).DataTable({
        destroy: true, // Permite reinicializar limpiamente sin lanzar advertencia tn/3
        serverSide: true,
        processing: true,
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
                limpiarEstadoCarga();

                if (status === "abort") {
                    return; // Cancelación esperada por solicitud concurrente
                }

                if (xhr.status === 401 || xhr.status === 403) {
                    window.location.href = loginUrl;
                    return;
                }

                if (xhr.status === 429) {
                    let segundosEspera = parseInt(
                        xhr.getResponseHeader("Retry-After"),
                        10,
                    );
                    if (isNaN(segundosEspera) || segundosEspera <= 0) {
                        segundosEspera =
                            parseInt(xhr.responseJSON?.retry_after, 10) || 60;
                    }
                    const mensajeError =
                        xhr.responseJSON?.message ||
                        "Has superado el límite de consultas permitidas. Por favor, espera antes de reintentar.";
                    mostrarAlertaLimite(segundosEspera, mensajeError);
                    return;
                }

                if (xhr.status === 422) {
                    const mensajeValidacion =
                        xhr.responseJSON?.errors?.search?.[0] ||
                        xhr.responseJSON?.message ||
                        "Escribe al menos 2 caracteres para buscar.";
                    mostrarAvisoBusqueda(mensajeValidacion);
                    return;
                }

                console.error("Error al obtener cuentas:", error);
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
        initComplete: function () {
            const $searchInput = $("div.dataTables_filter input");

            // Desvincular manejadores nativos de DataTables para evitar doble petición y falta de debounce real
            $searchInput.off();

            // Debounce real de 400 ms desde la última pulsación de tecla
            $searchInput.on("input", function () {
                clearTimeout(timerDebounce);
                const texto = this.value;
                timerDebounce = setTimeout(function () {
                    ejecutarBusquedaConUmbral(texto);
                }, 400);
            });

            // Respuesta inmediata al pulsar Enter
            $searchInput.on("keydown", function (e) {
                if (e.key === "Enter") {
                    e.preventDefault();
                    clearTimeout(timerDebounce);
                    ejecutarBusquedaConUmbral(this.value);
                }
            });
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
            ocultarAlertaLimite();
            tabla.ajax.reload();
        });

    // Recargar tabla de forma asíncrona cuando cambie el filtro de Estado (removiendo listeners previos)
    $("#filtro_estado")
        .off("change")
        .on("change", function () {
            ocultarAlertaLimite();
            tabla.ajax.reload();
        });

    // Botón para restablecer filtros (removiendo listeners previos)
    $("#btn-limpiar-filtros")
        .off("click")
        .on("click", function () {
            clearTimeout(timerDebounce);
            $("#filtro_rol").val("");
            $("#filtro_estado").val("");
            const $searchInput = $("div.dataTables_filter input");
            $searchInput.val("");
            ultimaBusquedaEnviada = "";
            ocultarAvisoBusqueda();
            ocultarAlertaLimite();
            tabla.search("").draw();
        });
}
