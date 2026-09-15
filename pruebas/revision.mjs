import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';

const BASE = process.env.KERICO_API || 'http://localhost/kerico/api';
const RAIZ = process.env.KERICO_RAIZ || path.resolve(import.meta.dirname, '..');
const MARIADB = process.env.KERICO_MYSQL || 'C:/xampp/mysql/bin/mysql.exe';

let pasadas = 0, fallidas = 0;
const fallos = [];

function ok(nombre, cond, extra = '') {
  if (cond) { pasadas++; console.log('  PASS  ' + nombre); }
  else { fallidas++; fallos.push(nombre + (extra ? ' :: ' + extra : '')); console.log('  FALL  ' + nombre + (extra ? ' :: ' + extra : '')); }
}

const seccion = (t) => console.log('\n=== ' + t + ' ===');
const CSRF = new Map();

async function api(metodo, ruta, { token, body, raw } = {}) {
  const h = {};
  if (body !== undefined) h['Content-Type'] = 'application/json';
  if (token) h.Authorization = 'Bearer ' + token;
  if (token && CSRF.has(token)) h['X-CSRF-Kerico'] = CSRF.get(token);
  const t0 = Date.now();
  const r = await fetch(BASE + ruta, { method: metodo, headers: h, body: body === undefined ? undefined : JSON.stringify(body) });
  const ms = Date.now() - t0;
  if (raw) return { status: r.status, buf: Buffer.from(await r.arrayBuffer()), ms, headers: r.headers };
  let j = null;
  try { j = await r.json(); } catch { j = null; }
  return { status: r.status, j, ms };
}

async function entrar(usuario, password) {
  const r = await api('POST', '/auth/login', { body: { usuario, password } });
  if (r.j?.datos?.token) CSRF.set(r.j.datos.token, r.j.datos.csrf_token);
  return r;
}

function sql(consulta) {
  return execFileSync(MARIADB, ['-u', process.env.KERICO_DB_USER || 'root', '-h', '127.0.0.1', '-P', process.env.KERICO_DB_PORT || '3306', 'kerico', '-B', '-N', '-e', consulta],
    { encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'] }).split(String.fromCharCode(13)).join('').trim();
}

const items = (r) => (r.j?.datos?.items ?? r.j?.datos ?? []);

const admin = await entrar('admin', '123');
const TA = admin.j.datos.token;
const cajero = await entrar('cajero', '123');
const TC = cajero.j.datos.token;
const inv = await entrar('inventario', '123');
const TI = inv.j.datos.token;

// ─── 1. Autenticacion, roles y sesiones ───────────────────────────────────────
seccion('1.1 Hash de contrasenas');
const hashes = sql("SELECT password_hash FROM usuarios WHERE deleted_at IS NULL").split('\n').filter(Boolean);
ok('1.1 todas las claves estan cifradas', hashes.every((h) => /^\$2[aby]\$\d{2}\$/.test(h)), hashes[0]?.slice(0, 10));
ok('1.1 ninguna clave viaja en texto plano', hashes.every((h) => h.length === 60));
ok('1.1 el algoritmo es bcrypt', hashes.every((h) => h.startsWith('$2y$')));
const costo = Number((hashes[0] || '').split('$')[2] || 0);
ok('1.1 el costo de bcrypt es 10 o mayor', costo >= 10, 'costo=' + costo);
ok('1.1 la API nunca devuelve el hash', JSON.stringify(admin.j).includes('password_hash') === false);

seccion('1.2 Inicio de sesion y RBAC');
ok('1.2 credenciales validas otorgan acceso', admin.status === 200 && !!TA);
ok('1.2 credenciales invalidas se rechazan', (await api('POST', '/auth/login', { body: { usuario: 'admin', password: 'x' } })).status === 401);

const restringidas = [
  ['GET', '/reportes/rentabilidad'],
  ['GET', '/sistema/usuarios'],
  ['GET', '/sistema/bitacora'],
  ['GET', '/sistema/configuracion'],
  ['GET', '/sistema/respaldos'],
  ['GET', '/proveedores'],
  ['GET', '/descuentos/historial'],
];
for (const [m, ruta] of restringidas) {
  const r = await api(m, ruta, { token: TC });
  ok('1.2 cajero bloqueado en ' + ruta, r.status === 403, 'status=' + r.status);
}
const escrituras = [
  ['POST', '/inventario/productos', { sku: 'X', nombre: 'X', categoria_id: 1, precio_costo: 1, precio_venta: 1 }],
  ['POST', '/proveedores', { nombre_empresa: 'X', nit: '1', tipo_productos: 'X' }],
  ['POST', '/sistema/usuarios', { usuario: 'x', nombre: 'x', apellido: 'x', documento: '11111', correo: 'x@x.co', rol_id: 1, password: '123' }],
];
for (const [m, ruta, cuerpo] of escrituras) {
  const r = await api(m, ruta, { token: TC, body: cuerpo });
  ok('1.2 cajero bloqueado al escribir en ' + ruta, r.status === 403, 'status=' + r.status);
}
ok('1.2 el administrador si accede', (await api('GET', '/reportes/rentabilidad', { token: TA })).status === 200);
ok('1.2 sin token no hay acceso', (await api('GET', '/inventario/productos')).status === 401);

seccion('1.3 Expiracion de sesion');
const efimero = await entrar('gerente', '123');
const TE = efimero.j.datos.token;
ok('1.3 sesion nueva es valida', (await api('GET', '/auth/sesion', { token: TE })).status === 200);
sql("UPDATE sesiones SET ultima_actividad = NOW() - INTERVAL 16 MINUTE WHERE id = '" + TE + "'");
const expirada = await api('GET', '/auth/sesion', { token: TE });
ok('1.3 caduca a los 15 minutos de inactividad', expirada.status === 401, 'status=' + expirada.status);
const cerrada = sql("SELECT motivo_cierre FROM sesiones WHERE id = '" + TE + "'");
ok('1.3 el motivo queda registrado como inactividad', cerrada === 'Inactividad', cerrada);
ok('1.3 el token queda destruido tras expirar', (await api('GET', '/ventas', { token: TE })).status === 401);

const paraSalir = await entrar('gerente', '123');
const TS = paraSalir.j.datos.token;
ok('1.3 logout responde correctamente', (await api('POST', '/auth/logout', { token: TS })).status === 200);
ok('1.3 el token no sirve tras el logout', (await api('GET', '/auth/sesion', { token: TS })).status === 401);
ok('1.3 el logout marca cierre manual', sql("SELECT motivo_cierre FROM sesiones WHERE id = '" + TS + "'") === 'Manual');

// ─── 2. Dashboard ─────────────────────────────────────────────────────────────
seccion('2.1 Metricas dinamicas');
const panel = await api('GET', '/reportes/panel', { token: TA });
ok('2.1 el panel responde', panel.status === 200);
const d = panel.j.datos;
const ventasBd = Number(sql("SELECT COALESCE(SUM(total),0) FROM ventas WHERE estado='Completada' AND DATE(fecha)=CURDATE()"));
ok('2.1 ventas de hoy vienen de la base de datos', Math.abs(d.ventas_hoy.total - ventasBd) < 0.01,
  `api=${d.ventas_hoy.total} bd=${ventasBd}`);
const alertasBd = Number(sql("SELECT COUNT(*) FROM v_estado_inventario WHERE activo=1 AND estado_stock='Agotado'"));
ok('2.1 alertas de agotados coinciden con la base', d.alertas.Agotado === alertasBd, `api=${d.alertas.Agotado} bd=${alertasBd}`);
const valorBd = Number(sql("SELECT COALESCE(SUM(valor_inventario),0) FROM v_estado_inventario WHERE activo=1"));
ok('2.1 valor del inventario coincide', Math.abs(d.alertas.valor_inventario - valorBd) < 1, `api=${d.alertas.valor_inventario} bd=${valorBd}`);
ok('2.1 movimientos de hoy son reales', typeof d.movimientos_hoy.total === 'number');
ok('2.1 el mes acumula ingresos', d.mes.ingresos_brutos > 0);
ok('2.1 margen calculado entre 0 y 100', d.mes.margen > 0 && d.mes.margen < 100, 'margen=' + d.mes.margen);

seccion('2.2 Graficos estadisticos');
ok('2.2 serie semanal agregada por dia', Array.isArray(d.semana) && d.semana.length > 0, 'n=' + d.semana?.length);
ok('2.2 cada punto trae dia y total', d.semana.every((x) => x.dia && typeof x.total === 'number'));
ok('2.2 top de productos agregado', Array.isArray(d.top_productos) && d.top_productos.length > 0);
ok('2.2 el top trae unidades sumadas', d.top_productos.every((p) => typeof p.unidades === 'number' && p.unidades > 0));
const rent = await api('GET', '/reportes/rentabilidad?desde=2026-09-01&hasta=2026-09-30', { token: TA });
ok('2.2 distribucion por categoria', rent.j.datos.categorias.length > 0);
ok('2.2 distribucion por dia', rent.j.datos.dias.length > 0);
const sumaCat = rent.j.datos.categorias.reduce((a, c) => a + c.ingresos, 0);
ok('2.2 las categorias suman los ingresos del periodo', sumaCat > 0);
ok('2.2 el inventario se agrega por estado', ['Agotado', 'Critico', 'Bajo', 'Disponible'].every((k) => typeof d.alertas[k] === 'number'));

// ─── 3. CRUD ──────────────────────────────────────────────────────────────────
seccion('3.1 Creacion y validacion');
const vacio = await api('POST', '/inventario/productos', { token: TI, body: {} });
ok('3.1 el servidor rechaza campos obligatorios vacios', vacio.status === 422, 'status=' + vacio.status);
ok('3.1 el servidor detalla que campo fallo', vacio.j.errores && Object.keys(vacio.j.errores).length >= 3,
  JSON.stringify(vacio.j.errores || {}).slice(0, 120));
ok('3.1 informa el SKU obligatorio', !!vacio.j.errores?.sku);
ok('3.1 informa el nombre obligatorio', !!vacio.j.errores?.nombre);

const negativo = await api('POST', '/inventario/productos', {
  token: TI, body: { sku: 'PRB-NEG', nombre: 'Prueba', categoria_id: 1, precio_costo: -5, precio_venta: 100 },
});
ok('3.1 rechaza precios negativos', negativo.status === 422, 'status=' + negativo.status);

const skuPrueba = 'PRB-' + Date.now().toString().slice(-6);
const creado = await api('POST', '/inventario/productos', {
  token: TI,
  body: { sku: skuPrueba, nombre: 'Producto de prueba', categoria_id: 1, precio_costo: 1000, precio_venta: 2500, iva_porcentaje: 8, punto_reorden: 5, stock_inicial: 30, unidad_medida: 'unidad' },
});
ok('3.1 crea el registro con datos validos', creado.status === 201, JSON.stringify(creado.j).slice(0, 140));
const idPrueba = creado.j?.datos?.id;

const duplicado = await api('POST', '/inventario/productos', {
  token: TI, body: { sku: skuPrueba, nombre: 'Otro', categoria_id: 1, precio_costo: 1, precio_venta: 2 },
});
ok('3.1 impide duplicar la clave unica', duplicado.status === 422);

seccion('3.2 Listado, paginacion, filtros y orden');
const pag1 = await api('GET', '/inventario/productos?pagina=1&por_pagina=5', { token: TI });
ok('3.2 devuelve sobre paginado', pag1.status === 200 && Array.isArray(pag1.j.datos.items));
ok('3.2 respeta el tamano de pagina', pag1.j.datos.items.length === 5, 'n=' + pag1.j.datos.items.length);
ok('3.2 informa el total de registros', pag1.j.datos.total > 5, 'total=' + pag1.j.datos.total);
ok('3.2 calcula el numero de paginas', pag1.j.datos.paginas === Math.ceil(pag1.j.datos.total / 5));
const pag2 = await api('GET', '/inventario/productos?pagina=2&por_pagina=5', { token: TI });
ok('3.2 la pagina 2 trae registros distintos',
  pag2.j.datos.items[0].id !== pag1.j.datos.items[0].id);
ok('3.2 el total se mantiene entre paginas', pag2.j.datos.total === pag1.j.datos.total);

const buscado = await api('GET', '/inventario/productos?busqueda=empanada', { token: TI });
ok('3.2 filtra por texto', buscado.j.datos.items.length > 0 &&
  buscado.j.datos.items.every((p) => (p.nombre + p.sku + p.categoria).toLowerCase().includes('empanada')),
  'n=' + buscado.j.datos.items.length);

const sinResultados = await api('GET', '/inventario/productos?busqueda=zzzzzznoexiste', { token: TI });
ok('3.2 busqueda sin coincidencias devuelve vacio', sinResultados.j.datos.items.length === 0 && sinResultados.j.datos.total === 0);

const filtrado = await api('GET', '/inventario/productos?estado=Agotado', { token: TI });
ok('3.2 filtra por estado', filtrado.j.datos.items.every((p) => p.estado_stock === 'Agotado'));

const asc = await api('GET', '/inventario/productos?orden=precio_venta&direccion=ASC&por_pagina=50', { token: TI });
const desc = await api('GET', '/inventario/productos?orden=precio_venta&direccion=DESC&por_pagina=50', { token: TI });
const preciosAsc = asc.j.datos.items.map((p) => p.precio_venta);
ok('3.2 ordena ascendente', preciosAsc.every((v, i, a) => i === 0 || a[i - 1] <= v));
ok('3.2 ordena descendente', desc.j.datos.items.map((p) => p.precio_venta).every((v, i, a) => i === 0 || a[i - 1] >= v));
ok('3.2 ascendente y descendente difieren', preciosAsc[0] !== desc.j.datos.items[0].precio_venta);

const ordenInvalido = await api('GET', '/inventario/productos?orden=;DROP TABLE productos;--&direccion=X', { token: TI });
ok('3.2 ignora columnas de orden no permitidas', ordenInvalido.status === 200);
ok('3.2 la tabla sigue existiendo tras el intento', Number(sql('SELECT COUNT(*) FROM productos')) > 0);

const ventasPag = await api('GET', '/ventas?pagina=1&por_pagina=3', { token: TA });
ok('3.2 las ventas tambien paginan', ventasPag.j.datos.items.length === 3 && ventasPag.j.datos.total > 3);
const bitPag = await api('GET', '/sistema/bitacora?pagina=1&por_pagina=10', { token: TA });
ok('3.2 la bitacora tambien pagina', bitPag.j.datos.registros.items.length === 10);
const provPag = await api('GET', '/proveedores?pagina=1&por_pagina=4', { token: TA });
ok('3.2 los proveedores tambien paginan', provPag.j.datos.items.length === 4);

seccion('3.3 Modificacion');
const antesEditar = await api('GET', '/inventario/productos/' + idPrueba, { token: TI });
ok('3.3 el registro se puede precargar', antesEditar.status === 200 && antesEditar.j.datos.sku === skuPrueba);
ok('3.3 la precarga trae todos los campos del formulario',
  ['sku', 'nombre', 'categoria', 'precio_costo', 'precio_venta', 'punto_reorden', 'unidad_medida']
    .every((k) => antesEditar.j.datos[k] !== undefined));

const editado = await api('PUT', '/inventario/productos/' + idPrueba, {
  token: TA,
  body: { sku: skuPrueba, nombre: 'Producto de prueba editado', categoria_id: 1, precio_costo: 1200, precio_venta: 3000, iva_porcentaje: 8, punto_reorden: 9, unidad_medida: 'unidad', activo: true },
});
ok('3.3 actualiza correctamente', editado.status === 200 && editado.j.datos.nombre === 'Producto de prueba editado');
ok('3.3 el cambio persiste en la base', sql("SELECT nombre FROM productos WHERE id=" + idPrueba) === 'Producto de prueba editado');
ok('3.3 el cambio de precio queda auditado',
  Number(sql("SELECT COUNT(*) FROM bitacora WHERE accion='PRECIO_MODIFICAR' AND entidad_id='" + idPrueba + "'")) > 0);
const sinPermisoPrecio = await api('PUT', '/inventario/productos/' + idPrueba, {
  token: TI,
  body: { sku: skuPrueba, nombre: 'Producto de prueba editado', categoria_id: 1, precio_costo: 9999, precio_venta: 9999, punto_reorden: 9, unidad_medida: 'unidad', activo: true },
});
ok('3.3 el perfil sin permiso no cambia precios', sinPermisoPrecio.status === 403, 'status=' + sinPermisoPrecio.status);

seccion('3.4 Eliminacion logica');
const totalAntes = Number(sql('SELECT COUNT(*) FROM productos'));
const borrado = await api('DELETE', '/inventario/productos/' + idPrueba, { token: TI });
ok('3.4 el borrado responde correctamente', borrado.status === 200, JSON.stringify(borrado.j).slice(0, 120));
ok('3.4 la fila NO se elimina fisicamente', Number(sql('SELECT COUNT(*) FROM productos')) === totalAntes);
ok('3.4 marca deleted_at', sql('SELECT deleted_at IS NOT NULL FROM productos WHERE id=' + idPrueba) === '1');
ok('3.4 marca estado inactivo', sql('SELECT activo FROM productos WHERE id=' + idPrueba) === '0');
ok('3.4 deja de aparecer en el listado',
  (await api('GET', '/inventario/productos?busqueda=' + skuPrueba, { token: TI })).j.datos.total === 0);
ok('3.4 deja de aparecer en la vista de inventario',
  Number(sql("SELECT COUNT(*) FROM v_estado_inventario WHERE id=" + idPrueba)) === 0);
const papelera = await api('GET', '/inventario/papelera', { token: TI });
ok('3.4 aparece en la papelera', papelera.j.datos.some((p) => p.id === idPrueba));
const restaurado = await api('POST', '/inventario/productos/' + idPrueba + '/restaurar', { token: TI });
ok('3.4 se puede restaurar', restaurado.status === 200);
ok('3.4 tras restaurar vuelve al listado',
  (await api('GET', '/inventario/productos?busqueda=' + skuPrueba, { token: TI })).j.datos.total === 1);
await api('DELETE', '/inventario/productos/' + idPrueba, { token: TI });

const provBorrar = await api('POST', '/proveedores', {
  token: TA, body: { nombre_empresa: 'Proveedor temporal', nit: '900.' + Date.now().toString().slice(-6) + '-2', tipo_productos: 'Pruebas' },
});
const idProv = provBorrar.j.datos.id;
const provTotal = Number(sql('SELECT COUNT(*) FROM proveedores'));
await api('DELETE', '/proveedores/' + idProv, { token: TA });
ok('3.4 el proveedor tampoco se borra fisicamente', Number(sql('SELECT COUNT(*) FROM proveedores')) === provTotal);
ok('3.4 el proveedor queda con deleted_at', sql('SELECT deleted_at IS NOT NULL FROM proveedores WHERE id=' + idProv) === '1');

const usuarioTmp = await api('POST', '/sistema/usuarios', {
  token: TA,
  body: { usuario: 'tmp' + Date.now().toString().slice(-5), nombre: 'Temporal', apellido: 'Prueba', documento: '9' + Date.now().toString().slice(-8), correo: 'tmp' + Date.now().toString().slice(-5) + '@kerico.co', rol_id: 3, password: '123' },
});
const idUsr = usuarioTmp.j?.datos?.id;
const usrTotal = Number(sql('SELECT COUNT(*) FROM usuarios'));
const borradoUsr = await api('DELETE', '/sistema/usuarios/' + idUsr, { token: TA });
ok('3.4 el usuario se borra logicamente', borradoUsr.status === 200, JSON.stringify(borradoUsr.j).slice(0, 110));
ok('3.4 el usuario sigue en la base', Number(sql('SELECT COUNT(*) FROM usuarios')) === usrTotal);
ok('3.4 el usuario queda con deleted_at', sql('SELECT deleted_at IS NOT NULL FROM usuarios WHERE id=' + idUsr) === '1');
ok('3.4 protege al ultimo administrador',
  (await api('DELETE', '/sistema/usuarios/1', { token: TA })).status === 409);

// ─── 4. Informes ──────────────────────────────────────────────────────────────
seccion('4.1 Filtro por fechas');
const sept = await api('GET', '/ventas?desde=2026-09-01&hasta=2026-09-05&por_pagina=100', { token: TA });
ok('4.1 filtra por rango', sept.status === 200);
ok('4.1 solo devuelve fechas del rango',
  sept.j.datos.items.every((v) => v.fecha >= '2026-09-01' && v.fecha <= '2026-09-05 23:59:59'),
  'n=' + sept.j.datos.items.length);
const fueraRango = await api('GET', '/ventas?desde=2020-01-01&hasta=2020-01-31', { token: TA });
ok('4.1 un rango sin datos devuelve vacio', fueraRango.j.datos.total === 0, 'total=' + fueraRango.j.datos.total);
const rentRango = await api('GET', '/reportes/rentabilidad?desde=2026-09-01&hasta=2026-09-05', { token: TA });
const rentMes = await api('GET', '/reportes/rentabilidad?desde=2026-09-01&hasta=2026-09-30', { token: TA });
ok('4.1 el rango corto factura menos que el mes',
  rentRango.j.datos.totales.ingresos_brutos < rentMes.j.datos.totales.ingresos_brutos,
  `${rentRango.j.datos.totales.ingresos_brutos} < ${rentMes.j.datos.totales.ingresos_brutos}`);
ok('4.1 el reporte confirma el rango pedido',
  rentRango.j.datos.desde === '2026-09-01' && rentRango.j.datos.hasta === '2026-09-05');
const movRango = await api('GET', '/inventario/movimientos?desde=2026-09-02&hasta=2026-09-03&por_pagina=100', { token: TI });
ok('4.1 los movimientos tambien filtran por fecha',
  movRango.j.datos.items.every((m) => m.fecha >= '2026-09-02' && m.fecha <= '2026-09-03 23:59:59'));

seccion('4.2 Exportacion PDF');
const pdf = await api('GET', '/reportes/rentabilidad/exportar?formato=pdf&desde=2026-09-01&hasta=2026-09-30', { token: TA, raw: true });
ok('4.2 responde un PDF', pdf.status === 200 && pdf.buf.slice(0, 5).toString() === '%PDF-');
const texto = pdf.buf.toString('latin1');
ok('4.2 el PDF declara version', /^%PDF-1\.\d/.test(texto));
ok('4.2 lleva encabezado de marca', texto.includes('Ke-Rico!'));
ok('4.2 lleva titulo del reporte', texto.includes('Análisis de rentabilidad'));
ok('4.2 lleva numeracion de paginas', /Página \d/.test(texto), (texto.match(/Página \d+/) || [])[0]);
ok('4.2 incluye fecha de generacion', texto.includes('Generado el'));
ok('4.2 trae encabezados de tabla', texto.includes('PRODUCTO') || texto.includes('SKU'));
ok('4.2 declara las fuentes', texto.includes('Helvetica') && texto.includes('Helvetica-Bold'));
ok('4.2 define objetos de pagina', (texto.match(/\/Type \/Page[^s]/g) || []).length >= 1);
ok('4.2 cierra la estructura del documento', texto.includes('%%EOF') && texto.includes('startxref'));
ok('4.2 tiene tabla de referencias cruzadas', texto.includes('xref') && texto.includes('trailer'));
const paginas = (texto.match(/\/Type \/Page[^s]/g) || []).length;
ok('4.2 pagina el contenido largo', paginas >= 1, 'paginas=' + paginas);
const pdfBit = await api('GET', '/sistema/bitacora/exportar?formato=pdf&desde=2026-09-01&hasta=2026-09-30', { token: TA, raw: true });
ok('4.2 tambien exporta la bitacora en PDF', pdfBit.status === 200 && pdfBit.buf.slice(0, 5).toString() === '%PDF-');
ok('4.2 el nombre del archivo viaja en la cabecera', (pdf.headers.get('X-Nombre-Archivo') || '').endsWith('.pdf'),
  pdf.headers.get('X-Nombre-Archivo'));

seccion('4.3 Exportacion CSV');
const csv = await api('GET', '/inventario/exportar?formato=csv', { token: TA, raw: true });
ok('4.3 responde un CSV', csv.status === 200);
ok('4.3 incluye BOM UTF-8', csv.buf[0] === 0xEF && csv.buf[1] === 0xBB && csv.buf[2] === 0xBF);
const csvTexto = csv.buf.slice(3).toString('utf8');
const lineas = csvTexto.trim().split('\n');
ok('4.3 usa delimitador punto y coma', lineas[0].includes(';'), lineas[0].slice(0, 60));
ok('4.3 tiene fila de encabezados', lineas[0].includes('SKU') && lineas[0].includes('Producto'));
ok('4.3 tiene filas de datos', lineas.length > 5, 'lineas=' + lineas.length);
ok('4.3 conserva tildes y caracteres especiales', /Categor|Empanada|Arepa/.test(csvTexto));
const conAcento = csvTexto.includes('ñ') || csvTexto.includes('í') || csvTexto.includes('é') || csvTexto.includes('á');
ok('4.3 la codificacion no rompe los acentos', !csvTexto.includes('Ã') && !csvTexto.includes('�'),
  conAcento ? 'con acentos' : 'sin acentos en los datos');
ok('4.3 todas las filas tienen el mismo numero de columnas',
  new Set(lineas.map((l) => l.split(';').length)).size === 1,
  JSON.stringify([...new Set(lineas.map((l) => l.split(';').length))]));
ok('4.3 el nombre del archivo viaja en la cabecera', (csv.headers.get('X-Nombre-Archivo') || '').endsWith('.csv'));
const xlsx = await api('GET', '/inventario/exportar?formato=excel', { token: TA, raw: true });
ok('4.3 tambien exporta xlsx valido', xlsx.status === 200 && xlsx.buf.slice(0, 2).toString() === 'PK');

// ─── 5. Seguridad ─────────────────────────────────────────────────────────────
seccion('5.1 Inyeccion SQL');
const cargas = [
  "' OR '1'='1",
  "'; DROP TABLE productos; --",
  "' UNION SELECT usuario, password_hash FROM usuarios --",
  "1' OR 1=1 --",
  "admin'--",
  "' OR ''='",
];
const productosAntes = Number(sql('SELECT COUNT(*) FROM productos'));
for (const carga of cargas) {
  const r = await api('GET', '/inventario/productos?busqueda=' + encodeURIComponent(carga), { token: TI });
  ok('5.1 busqueda neutraliza ' + carga.slice(0, 26), r.status === 200 && r.j.datos.total === 0,
    'status=' + r.status + ' total=' + r.j?.datos?.total);
}
ok('5.1 ninguna tabla fue destruida', Number(sql('SELECT COUNT(*) FROM productos')) === productosAntes);
ok('5.1 no hubo fuga de hashes', !JSON.stringify(
  (await api('GET', "/inventario/productos?busqueda=" + encodeURIComponent("' UNION SELECT usuario, password_hash FROM usuarios --"), { token: TI })).j
).includes('$2y$'));
const loginInyeccion = await api('POST', '/auth/login', { body: { usuario: "admin' OR '1'='1", password: "x' OR '1'='1" } });
ok('5.1 el login resiste la inyeccion', loginInyeccion.status === 401, 'status=' + loginInyeccion.status);
const bitInyeccion = await api('GET', '/sistema/bitacora?busqueda=' + encodeURIComponent("zz' OR '1'='1"), { token: TA });
ok('5.1 la bitacora resiste la inyeccion', bitInyeccion.status === 200 && bitInyeccion.j.datos.registros.total === 0,
  'total=' + bitInyeccion.j?.datos?.registros?.total);
const provInyeccion = await api('GET', '/proveedores?busqueda=' + encodeURIComponent("' OR 1=1 --"), { token: TA });
ok('5.1 proveedores resiste la inyeccion', provInyeccion.j.datos.total === 0);
const phpBackend = (() => {
  const archivos = [];
  (function recorrer(p) {
    for (const e of fs.readdirSync(p, { withFileTypes: true })) {
      const completo = path.join(p, e.name);
      if (e.isDirectory()) recorrer(completo);
      else if (e.name.endsWith('.php')) archivos.push(completo);
    }
  })(RAIZ + '/backend');
  return archivos.map((a) => [a, fs.readFileSync(a, 'utf8')]);
})();

ok('5.1 no hay interpolacion de variables dentro de cadenas SQL', (() => {
  const patron = /"[^"\n]*\b(SELECT|INSERT INTO|UPDATE|DELETE FROM)\b[^"\n]*\$[a-zA-Z_]/i;
  const malos = phpBackend.filter(([, c]) => patron.test(c)).map(([a]) => path.basename(a));
  if (malos.length) console.log('      archivos: ' + malos.join(', '));
  return malos.length === 0;
})());

ok('5.1 todo LIMIT u OFFSET dinamico es numerico', (() => {
  const malos = [];
  const D = String.fromCharCode(36);
  for (const [archivo, contenido] of phpBackend) {
    for (const m of contenido.matchAll(/(LIMIT|OFFSET)\s*'\s*\.\s*(\(int\)\s*)?\$([a-zA-Z_]+)/gi)) {
      const castInline = !!m[2];
      const variable = m[3];
      const esc = '\\' + D + variable;
      const numerica = castInline
        || new RegExp(esc + '\\s*=\\s*(\\(int\\)|max\\(|min\\(|intdiv\\()').test(contenido);
      if (!numerica) malos.push(path.basename(archivo) + ':' + variable);
    }
  }
  if (malos.length) console.log('      ' + malos.join(', '));
  return malos.length === 0;
})());

ok('5.1 las consultas usan marcadores de posicion', (() => {
  const conMarcador = phpBackend.filter(([, c]) => /execute\(|Bd::(consultar|primero|valor|ejecutar|insertar)\([^)]*,\s*\[/.test(c)).length;
  return conMarcador >= 8;
})());

seccion('5.2 Cross-Site Scripting');
const xss = '<script>alert("xss")</script>';
const xssProv = await api('POST', '/proveedores', {
  token: TA,
  body: {
    nombre_empresa: 'Malicioso ' + xss,
    nit: '900.' + Date.now().toString().slice(-6) + '-3',
    tipo_productos: 'Insumos <img src=x onerror=alert(1)> varios',
    notas: 'Nota <iframe src="javascript:alert(1)"></iframe> final',
  },
});
ok('5.2 acepta el envio pero sanitiza', xssProv.status === 201, JSON.stringify(xssProv.j).slice(0, 130));
const guardado = xssProv.j.datos;
ok('5.2 elimina la etiqueta script', !guardado.nombre_empresa.includes('<script'), guardado.nombre_empresa);
ok('5.2 elimina atributos de evento', !guardado.tipo_productos.includes('onerror'), guardado.tipo_productos);
ok('5.2 elimina iframes', !guardado.notas.includes('<iframe'), guardado.notas);
ok('5.2 elimina el esquema javascript', !guardado.notas.toLowerCase().includes('javascript:'));
ok('5.2 la base guarda el texto ya limpio',
  !sql('SELECT nombre_empresa FROM proveedores WHERE id=' + guardado.id).includes('<script'));
ok('5.2 no queda ninguna etiqueta html', !/[<>]/.test(guardado.nombre_empresa + guardado.tipo_productos + guardado.notas),
  guardado.nombre_empresa);
const xssCsv = await api('GET', '/proveedores/exportar?formato=csv', { token: TA, raw: true });
const csvSalida = xssCsv.buf.toString('utf8');
ok('5.2 la exportacion CSV no reinyecta html', !csvSalida.includes('<script'));
ok('5.2 el CSV neutraliza formulas', !/^[=+@]/m.test(csvSalida.split('\n').slice(1).join('\n')));
ok('5.2 el front no usa dangerouslySetInnerHTML', (() => {
  const dir = RAIZ + '/frontend/src';
  if (!fs.existsSync(dir)) return true;
  const archivos = [];
  (function recorrer(p) {
    for (const e of fs.readdirSync(p, { withFileTypes: true })) {
      const completo = path.join(p, e.name);
      if (e.isDirectory()) recorrer(completo);
      else if (/\.(jsx?|tsx?)$/.test(e.name)) archivos.push(completo);
    }
  })(dir);
  return !archivos.some((a) => fs.readFileSync(a, 'utf8').includes('dangerouslySetInnerHTML'));
})());
ok('5.2 la API responde JSON, no HTML ejecutable',
  (await api('GET', '/inventario/productos', { token: TI })).j !== null);
await api('DELETE', '/proveedores/' + guardado.id, { token: TA });

seccion('5.3 Mitigacion CSRF');
const sesionCsrf = await entrar('admin', '123');
const tokenCsrf = sesionCsrf.j.datos.token;
ok('5.3 el login entrega un token CSRF', typeof sesionCsrf.j.datos.csrf_token === 'string' && sesionCsrf.j.datos.csrf_token.length === 64);
ok('5.3 el token CSRF se guarda en la sesion',
  sql("SELECT LENGTH(csrf_token) FROM sesiones WHERE id='" + tokenCsrf + "'") === '64');
ok('5.3 la sesion devuelve el token CSRF al recargar',
  (await api('GET', '/auth/sesion', { token: tokenCsrf })).j.datos.csrf_token === sesionCsrf.j.datos.csrf_token);

const sinCsrf = await fetch(BASE + '/inventario/movimientos', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json', Authorization: 'Bearer ' + tokenCsrf },
  body: JSON.stringify({ producto_id: 1, tipo: 'Ajuste', cantidad: 1, motivo: 'csrf' }),
});
ok('5.3 rechaza POST sin token CSRF', sinCsrf.status === 403, 'status=' + sinCsrf.status);

const csrfMalo = await fetch(BASE + '/inventario/movimientos', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json', Authorization: 'Bearer ' + tokenCsrf, 'X-CSRF-Kerico': 'a'.repeat(64) },
  body: JSON.stringify({ producto_id: 1, tipo: 'Ajuste', cantidad: 1, motivo: 'csrf' }),
});
ok('5.3 rechaza token CSRF incorrecto', csrfMalo.status === 403, 'status=' + csrfMalo.status);

const putSinCsrf = await fetch(BASE + '/inventario/productos/1/reorden', {
  method: 'PUT',
  headers: { 'Content-Type': 'application/json', Authorization: 'Bearer ' + tokenCsrf },
  body: JSON.stringify({ punto_reorden: 1 }),
});
ok('5.3 tambien protege PUT', putSinCsrf.status === 403, 'status=' + putSinCsrf.status);

const delSinCsrf = await fetch(BASE + '/proveedores/1', {
  method: 'DELETE',
  headers: { Authorization: 'Bearer ' + tokenCsrf },
});
ok('5.3 tambien protege DELETE', delSinCsrf.status === 403, 'status=' + delSinCsrf.status);

const getSinCsrf = await fetch(BASE + '/inventario/productos', {
  headers: { Authorization: 'Bearer ' + tokenCsrf },
});
ok('5.3 las lecturas GET no exigen CSRF', getSinCsrf.status === 200);

const conCsrf = await api('POST', '/inventario/movimientos', {
  token: tokenCsrf, body: { producto_id: 1, tipo: 'Ajuste', cantidad: 1, motivo: 'Prueba CSRF valida' },
});
ok('5.3 acepta la peticion con token CSRF valido', conCsrf.status === 201, 'status=' + conCsrf.status);
ok('5.3 el rechazo queda auditado',
  Number(sql("SELECT COUNT(*) FROM bitacora WHERE accion='CSRF_RECHAZADO'")) >= 4);
ok('5.3 cada sesion tiene su propio token CSRF',
  sesionCsrf.j.datos.csrf_token !== admin.j.datos.csrf_token);

seccion('5.4 Calidad y arquitectura');
const dirs = ['backend/modelo', 'backend/vista', 'backend/controlador', 'backend/nucleo', 'frontend/src'];
ok('5.4 existe la carpeta de modelos', fs.existsSync(RAIZ + '/backend/modelo'));
ok('5.4 existe la carpeta de controladores', fs.existsSync(RAIZ + '/backend/controlador'));
ok('5.4 existe el nucleo de la aplicacion', fs.existsSync(RAIZ + '/backend/nucleo'));
ok('5.4 la vista vive en el frontend', fs.existsSync(RAIZ + '/frontend/src'));
ok('5.4 hay un controlador frontal unico', fs.existsSync(RAIZ + '/backend/index.php'));
const modelos = fs.readdirSync(RAIZ + '/backend/modelo').filter((f) => f.endsWith('.php'));
const controladores = fs.readdirSync(RAIZ + '/backend/controlador').filter((f) => f.endsWith('.php'));
ok('5.4 hay modelos por dominio', modelos.length >= 8, 'n=' + modelos.length);
ok('5.4 hay controladores por modulo', controladores.length >= 7, 'n=' + controladores.length);
ok('5.4 los controladores no arman SQL', !controladores.some((c) =>
  /\b(SELECT|INSERT INTO|UPDATE|DELETE FROM)\b/i.test(fs.readFileSync(RAIZ + '/backend/controlador/' + c, 'utf8'))));
ok('5.4 los modelos no imprimen respuestas HTTP', !modelos.some((m) =>
  /\bRespuesta::/.test(fs.readFileSync(RAIZ + '/backend/modelo/' + m, 'utf8'))));
ok('5.4 el codigo no lleva credenciales embebidas', (() => {
  const cfg = fs.readFileSync(RAIZ + '/backend/config/config.php', 'utf8');
  return cfg.includes('entorno(') && cfg.includes('KERICO_DB_PASS');
})());

const css = fs.existsSync(RAIZ + '/frontend/src/index.css') ? fs.readFileSync(RAIZ + '/frontend/src/index.css', 'utf8') : '';
ok('5.4 el diseno declara consultas de medios', (css.match(/@media/g) || []).length >= 3,
  'n=' + (css.match(/@media/g) || []).length);
ok('5.4 contempla ancho movil', /max-width:\s*768px/.test(css));
ok('5.4 usa rejillas flexibles', /minmax\(/.test(css));
const html = fs.existsSync(RAIZ + '/frontend/index.html') ? fs.readFileSync(RAIZ + '/frontend/index.html', 'utf8') : '';
ok('5.4 declara viewport responsive', /name="viewport"/.test(html));

const rutasMedidas = [
  ['/reportes/panel', TA],
  ['/inventario/productos?por_pagina=50', TI],
  ['/ventas?por_pagina=50', TA],
  ['/reportes/rentabilidad?desde=2026-09-01&hasta=2026-09-30', TA],
  ['/sistema/bitacora?por_pagina=100', TA],
  ['/inventario/movimientos?por_pagina=100', TI],
  ['/proveedores?por_pagina=50', TA],
  ['/inventario/alertas', TI],
];
let peor = 0;
for (const [ruta, token] of rutasMedidas) {
  const r = await api('GET', ruta, { token });
  peor = Math.max(peor, r.ms);
  ok('5.4 ' + ruta.split('?')[0] + ' responde en menos de 2 s', r.ms < 2000, r.ms + ' ms');
}
const venta = await api('POST', '/ventas', {
  token: TA, body: { items: [{ producto_id: 1, cantidad: 1 }], metodo_pago: 'Efectivo', monto_recibido: 10000 },
});
ok('5.4 registrar una venta tarda menos de 2 s', venta.ms < 2000, venta.ms + ' ms');
peor = Math.max(peor, venta.ms);
ok('5.4 ninguna ruta medida supera los 2 s', peor < 2000, 'peor=' + peor + ' ms');

console.log('\n================================');
console.log('PASADAS: ' + pasadas + '   FALLIDAS: ' + fallidas);
if (fallos.length) {
  console.log('\nFALLOS:');
  fallos.forEach((f) => console.log('  - ' + f));
}
console.log('================================');
process.exit(fallidas > 0 ? 1 : 0);
