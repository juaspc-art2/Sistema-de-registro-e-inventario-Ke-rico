// Genera docs/diagrama-er.svg a partir de database/kerico.sql.
// Las tablas, columnas, llaves y relaciones se leen del SQL; aquí solo se decide dónde va cada tabla
// y por qué carril viaja cada línea. Si el esquema gana una tabla o una llave foránea que este archivo
// no ubica, el script falla en vez de entregar un diagrama incompleto.
//   node herramientas/diagrama-er.mjs
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const raiz = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const sql = fs.readFileSync(path.join(raiz, 'database', 'kerico.sql'), 'utf8');

// ---------- 1. Esquema ----------
const tablas = {};
const foraneas = [];
for (const [, nombre, cuerpo] of sql.matchAll(/CREATE TABLE (\w+) \(([\s\S]*?)\n\) ENGINE/g)) {
  const columnas = [];
  const marcar = (lista, marca) => lista.split(',').forEach((c) => columnas.find((x) => x.nombre === c.trim()).marcas.add(marca));
  for (const linea of cuerpo.split('\n').map((l) => l.trim().replace(/,$/, '')).filter(Boolean)) {
    let m;
    if ((m = linea.match(/^PRIMARY KEY \((.+)\)/))) marcar(m[1], 'PK');
    else if ((m = linea.match(/^UNIQUE KEY \w+ \((.+)\)/))) marcar(m[1], 'UQ');
    else if ((m = linea.match(/^CONSTRAINT \w+\s+FOREIGN KEY \((\w+)\)\s+REFERENCES (\w+)\((\w+)\)/))) {
      marcar(m[1], 'FK');
      foraneas.push({ hijo: nombre, columna: m[1], padre: m[2] });
    } else if (!/^(KEY|CONSTRAINT)\b/.test(linea)) {
      const [col, ...resto] = linea.split(/\s+/);
      const tipo = resto.join(' ').replace(/\s+(NOT NULL|NULL|DEFAULT|AUTO_INCREMENT|ON UPDATE)\b.*$/, '').replace(/^ENUM\(.*\)$/, 'ENUM');
      columnas.push({ nombre: col, tipo, marcas: new Set() });
    }
  }
  tablas[nombre] = { nombre, columnas };
}

// ---------- 2. Disposición ----------
const ANCHO = 250, CABEZA = 30, FILA = 20, SEPARACION = 50, ARRIBA = 96;
const GRUPOS = {
  sistema: { titulo: 'Sistema', color: '#8d7166' },
  seguridad: { titulo: 'Seguridad y acceso', color: '#594138' },
  ventas: { titulo: 'Ventas', color: '#eb5e15' },
  inventario: { titulo: 'Catálogo e inventario', color: '#2e7d32' },
  compras: { titulo: 'Compras', color: '#00629b' },
};
// Cada columna de la página: grupo, espacio libre a su derecha (para carriles) y tablas de arriba abajo
const COLUMNAS = [
  { grupo: 'sistema', hueco: 70, tablas: ['bitacora', 'respaldos', 'configuracion'] },
  { grupo: 'seguridad', hueco: 70, tablas: ['permisos', 'rol_permisos', 'roles', 'usuarios', 'sesiones'] },
  { grupo: 'ventas', hueco: 90, tablas: ['clientes', 'ventas', 'comprobantes'] },
  { grupo: 'ventas', hueco: 100, tablas: ['venta_detalle', 'descuentos_aplicados', 'promociones'] },
  { grupo: 'inventario', hueco: 100, tablas: ['categorias', 'productos', 'movimientos_inventario', 'alertas_stock'] },
  { grupo: 'compras', hueco: 60, tablas: ['compra_detalle', 'compras', 'proveedores'] },
];

let x = 40;
let fondo = 0;
for (const [i, columna] of COLUMNAS.entries()) {
  columna.x = x;
  let y = ARRIBA;
  for (const [j, nombre] of columna.tablas.entries()) {
    const t = tablas[nombre];
    if (!t) throw new Error('La disposición nombra una tabla que no existe en el SQL: ' + nombre);
    Object.assign(t, { x, y, alto: CABEZA + t.columnas.length * FILA, columna: i, orden: j, grupo: columna.grupo });
    y += t.alto + SEPARACION;
  }
  fondo = Math.max(fondo, y - SEPARACION);
  x += ANCHO + columna.hueco;
}
const anchoTotal = x - COLUMNAS.at(-1).hueco + 60 + 40;
const sinUbicar = Object.keys(tablas).filter((n) => tablas[n].x === undefined);
if (sinUbicar.length) throw new Error('Tablas del SQL sin lugar en el diagrama: ' + sinUbicar.join(', '));

const BUS = fondo + 44;
const altoTotal = BUS + 44;
// x de un carril dentro del hueco que sigue a la columna i (fracción 0..1 del hueco)
const carril = (i, fraccion) => COLUMNAS[i].x + ANCHO + COLUMNAS[i].hueco * fraccion;

// Líneas que comparten un carril vertical. Cada punto es 'tabla.columna:lado'.
// usuarios: true marca las relaciones "quién lo hizo", que bajan al bus inferior para no cruzar el diagrama.
const CARRILES = [
  { x: carril(0, 0.5), usuarios: true, puntos: ['usuarios.id:izq', 'bitacora.usuario_id:der'] },
  { x: carril(1, 0.5), usuarios: true, bus: true, puntos: ['usuarios.id:der', 'ventas.usuario_id:izq', 'ventas.anulada_por:izq'] },
  { x: carril(2, 0.35), puntos: ['ventas.id:der', 'venta_detalle.venta_id:izq', 'descuentos_aplicados.venta_id:izq'] },
  { x: carril(2, 0.68), usuarios: true, bus: true, puntos: ['descuentos_aplicados.autorizado_por:izq', 'promociones.creado_por:izq'] },
  { x: carril(3, 0.34), puntos: ['categorias.id:izq', 'promociones.categoria_id:der'] },
  { x: carril(3, 0.66), puntos: ['productos.id:izq', 'venta_detalle.producto_id:der', 'promociones.producto_id:der', 'alertas_stock.producto_id:izq'] },
  { x: carril(4, 0.3), puntos: ['productos.id:der', 'compra_detalle.producto_id:izq'] },
  { x: carril(4, 0.3), puntos: ['proveedores.id:izq', 'productos.proveedor_id:der'] },
  { x: carril(4, 0.66), usuarios: true, bus: true, puntos: ['movimientos_inventario.usuario_id:der', 'alertas_stock.vista_por:der'] },
  { x: carril(5, 0.6), usuarios: true, bus: true, puntos: ['compras.usuario_id:der'] },
];

// ---------- 3. Comprobación: cada llave foránea del SQL queda dibujada una vez ----------
const apiladas = (f) => tablas[f.hijo].columna === tablas[f.padre].columna && Math.abs(tablas[f.hijo].orden - tablas[f.padre].orden) === 1;
const enCarril = new Set(CARRILES.flatMap((c) => c.puntos.map((p) => p.split(':')[0])).filter((p) => !p.endsWith('.id')));
for (const f of foraneas) {
  const clave = f.hijo + '.' + f.columna;
  if (apiladas(f) === enCarril.has(clave)) {
    throw new Error('La relación ' + clave + ' -> ' + f.padre + (apiladas(f) ? ' está dibujada dos veces' : ' no tiene carril en el diagrama'));
  }
}
for (const clave of enCarril) {
  if (!foraneas.some((f) => f.hijo + '.' + f.columna === clave)) throw new Error('El carril de ' + clave + ' no corresponde a ninguna llave foránea');
}

// ---------- 4. Dibujo ----------
const LINEA = '#594138', LINEA_USUARIOS = '#7e57c2';
const esc = (t) => String(t).replace(/&/g, '&amp;').replace(/</g, '&lt;');
const svg = [];
const trazo = (d, color, extra = '') => svg.push(`<path d="${d}" fill="none" stroke="${color}" stroke-width="1.4" ${extra}/>`);

// Extremo "muchos" (pata de gallo) o "uno" (barra) sobre el borde de una tabla.
// dx, dy: hacia dónde se aleja la línea de la tabla.
function extremo(px, py, dx, dy, muchos, color) {
  if (muchos) {
    const [bx, by] = [px + dx * 11, py + dy * 11];
    trazo(`M${bx} ${by}L${px + dy * 5} ${py + dx * 5}M${bx} ${by}L${px - dy * 5} ${py - dx * 5}`, color);
  } else {
    const [bx, by] = [px + dx * 7, py + dy * 7];
    trazo(`M${bx + dy * 5} ${by + dx * 5}L${bx - dy * 5} ${by - dx * 5}`, color);
  }
}

// Marcos de grupo
for (const [clave, grupo] of Object.entries(GRUPOS)) {
  const cols = COLUMNAS.filter((c) => c.grupo === clave);
  const x0 = cols[0].x - 14, x1 = cols.at(-1).x + ANCHO + 14;
  const y1 = Math.max(...cols.flatMap((c) => c.tablas.map((n) => tablas[n].y + tablas[n].alto))) + 14;
  svg.push(`<rect x="${x0}" y="${ARRIBA - 34}" width="${x1 - x0}" height="${y1 - ARRIBA + 34}" rx="12" fill="${grupo.color}" fill-opacity="0.06" stroke="${grupo.color}" stroke-opacity="0.35" stroke-dasharray="4 4"/>`);
  svg.push(`<text x="${x0 + 12}" y="${ARRIBA - 14}" font-size="13" font-weight="700" fill="${grupo.color}" letter-spacing="0.6">${esc(grupo.titulo.toUpperCase())}</text>`);
}

// Bus inferior de las relaciones con usuarios
const conBus = CARRILES.filter((c) => c.bus).map((c) => c.x);
trazo(`M${Math.min(...conBus)} ${BUS}H${Math.max(...conBus)}`, LINEA_USUARIOS);

// Carriles
for (const c of CARRILES) {
  const color = c.usuarios ? LINEA_USUARIOS : LINEA;
  const puntos = c.puntos.map((p) => {
    const [, tabla, columna, lado] = p.match(/^(\w+)\.(\w+):(izq|der)$/);
    const t = tablas[tabla];
    const fila = t.columnas.findIndex((k) => k.nombre === columna);
    if (fila < 0) throw new Error('No existe la columna ' + tabla + '.' + columna);
    return { px: lado === 'izq' ? t.x : t.x + ANCHO, py: t.y + CABEZA + fila * FILA + FILA / 2, dx: lado === 'izq' ? -1 : 1, muchos: columna !== 'id' };
  });
  const ys = puntos.map((p) => p.py).concat(c.bus ? [BUS] : []);
  trazo(`M${c.x} ${Math.min(...ys)}V${Math.max(...ys)}`, color);
  for (const p of puntos) {
    trazo(`M${p.px} ${p.py}H${c.x}`, color);
    extremo(p.px, p.py, p.dx, 0, p.muchos, color);
    // El punto distingue una unión de un simple cruce de líneas
    if (p.py !== Math.min(...ys) && p.py !== Math.max(...ys)) svg.push(`<circle cx="${c.x}" cy="${p.py}" r="2.6" fill="${color}"/>`);
  }
  if (c.bus && c.x !== Math.min(...conBus) && c.x !== Math.max(...conBus)) svg.push(`<circle cx="${c.x}" cy="${BUS}" r="2.6" fill="${color}"/>`);
}

// Relaciones entre tablas apiladas: un conector vertical corto
for (const f of foraneas.filter(apiladas)) {
  const hijo = tablas[f.hijo], padre = tablas[f.padre];
  const hijoArriba = hijo.orden < padre.orden;
  const cx = hijo.x + ANCHO / 2;
  const yHijo = hijoArriba ? hijo.y + hijo.alto : hijo.y;
  const yPadre = hijoArriba ? padre.y : padre.y + padre.alto;
  trazo(`M${cx} ${yHijo}V${yPadre}`, LINEA);
  extremo(cx, yHijo, 0, hijoArriba ? 1 : -1, true, LINEA);
  extremo(cx, yPadre, 0, hijoArriba ? -1 : 1, false, LINEA);
}

// Tablas
const COLOR_MARCA = { PK: '#c9a904', FK: '#00629b', UQ: '#8d7166' };
for (const t of Object.values(tablas)) {
  const color = GRUPOS[t.grupo].color;
  svg.push(`<g>`);
  svg.push(`<rect x="${t.x}" y="${t.y}" width="${ANCHO}" height="${t.alto}" rx="7" fill="#ffffff" stroke="${color}" stroke-width="1.3"/>`);
  svg.push(`<path d="M${t.x} ${t.y + CABEZA}V${t.y + 7}a7 7 0 0 1 7 -7H${t.x + ANCHO - 7}a7 7 0 0 1 7 7V${t.y + CABEZA}Z" fill="${color}"/>`);
  svg.push(`<text x="${t.x + 12}" y="${t.y + 20}" font-size="13.5" font-weight="700" fill="#ffffff">${esc(t.nombre)}</text>`);
  svg.push(`<text x="${t.x + ANCHO - 10}" y="${t.y + 20}" font-size="10" fill="#ffffff" fill-opacity="0.8" text-anchor="end">${t.columnas.length} columnas</text>`);
  // Los nombres se alinean dejando sitio para la fila con más marcas (rol_permisos: PK y FK a la vez)
  const sangria = 10 + 26 * Math.max(...t.columnas.map((c) => c.marcas.size), 1);
  t.columnas.forEach((c, i) => {
    const y = t.y + CABEZA + i * FILA;
    if (i > 0) svg.push(`<path d="M${t.x + 1} ${y}H${t.x + ANCHO - 1}" stroke="#efe6e2" stroke-width="1"/>`);
    ['PK', 'FK', 'UQ'].filter((m) => c.marcas.has(m)).forEach((marca, k) => {
      svg.push(`<rect x="${t.x + 7 + k * 26}" y="${y + 4}" width="22" height="12" rx="3" fill="${COLOR_MARCA[marca]}"/>`);
      svg.push(`<text x="${t.x + 18 + k * 26}" y="${y + 13.2}" font-size="8.5" font-weight="700" fill="#ffffff" text-anchor="middle">${marca}</text>`);
    });
    svg.push(`<text x="${t.x + sangria}" y="${y + 14}" font-size="11.5" font-weight="${c.marcas.has('PK') ? 700 : 400}" fill="#1e1b18">${esc(c.nombre)}</text>`);
    svg.push(`<text x="${t.x + ANCHO - 9}" y="${y + 14}" font-size="9.5" fill="#8d7166" text-anchor="end">${esc(c.tipo)}</text>`);
  });
  svg.push(`</g>`);
}

// Leyenda, en el espacio libre bajo la columna de Sistema
const lx = COLUMNAS[0].x, ly = Math.max(...COLUMNAS[0].tablas.map((n) => tablas[n].y + tablas[n].alto)) + 70;
const renglones = [
  ['PK', 'Llave primaria'], ['FK', 'Llave foránea'], ['UQ', 'Valor único'],
];
svg.push(`<rect x="${lx - 14}" y="${ly - 30}" width="${ANCHO + 28}" height="250" rx="12" fill="#ffffff" stroke="#e1bfb3"/>`);
svg.push(`<text x="${lx}" y="${ly - 8}" font-size="13" font-weight="700" fill="#594138" letter-spacing="0.6">CÓMO LEER EL DIAGRAMA</text>`);
renglones.forEach(([marca, texto], i) => {
  const y = ly + 12 + i * 22;
  svg.push(`<rect x="${lx}" y="${y}" width="22" height="12" rx="3" fill="${COLOR_MARCA[marca]}"/>`);
  svg.push(`<text x="${lx + 11}" y="${y + 9.2}" font-size="8.5" font-weight="700" fill="#ffffff" text-anchor="middle">${marca}</text>`);
  svg.push(`<text x="${lx + 34}" y="${y + 10}" font-size="11.5" fill="#1e1b18">${texto}</text>`);
});
let y = ly + 92;
trazo(`M${lx} ${y}H${lx + 60}`, LINEA);
extremo(lx, y, 1, 0, false, LINEA);
extremo(lx + 60, y, -1, 0, true, LINEA);
svg.push(`<text x="${lx + 72}" y="${y + 4}" font-size="11.5" fill="#1e1b18">Uno (barra) a muchos (pata)</text>`);
y += 26;
trazo(`M${lx} ${y}H${lx + 60}`, LINEA);
svg.push(`<circle cx="${lx + 30}" cy="${y}" r="2.6" fill="${LINEA}"/>`);
svg.push(`<text x="${lx + 72}" y="${y + 4}" font-size="11.5" fill="#1e1b18">Con punto se unen; sin punto se cruzan</text>`);
y += 26;
trazo(`M${lx} ${y}H${lx + 60}`, LINEA_USUARIOS);
svg.push(`<text x="${lx + 72}" y="${y + 4}" font-size="11.5" fill="#1e1b18">Quién lo hizo: llave hacia usuarios</text>`);
y += 30;
svg.push(`<text x="${lx}" y="${y}" font-size="10.5" fill="#8d7166">Cada línea sale de la columna FK y llega a la</text>`);
svg.push(`<text x="${lx}" y="${y + 15}" font-size="10.5" fill="#8d7166">columna id de la tabla a la que apunta.</text>`);

const totalColumnas = Object.values(tablas).reduce((a, t) => a + t.columnas.length, 0);
const salida = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${anchoTotal} ${altoTotal}" width="${anchoTotal}" height="${altoTotal}" font-family="'Segoe UI', Arial, Helvetica, sans-serif">
<title>Diagrama entidad-relación del Sistema de Registro e Inventario Ke-Rico!</title>
<rect width="100%" height="100%" fill="#fff8f5"/>
<text x="40" y="34" font-size="22" font-weight="800" fill="#1e1b18">Diagrama entidad–relación · Sistema de Registro e Inventario Ke-Rico!</text>
<text x="40" y="54" font-size="12.5" fill="#8d7166">${Object.keys(tablas).length} tablas · ${totalColumnas} columnas · ${foraneas.length} relaciones · generado desde database/kerico.sql</text>
${svg.join('\n')}
</svg>
`;
fs.writeFileSync(path.join(raiz, 'docs', 'diagrama-er.svg'), salida);
console.log(`docs/diagrama-er.svg: ${Object.keys(tablas).length} tablas, ${totalColumnas} columnas, ${foraneas.length} relaciones (${anchoTotal} x ${altoTotal}).`);
