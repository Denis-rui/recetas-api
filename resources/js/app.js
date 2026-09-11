import { inicializarTablaUsuarios } from "./usuarios-datatable.js";

document.addEventListener("DOMContentLoaded", () => {
    // Inicializar módulo de tabla de usuarios si se encuentra en la vista actual
    const tablaUsuarios = document.getElementById("tabla-usuarios");
    if (tablaUsuarios) {
        inicializarTablaUsuarios();
    }
});
