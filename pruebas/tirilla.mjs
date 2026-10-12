// Comprueba que el comprobante en PDF sigue coincidiendo con la tirilla de la página.
// No necesita Apache, MySQL ni navegador: genera el PDF de un comprobante fijo y compara el ancho y la
// posición de sus textos con los que midió Chrome en la página (comprobante KR-00000023, pantalla de
// doble densidad). Si falla después de tocar TirillaPdf.php, FuentePdf.php, el componente Tirilla o las
// fuentes, las dos tirillas dejaron de ser iguales.
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';

const PHP = process.env.KERICO_PHP || 'C:/xampp/php/php.exe';
const RAIZ = path.resolve(import.meta.dirname, '..');

let pasadas = 0, fallidas = 0;
function ok(nombre, cond, extra = '') {
  if (cond) { pasadas++; console.log('  PASS  ' + nombre); }
  else { fallidas++; console.log('  FALL  ' + nombre + (extra ? ' :: ' + extra : '')); }
}
const cerca = (a, b, margen = 0.05) => Math.abs(a - b) <= margen;

const comprobante = {
  tipo: 'Factura de venta', numero: 'KR-00000023', folio: 'V-000023', cajero: 'Andrés Rodríguez',
  fecha_emision: '2026-09-24 14:20:42', estado: 'Emitido',
  resolucion_dian: 'Resolución DIAN 18764003456789 del 2026-01-15, prefijo KR, rango 1 al 50000',
  emisor: {
    nombre: 'Ke-Rico!', razon_social: 'Ke-Rico! S.A.S.', nit: '901.456.789-3',
    direccion: 'Cra. 59 # 132A - 7, Suba, Bogotá D.C.', telefono: '(+57) 317 796 5569',
  },
  cliente: { tipo_documento: 'CC', documento: '80114522', nombre: 'Andrés Rojas' },
  detalle: [
    { nombre: 'Arepa rellena', cantidad: 1, precio_unitario: 3900, total: 3900 },
    { nombre: 'Carimañola', cantidad: 1, precio_unitario: 4500, total: 4500 },
    { nombre: 'Empanada de carne', cantidad: 2, precio_unitario: 3900, total: 7800 },
  ],
  subtotal: 16200, descuento: 0, base_gravable: 15000, impuesto: 1200, total: 16200,
};

const guion = path.join(os.tmpdir(), 'kerico-tirilla-' + process.pid + '.php');
fs.writeFileSync(guion, `<?php
date_default_timezone_set('America/Bogota');
foreach (['FuentePdf', 'DocumentoPdf', 'TirillaPdf'] as $clase) {
    require $argv[1] . '/backend/nucleo/' . $clase . '.php';
}
$fuentes = $argv[1] . '/backend/recursos/fuentes/';
$normal = new Kerico\\Nucleo\\FuentePdf($fuentes . 'WorkSans-Regular.ttf');
$marca = new Kerico\\Nucleo\\FuentePdf($fuentes . 'Baloo2-ExtraBold.ttf');
echo json_encode([
    'venta'      => $normal->ancho('Venta: V-000023', 12),
    'cajero'     => $normal->ancho('Cajero: Andrés Rodríguez', 12),
    'uno'        => $normal->ancho('1', 12.5, true),
    'ocho'       => $normal->ancho('8', 12.5, true),
    'unoSuelto'  => $normal->ancho('1', 12.5),
    'ochoSuelto' => $normal->ancho('8', 12.5),
    'marca'      => $marca->ancho('Ke-Rico!', 22, false, -0.264),
    'pdf'        => base64_encode((new Kerico\\Nucleo\\TirillaPdf(json_decode($argv[2], true)))->generar()),
]);
`);

let r;
try {
  r = JSON.parse(execFileSync(PHP, [guion, RAIZ, JSON.stringify(comprobante)], { encoding: 'utf8' }));
} finally {
  fs.unlinkSync(guion);
}

console.log('\n=== Medidas de la letra ===');
ok('el ancho con kerning coincide con el de la página', cerca(r.venta, 97.657, 0.03), r.venta.toFixed(3));
ok('el kerning entre palabras también', cerca(r.cajero, 146.836, 0.03), r.cajero.toFixed(3));
ok('las cifras tabulares miden todas lo mismo', cerca(r.uno, r.ocho, 0.001) && cerca(r.uno, 7.555, 0.01), r.uno + ' / ' + r.ocho);
ok('las cifras normales no', r.unoSuelto < r.ochoSuelto - 1, r.unoSuelto + ' / ' + r.ochoSuelto);
ok('el nombre de la marca mide lo mismo con su espaciado', cerca(r.marca, 80.328, 0.03), r.marca.toFixed(3));

console.log('\n=== Posición de los textos en el PDF ===');
const pdf = Buffer.from(r.pdf, 'base64').toString('latin1');
const [, anchoPt, altoPt] = pdf.match(/\/MediaBox \[0 0 ([\d.]+) ([\d.]+)\]/) || [];
ok('la hoja mide lo mismo que la tirilla de la página', cerca(anchoPt / 0.75, 378, 0.02) && cerca(altoPt / 0.75, 608.648, 0.03),
  (anchoPt / 0.75).toFixed(2) + ' x ' + (altoPt / 0.75).toFixed(2));

// Cada texto del PDF: fuente (F1 normal, F2 seminegrita, F3 marca), tamaño, borde izquierdo y línea base, en píxeles
const tramos = [...pdf.matchAll(/BT \/(F\d) ([\d.]+) Tf [\d. ]+ rg -?[\d.]+ Tc 1 0 0 -1 ([\d.]+) ([\d.]+) Tm /g)]
  .map((m) => ({ fuente: m[1], tam: Number(m[2]), x: Number(m[3]), base: Number(m[4]) }));
const esta = (fuente, tam, x, base) => tramos.some((t) => t.fuente === fuente && t.tam === tam && cerca(t.x, x) && cerca(t.base, base));
const cercanos = (base) => JSON.stringify(tramos.filter((t) => cerca(t.base, base, 3)));

ok('la marca queda centrada donde la pone la página', esta('F3', 22, 148.836, 37), cercanos(37));
ok('el tipo de comprobante va en seminegrita al margen', esta('F2', 12, 21, 141.898), cercanos(141.898));
ok('el número sigue en la misma línea', esta('F1', 12, 120.117, 141.898), cercanos(141.898));
ok('el valor de una línea termina en el borde derecho', esta('F1', 12.5, 315.469, 274.398), cercanos(274.398));
ok('el total va en seminegrita y alineado a la derecha', esta('F2', 15, 298.109, 503.148), cercanos(503.148));
ok('la resolución parte en el mismo punto y centra su segunda línea', esta('F1', 10, 167.758, 559.148), cercanos(559.148));
ok('el agradecimiento queda centrado al final', esta('F1', 11, 128.047, 583.148), cercanos(583.148));
ok('las tres fuentes van incrustadas con su tabla de texto', (pdf.match(/\/FontFile2 /g) || []).length === 3 && (pdf.match(/\/ToUnicode /g) || []).length === 3);

console.log('\n================================');
console.log('PASADAS: ' + pasadas + '   FALLIDAS: ' + fallidas);
console.log('================================');
process.exit(fallidas > 0 ? 1 : 0);
