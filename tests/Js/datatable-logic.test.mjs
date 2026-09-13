import test from 'node:test';
import assert from 'node:assert/strict';
import { inicializarTablaUsuarios } from '../../resources/js/usuarios-datatable.js';

// Adaptador de las dependencias DOM/jQuery/DataTables; los manejadores bajo prueba
// son los que registra el módulo de producción, sin copiar sus reglas.
function pantalla(t) {
    t.mock.timers.enable({ apis: ['setTimeout', 'setInterval'] });
    const elementos = new Map();
    const consultas = [];
    let recargas = 0;
    let opciones;
    const tabla = {
        search(texto) { consultas.push(texto); return this; },
        draw() { return this; },
        on() { return this; },
        ajax: { reload() { recargas++; } },
    };
    function elemento(selector) {
        if (!elementos.has(selector)) {
            const valor = {
                length: 1, value: '', texto: '', propiedades: {}, eventos: {}, clases: new Set(['hidden']),
                dataset: { url: '/usuarios', loginUrl: '/login' },
                off(nombre) { if (nombre) delete this.eventos[nombre]; else this.eventos = {}; return this; },
                on(nombre, callback) { this.eventos[nombre] = callback; return this; },
                text(texto) { if (texto === undefined) return this.texto; this.texto = texto; return this; },
                val(valor) { if (valor === undefined) return this.value; this.value = valor; return this; },
                prop(nombre, valor) { this.propiedades[nombre] = valor; return this; },
                addClass(clases) { clases.split(' ').forEach(c => this.clases.add(c)); return this; },
                removeClass(clases) { clases.split(' ').forEach(c => this.clases.delete(c)); return this; },
                find(nombre) { return elemento(selector + ' ' + nombre); },
                hide() { return this; }, attr() { return 'csrf'; },
                DataTable(config) { opciones = config; return tabla; },
                disparar(nombre, evento = {}) { this.eventos[nombre]?.call(this, evento); },
            };
            elementos.set(selector, valor);
        }
        return elementos.get(selector);
    }
    const $ = selector => typeof selector === 'string' ? elemento(selector) : selector;
    $.fn = { DataTable: { isDataTable: () => false }, dataTable: { ext: {} } };
    const previoDocument = globalThis.document;
    const previoWindow = globalThis.window;
    globalThis.document = { getElementById: () => elemento('tabla') };
    globalThis.window = { $, location: { href: '' } };
    t.after(() => { globalThis.document = previoDocument; globalThis.window = previoWindow; });
    inicializarTablaUsuarios();
    opciones.initComplete();
    const entrada = elemento('div.dataTables_filter input');
    return { elemento, entrada, opciones, consultas, recargas: () => recargas,
        escribir(texto) { entrada.value = texto; entrada.disparar('input'); },
        enter() { entrada.disparar('keydown', { key: 'Enter', preventDefault() {} }); },
    };
}

test('la pantalla cuenta Unicode, bloquea un carácter y conserva el aviso de la búsqueda anterior', t => {
    const ui = pantalla(t);
    ui.escribir('  é  '); t.mock.timers.tick(400);
    assert.deepEqual(ui.consultas, []);
    assert.equal(ui.elemento('#texto-aviso-busqueda').texto, 'Escribe al menos 2 caracteres para buscar');
    ui.escribir('  Ñá  '); t.mock.timers.tick(400);
    assert.deepEqual(ui.consultas, ['Ñá']);
    ui.escribir('C'); t.mock.timers.tick(400);
    assert.deepEqual(ui.consultas, ['Ñá']);
    assert.match(ui.elemento('#texto-aviso-busqueda').texto, /consulta anterior: "Ñá"/);
    ui.escribir('   '); t.mock.timers.tick(400);
    assert.deepEqual(ui.consultas, ['Ñá', '']);
    assert.equal(ui.elemento('#aviso-busqueda').clases.has('hidden'), true);
});

test('la pantalla limita a 100 caracteres sin partir caracteres Unicode', t => {
    const ui = pantalla(t);
    ui.escribir('a'.repeat(120)); t.mock.timers.tick(400);
    assert.equal(ui.consultas[0], 'a'.repeat(100));
    ui.escribir('😀'.repeat(120)); t.mock.timers.tick(400);
    assert.equal(ui.consultas[1], '😀'.repeat(100));
});

test('los manejadores reales agrupan pulsaciones a 400 ms desde la última', t => {
    const ui = pantalla(t);
    ui.escribir('Ca'); t.mock.timers.tick(100);
    ui.escribir('Car'); t.mock.timers.tick(100);
    ui.escribir('Carlos'); t.mock.timers.tick(399);
    assert.deepEqual(ui.consultas, []);
    t.mock.timers.tick(1);
    assert.deepEqual(ui.consultas, ['Carlos']);
});

test('Enter cancela la búsqueda pendiente y no duplica el envío', t => {
    const ui = pantalla(t);
    ui.escribir('Mendoza'); ui.enter();
    assert.deepEqual(ui.consultas, ['Mendoza']);
    t.mock.timers.tick(1000); ui.enter();
    assert.deepEqual(ui.consultas, ['Mendoza']);
});

test('HTTP 429 respeta Retry-After y solo reintenta al pulsar después de la espera', t => {
    const ui = pantalla(t);
    ui.opciones.ajax.error({ status: 429, getResponseHeader: () => '5', responseJSON: { message: 'Límite' } });
    const boton = ui.elemento('#btn-reintentar-datatable');
    assert.equal(boton.propiedades.disabled, true);
    boton.disparar('click');
    assert.equal(ui.recargas(), 0);
    t.mock.timers.tick(4000);
    assert.equal(boton.propiedades.disabled, true);
    t.mock.timers.tick(1000);
    assert.equal(boton.propiedades.disabled, false);
    assert.equal(ui.recargas(), 0);
    boton.disparar('click');
    assert.equal(ui.recargas(), 1);
});

test('limpiar filtros cancela el debounce y envía solo la consulta vacía', t => {
    const ui = pantalla(t);
    ui.escribir('Carlos');
    ui.elemento('#btn-limpiar-filtros').disparar('click');
    t.mock.timers.tick(1000);
    assert.deepEqual(ui.consultas, ['']);
});

test('el módulo cancela solicitudes anteriores y maneja 422 sin redirigir', t => {
    const ui = pantalla(t);
    let canceladas = 0;
    ui.opciones.ajax.beforeSend({ readyState: 1, abort() { canceladas++; } });
    ui.opciones.ajax.beforeSend({ readyState: 1, abort() {} });
    assert.equal(canceladas, 1);
    ui.opciones.ajax.error({ status: 422, responseJSON: { message: 'Consulta inválida' } });
    assert.equal(ui.elemento('#texto-aviso-busqueda').texto, 'Consulta inválida');
    assert.equal(window.location.href, '');
    ui.opciones.ajax.error({ status: 401 });
    assert.equal(window.location.href, '/login');
});
