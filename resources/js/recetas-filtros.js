const ESPERA_BUSQUEDA_MS = 400;
const TIEMPO_LIMITE_MS = 15000;
const MINIMO_CARACTERES = 2;

export function inicializarFiltrosRecetas() {
    const formulario = document.getElementById("filtros-recetas");
    const listado = document.getElementById("listado-recetas");

    if (!formulario || !listado) {
        return;
    }

    const entradaBusqueda = formulario.elements.namedItem("buscar");
    const filtroClasificacion = formulario.elements.namedItem("clasificacion");
    const filtroCategoria = formulario.elements.namedItem("categoria_id");
    const filtroSubcategoria = formulario.elements.namedItem("subcategoria_id");
    const aviso = document.getElementById("aviso-filtros-recetas");
    const textoAviso = document.getElementById("texto-aviso-filtros-recetas");
    const botonReintentar = document.getElementById("reintentar-filtros-recetas");
    const botonLimpiar = document.getElementById("limpiar-filtros-recetas");
    const totalRecetas = document.getElementById("total-recetas-encontradas");
    const opcionesSubcategorias = Array.from(filtroSubcategoria.options, (opcion) => opcion.cloneNode(true));

    let temporizadorBusqueda = null;
    let temporizadorLimite = null;
    let temporizadorSolicitud = null;
    let controladorSolicitud = null;
    let numeroSolicitud = 0;
    let bloqueadoHasta = 0;
    let urlReintento = null;
    let solicitudExpirada = false;

    function detenerSolicitudActiva() {
        numeroSolicitud++;

        if (controladorSolicitud) {
            controladorSolicitud.abort();
            controladorSolicitud = null;
        }

        if (temporizadorSolicitud) {
            clearTimeout(temporizadorSolicitud);
            temporizadorSolicitud = null;
        }

        listado.setAttribute("aria-busy", "false");
        listado.classList.remove("pointer-events-none", "opacity-50");
    }

    function ocultarAviso() {
        aviso.classList.add("hidden");
        aviso.classList.remove("flex");
        textoAviso.textContent = "";
        botonReintentar.classList.add("hidden");
        botonReintentar.disabled = false;
        botonReintentar.textContent = "Reintentar";

        if (temporizadorLimite) {
            clearInterval(temporizadorLimite);
            temporizadorLimite = null;
        }
    }

    function mostrarAviso(mensaje, { permitirReintento = false } = {}) {
        textoAviso.textContent = mensaje;
        aviso.classList.remove("hidden");
        aviso.classList.add("flex");
        botonReintentar.classList.toggle("hidden", !permitirReintento);
        botonReintentar.disabled = !permitirReintento;
    }

    function sincronizarUrlEnFormulario(url) {
        const parametros = new URL(url, window.location.href).searchParams;

        entradaBusqueda.value = parametros.get("buscar") ?? "";
        filtroClasificacion.value = parametros.get("clasificacion") ?? "todas";
        filtroCategoria.value = parametros.get("categoria_id") ?? "";
        filtroSubcategoria.value = "";
        actualizarOpcionesSubcategoria();

        const subcategoriaUrl = parametros.get("subcategoria_id") ?? "";
        if (Array.from(filtroSubcategoria.options).some((opcion) => opcion.value === subcategoriaUrl)) {
            filtroSubcategoria.value = subcategoriaUrl;
        }
    }

    function actualizarOpcionesSubcategoria() {
        const categoriaSeleccionada = filtroCategoria.value;
        const subcategoriaSeleccionada = filtroSubcategoria.value;
        const opcionInicial = opcionesSubcategorias[0].cloneNode(true);

        if (categoriaSeleccionada === "") {
            opcionInicial.textContent = "Selecciona una categoría";
            filtroSubcategoria.replaceChildren(opcionInicial);
            filtroSubcategoria.value = "";
            filtroSubcategoria.disabled = true;
            return;
        }

        opcionInicial.textContent = "Todas las subcategorías";
        const opcionesCompatibles = [opcionInicial, ...opcionesSubcategorias
            .filter((opcion) => opcion.value !== "" && opcion.dataset.categoriaId === categoriaSeleccionada)
            .map((opcion) => opcion.cloneNode(true))];

        filtroSubcategoria.replaceChildren(...opcionesCompatibles);
        filtroSubcategoria.disabled = false;

        if (opcionesCompatibles.some((opcion) => opcion.value === subcategoriaSeleccionada)) {
            filtroSubcategoria.value = subcategoriaSeleccionada;
        } else {
            filtroSubcategoria.value = "";
        }
    }

    function crearUrlFiltros() {
        const url = new URL(formulario.action, window.location.href);
        const termino = entradaBusqueda.value.trim();

        if (termino !== "") {
            url.searchParams.set("buscar", termino);
        }

        if (filtroClasificacion.value !== "todas") {
            url.searchParams.set("clasificacion", filtroClasificacion.value);
        }

        if (filtroCategoria.value !== "") {
            url.searchParams.set("categoria_id", filtroCategoria.value);
        }

        if (filtroSubcategoria.value !== "") {
            url.searchParams.set("subcategoria_id", filtroSubcategoria.value);
        }

        return url;
    }

    function mostrarEsperaLimite(segundos, mensaje) {
        if (temporizadorLimite) {
            clearInterval(temporizadorLimite);
            temporizadorLimite = null;
        }

        bloqueadoHasta = Date.now() + segundos * 1000;

        const actualizarCuentaRegresiva = () => {
            const restante = Math.max(0, Math.ceil((bloqueadoHasta - Date.now()) / 1000));
            if (restante > 0) {
                mostrarAviso(`${mensaje} Podrás reintentar en ${restante} segundos.`, { permitirReintento: true });
                botonReintentar.disabled = true;
                botonReintentar.textContent = `Reintentar (${restante})`;
                return;
            }

            bloqueadoHasta = 0;
            clearInterval(temporizadorLimite);
            temporizadorLimite = null;
            mostrarAviso(`${mensaje} Ya puedes reintentar la consulta.`, { permitirReintento: true });
            botonReintentar.disabled = false;
            botonReintentar.textContent = "Reintentar";
        };

        actualizarCuentaRegresiva();
        temporizadorLimite = setInterval(actualizarCuentaRegresiva, 1000);
    }

    function segundosParaReintento(respuesta) {
        const valor = respuesta.headers.get("Retry-After");
        const segundos = Number.parseInt(valor ?? "", 10);

        if (Number.isFinite(segundos) && segundos > 0) {
            return segundos;
        }

        const fecha = Date.parse(valor ?? "");
        return Number.isFinite(fecha) ? Math.max(1, Math.ceil((fecha - Date.now()) / 1000)) : 60;
    }

    async function consultar(url, { historial = "replace" } = {}) {
        const urlConsulta = new URL(url, window.location.href);
        const termino = (urlConsulta.searchParams.get("buscar") ?? "").trim();

        if (Array.from(termino).length === 1) {
            mostrarAviso("Escribe al menos 2 caracteres para buscar. Se conserva el resultado anterior.");
            return;
        }

        if (bloqueadoHasta > Date.now()) {
            urlReintento = urlConsulta.href;
            return;
        }

        detenerSolicitudActiva();
        const solicitudActual = numeroSolicitud;
        const controlador = new AbortController();
        controladorSolicitud = controlador;
        solicitudExpirada = false;
        urlReintento = urlConsulta.href;
        listado.setAttribute("aria-busy", "true");
        listado.classList.add("pointer-events-none", "opacity-50");
        mostrarAviso("Actualizando recetas…");

        temporizadorSolicitud = setTimeout(() => {
            solicitudExpirada = true;
            controlador.abort();
        }, TIEMPO_LIMITE_MS);

        try {
            const respuesta = await fetch(urlConsulta, {
                method: "GET",
                headers: {
                    Accept: "application/json",
                    "X-Requested-With": "XMLHttpRequest",
                },
                signal: controlador.signal,
                cache: "no-store",
            });

            if (solicitudActual !== numeroSolicitud) {
                return;
            }

            if (respuesta.status === 401 || respuesta.status === 403) {
                window.location.assign(formulario.dataset.loginUrl || "/login");
                return;
            }

            if (respuesta.status === 429) {
                let detalle = {};
                try {
                    detalle = await respuesta.json();
                } catch {
                    detalle = {};
                }
                if (solicitudActual !== numeroSolicitud) {
                    return;
                }
                mostrarEsperaLimite(
                    segundosParaReintento(respuesta),
                    detalle.message || "Se alcanzó el límite de consultas.",
                );
                return;
            }

            if (respuesta.status === 422) {
                const detalle = await respuesta.json();
                if (solicitudActual !== numeroSolicitud) {
                    return;
                }
                const primerError = Object.values(detalle.errors ?? {}).flat()[0];
                mostrarAviso(primerError || detalle.message || "Revisa los filtros seleccionados.", { permitirReintento: true });
                return;
            }

            if (!respuesta.ok) {
                throw new Error(`HTTP ${respuesta.status}`);
            }

            const html = await respuesta.text();
            if (solicitudActual !== numeroSolicitud) {
                return;
            }

            listado.innerHTML = html;
            totalRecetas.textContent = respuesta.headers.get("X-Total-Count") ?? totalRecetas.textContent;

            if (historial === "push") {
                window.history.pushState({}, "", urlConsulta);
            } else if (historial === "replace") {
                window.history.replaceState({}, "", urlConsulta);
            }

            ocultarAviso();
        } catch (error) {
            if (solicitudActual !== numeroSolicitud) {
                return;
            }

            if (controlador.signal.aborted && !solicitudExpirada) {
                return;
            }

            mostrarAviso(
                solicitudExpirada
                    ? "La consulta tardó demasiado. Comprueba tu conexión y vuelve a intentarlo."
                    : "No se pudieron actualizar las recetas. Comprueba tu conexión y vuelve a intentarlo.",
                { permitirReintento: true },
            );
        } finally {
            if (solicitudActual === numeroSolicitud) {
                listado.setAttribute("aria-busy", "false");
                listado.classList.remove("pointer-events-none", "opacity-50");
                controladorSolicitud = null;

                if (temporizadorSolicitud) {
                    clearTimeout(temporizadorSolicitud);
                    temporizadorSolicitud = null;
                }
            }
        }
    }

    function programarConsulta(espera = ESPERA_BUSQUEDA_MS, historial = "replace") {
        clearTimeout(temporizadorBusqueda);
        detenerSolicitudActiva();

        const termino = entradaBusqueda.value.trim();
        if (Array.from(termino).length === 1) {
            mostrarAviso("Escribe al menos 2 caracteres para buscar. Se conserva el resultado anterior.");
            return;
        }

        const url = crearUrlFiltros();
        urlReintento = url.href;

        if (bloqueadoHasta > Date.now()) {
            return;
        }

        temporizadorBusqueda = setTimeout(() => {
            consultar(url, { historial });
        }, espera);
    }

    function ajustarCategoriaDesdeSubcategoria() {
        const opcion = filtroSubcategoria.selectedOptions[0];
        const categoriaId = opcion?.dataset.categoriaId;

        if (categoriaId) {
            filtroCategoria.value = categoriaId;
        }
    }

    function limpiarFiltros() {
        clearTimeout(temporizadorBusqueda);
        entradaBusqueda.value = "";
        filtroClasificacion.value = "todas";
        filtroCategoria.value = "";
        filtroSubcategoria.value = "";

        if (bloqueadoHasta > Date.now()) {
            programarConsulta(0);
            return;
        }

        ocultarAviso();
        programarConsulta(0);
    }

    formulario.addEventListener("submit", (evento) => {
        evento.preventDefault();
        programarConsulta(0);
    });

    entradaBusqueda.addEventListener("input", () => programarConsulta());
    filtroClasificacion.addEventListener("change", () => programarConsulta(0));
    filtroCategoria.addEventListener("change", () => {
        actualizarOpcionesSubcategoria();
        programarConsulta(0);
    });
    filtroSubcategoria.addEventListener("change", () => {
        ajustarCategoriaDesdeSubcategoria();
        actualizarOpcionesSubcategoria();
        programarConsulta(0);
    });
    botonLimpiar.addEventListener("click", limpiarFiltros);
    botonReintentar.addEventListener("click", () => {
        if (bloqueadoHasta > Date.now() || !urlReintento) {
            return;
        }

        consultar(urlReintento);
    });

    listado.addEventListener("click", (evento) => {
        const enlace = evento.target.closest("a[href]");
        if (!enlace || !enlace.href || !new URL(enlace.href).searchParams.has("page")) {
            return;
        }

        const url = new URL(enlace.href);
        if (url.pathname !== new URL(formulario.action, window.location.href).pathname) {
            return;
        }

        evento.preventDefault();
        sincronizarUrlEnFormulario(url);
        consultar(url, { historial: "push" });
    });

    window.addEventListener("popstate", () => {
        sincronizarUrlEnFormulario(window.location.href);
        programarConsulta(0, "none");
    });

    actualizarOpcionesSubcategoria();
    window.addEventListener("pagehide", detenerSolicitudActiva, { once: true });
}
