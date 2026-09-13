import test from "node:test";
import assert from "node:assert/strict";

// Función pura de conteo y umbral idéntica a la implementada en usuarios-datatable.js
function evaluarTextoBusqueda(valorOriginal, ultimaBusquedaEnviada) {
    const valorLimpio = (valorOriginal || "").trim();
    const longitud = Array.from(valorLimpio).length;

    if (longitud === 0) {
        return {
            accion: "limpiar",
            consultaEnviada: "",
            avisoVisible: false,
            avisoTexto: "",
        };
    } else if (longitud === 1) {
        return {
            accion: "bloqueado",
            consultaEnviada: null, // No se consulta al servidor
            avisoVisible: true,
            avisoTexto:
                ultimaBusquedaEnviada !== ""
                    ? `Escribe al menos 2 caracteres para buscar (mostrando resultados de la consulta anterior: "${ultimaBusquedaEnviada}")`
                    : "Escribe al menos 2 caracteres para buscar",
        };
    } else {
        const terminoSanitizado = valorLimpio.slice(0, 100);
        return {
            accion: "buscar",
            consultaEnviada: terminoSanitizado,
            avisoVisible: false,
            avisoTexto: "",
        };
    }
}

// Simulador de debounce
function crearBuscadorConDebounce(delayMs, callback) {
    let timer = null;
    let ultimaBusqueda = "";

    return {
        escribir(texto, esEnter = false) {
            if (timer) {
                clearTimeout(timer);
                timer = null;
            }

            const ejecutar = () => {
                const resultado = evaluarTextoBusqueda(texto, ultimaBusqueda);
                if (resultado.accion === "buscar") {
                    ultimaBusqueda = resultado.consultaEnviada;
                } else if (resultado.accion === "limpiar") {
                    ultimaBusqueda = "";
                }
                callback(resultado);
            };

            if (esEnter) {
                ejecutar();
            } else {
                timer = setTimeout(ejecutar, delayMs);
            }
        },
        cancelar() {
            if (timer) {
                clearTimeout(timer);
                timer = null;
            }
        },
    };
}

test("conteo correcto de caracteres Unicode con acentos, eñes y espacios", () => {
    // 1 carácter con espacios
    const r1 = evaluarTextoBusqueda("  é  ", "");
    assert.equal(r1.accion, "bloqueado");
    assert.equal(r1.consultaEnviada, null);
    assert.equal(r1.avisoTexto, "Escribe al menos 2 caracteres para buscar");

    // 2 caracteres con eñe y acento
    const r2 = evaluarTextoBusqueda("  Ñá  ", "");
    assert.equal(r2.accion, "buscar");
    assert.equal(r2.consultaEnviada, "Ñá");

    // Conservar aviso con referencia a búsqueda previa
    const r3 = evaluarTextoBusqueda("C", "Carlos");
    assert.equal(r3.accion, "bloqueado");
    assert.equal(r3.consultaEnviada, null);
    assert.match(
        r3.avisoTexto,
        /mostrando resultados de la consulta anterior: "Carlos"/,
    );

    // Borrado total limpia búsqueda y oculta aviso
    const r4 = evaluarTextoBusqueda("   ", "Carlos");
    assert.equal(r4.accion, "limpiar");
    assert.equal(r4.consultaEnviada, "");
    assert.equal(r4.avisoVisible, false);
});

test("limitación de longitud máxima a 100 caracteres", () => {
    const textoLargo = "a".repeat(120);
    const res = evaluarTextoBusqueda(textoLargo, "");
    assert.equal(res.accion, "buscar");
    assert.equal(res.consultaEnviada.length, 100);
});

test("debounce real de 400 ms: agrupa pulsaciones rápidas en una única búsqueda", async () => {
    const llamadas = [];
    const buscador = crearBuscadorConDebounce(400, (res) => {
        llamadas.push(res);
    });

    // Escribir 'Ca' en t=0ms, 'Car' en t=100ms, 'Carlos' en t=200ms
    buscador.escribir("Ca");
    await new Promise((resolve) => setTimeout(resolve, 100));
    buscador.escribir("Car");
    await new Promise((resolve) => setTimeout(resolve, 100));
    buscador.escribir("Carlos");

    // En t=250ms aún no debe haberse ejecutado la búsqueda (se reinició el timer)
    await new Promise((resolve) => setTimeout(resolve, 50));
    assert.equal(llamadas.length, 0);

    // En t=650ms (400ms después de 'Carlos'), debe haberse ejecutado exactamente 1 vez
    await new Promise((resolve) => setTimeout(resolve, 400));
    assert.equal(llamadas.length, 1);
    assert.equal(llamadas[0].consultaEnviada, "Carlos");
});

test("pulsar Enter ejecuta inmediatamente la búsqueda sin esperar los 400 ms", () => {
    const llamadas = [];
    const buscador = crearBuscadorConDebounce(400, (res) => {
        llamadas.push(res);
    });

    buscador.escribir("Mendoza", true); // con Enter = true
    assert.equal(llamadas.length, 1);
    assert.equal(llamadas[0].consultaEnviada, "Mendoza");
});

test("control de cuenta regresiva y Retry-After para HTTP 429", () => {
    let segundosRestantes = 5;
    let botonHabilitado = false;
    let textoAviso = "";

    function tick() {
        segundosRestantes--;
        if (segundosRestantes > 0) {
            textoAviso = `Podrás reintentar en ${segundosRestantes} segundos.`;
            botonHabilitado = false;
        } else {
            textoAviso =
                "El tiempo de espera ha concluido. Ya puedes reintentar la consulta.";
            botonHabilitado = true;
        }
    }

    assert.equal(botonHabilitado, false);
    for (let i = 0; i < 4; i++) {
        tick();
        assert.equal(botonHabilitado, false);
    }
    tick(); // quinto segundo
    assert.equal(botonHabilitado, true);
    assert.equal(
        textoAviso,
        "El tiempo de espera ha concluido. Ya puedes reintentar la consulta.",
    );
});
