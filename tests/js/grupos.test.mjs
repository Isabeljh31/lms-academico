import test from 'node:test';
import assert from 'node:assert/strict';
import { tableroGrupos } from '../../resources/js/grupos.js';

test('selecciona los 40 resultados sin duplicar los seleccionados y permite limpiarlos', () => {
    const tablero = tableroGrupos();
    tablero.alumnosSinGrupo = Array.from({ length: 40 }, (_, i) => ({ id: String(i + 1), nombre: `Alumno ${i + 1}` }));
    tablero.seleccionados = ['1'];
    tablero.seleccionarVisibles();
    assert.equal(tablero.seleccionados.length, 40);
    tablero.abrirSeleccion();
    assert.equal(tablero.menuPara, 'seleccion');
    tablero.limpiarSeleccion();
    assert.deepEqual(tablero.seleccionados, []);
});

test('la busqueda ignora tildes y selecciona solo los resultados coincidentes', () => {
    const tablero = tableroGrupos();
    tablero.alumnosSinGrupo = [{ id: '11', nombre: 'José Pérez' }, { id: '12', nombre: 'Ana Rojas' }, { id: '13', nombre: 'Jose Ruiz' }];
    tablero.busqueda = ' JOSE ';
    tablero.seleccionarVisibles();
    assert.deepEqual(tablero.seleccionados, ['11', '13']);
    tablero.busqueda = 'inexistente';
    assert.deepEqual(tablero.sinGrupoVisibles(), []);
    tablero.seleccionarVisibles();
    assert.deepEqual(tablero.seleccionados, ['11', '13']);
});

test('clics sucesivos mantienen seleccion multiple sin requerir Ctrl', () => {
    const tablero = tableroGrupos();
    tablero.elegir({}, '11');
    tablero.elegir({}, '12');
    assert.deepEqual(tablero.seleccionados, ['11', '12']);
    tablero.elegir({}, '11');
    assert.deepEqual(tablero.seleccionados, ['12']);
});

test('mover seleccionados envia todos los ids al grupo elegido en una sola llamada', () => {
    const tablero = tableroGrupos();
    const llamadas = [];
    tablero.$wire = { mover: (...args) => llamadas.push(args) };
    tablero.seleccionados = ['11', '12', '13', '14'];
    tablero.abrirSeleccion();
    tablero.moverA(7);
    assert.deepEqual(llamadas, [[[11, 12, 13, 14], 7]]);
    assert.deepEqual(tablero.seleccionados, []);
    assert.equal(tablero.menuPara, null);
});

test('la capacidad cuenta solo a los seleccionados que aun no estan en el destino', () => {
    const tablero = tableroGrupos();
    const zona = { dataset: { maximo: '4', cuenta: '3' }, querySelector: (selector) => selector === '[data-estudiante="11"]' ? {} : null };
    tablero.seleccionados = ['11', '12'];
    assert.equal(tablero.cabe(zona), true);
    tablero.seleccionados.push('13');
    assert.equal(tablero.cabe(zona), false);
});

test('el filtro se actualiza despues de mover o deshacer usando la lista actual del servidor', () => {
    const tablero = tableroGrupos();
    let chips = [{ dataset: { estudiante: '11', nombre: 'José Pérez' } }];
    tablero.$root = { querySelectorAll: () => [{ dataset: { zona: 'sin-grupo' }, querySelectorAll: () => chips }] };
    tablero.busqueda = 'jose';
    tablero.sincronizarSinGrupo();
    assert.deepEqual(tablero.sinGrupoVisibles(), ['11']);
    chips = [];
    tablero.sincronizarSinGrupo();
    assert.deepEqual(tablero.sinGrupoVisibles(), []);
});

test('crear el primer grupo y eliminar el ultimo actualiza los destinos disponibles', () => {
    const tablero = tableroGrupos();
    const sinGrupo = { dataset: { zona: 'sin-grupo', grupo: '' }, querySelectorAll: () => [] };
    let zonas = [sinGrupo];
    tablero.$root = { querySelectorAll: () => zonas };
    tablero.sincronizarSinGrupo();
    assert.equal(tablero.cantidadGrupos, 0);
    zonas.push({ dataset: { zona: 'grupo-7', grupo: '7' } });
    tablero.sincronizarSinGrupo();
    assert.equal(tablero.cantidadGrupos, 1);
    zonas = [sinGrupo];
    tablero.sincronizarSinGrupo();
    assert.equal(tablero.cantidadGrupos, 0);
    assert.equal(tablero.cabe(undefined), false);
});
