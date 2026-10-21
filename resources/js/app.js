import { inicializarTablaUsuarios } from "./usuarios-datatable.js";
import { inicializarRevision } from "./revision-recetas.js";
import { inicializarFiltrosRecetas } from "./recetas-filtros.js";

document.addEventListener("DOMContentLoaded", () => {
    inicializarRevision();
    inicializarFiltrosRecetas();
    // Inicializar módulo de tabla de usuarios si se encuentra en la vista actual
    const tablaUsuarios = document.getElementById("tabla-usuarios");
    if (tablaUsuarios) {
        inicializarTablaUsuarios();
    }
});
