import { inicializarTablaUsuarios } from "./usuarios-datatable.js";
import { inicializarRevision } from "./revision-recetas.js";

document.addEventListener("DOMContentLoaded", () => {
    inicializarRevision();
    // Inicializar módulo de tabla de usuarios si se encuentra en la vista actual
    const tablaUsuarios = document.getElementById("tabla-usuarios");
    if (tablaUsuarios) {
        inicializarTablaUsuarios();
    }
});
