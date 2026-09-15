const BASE = process.env.KERICO_API || 'http://localhost/kerico/api';
let pasadas = 0, fallidas = 0;
const fallos = [];

function ok(nombre, cond, extra = '') {
  if (cond) { pasadas++; console.log('  PASS  ' + nombre); }
  else { fallidas++; fallos.push(nombre + (extra ? ' :: ' + extra : '')); console.log('  FALL  ' + nombre + (extra ? ' :: ' + extra : '')); }
}

export const CSRF = new Map();

async function api(metodo, ruta, { token, body, raw } = {}) {
  const h = {};
  if (body) h['Content-Type'] = 'application/json';
  if (token) h['Authorization'] = 'Bearer ' + token;
  if (token && CSRF.has(token)) h['X-CSRF-Kerico'] = CSRF.get(token);
  const t0 = Date.now();
  const r = await fetch(BASE + ruta, { method: metodo, headers: h, body: body ? JSON.stringify(body) : undefined });
  const ms = Date.now() - t0;
  if (raw) {
    const buf = Buffer.from(await r.arrayBuffer());
    return { status: r.status, buf, ms, headers: r.headers };
  }
  let j = null;
  try { j = await r.json(); } catch { j = null; }
  return { status: r.status, j, ms };
}

const seccion = (t) => console.log('\n=== ' + t + ' ===');

function registrarCsrf(respuesta) {
  const d = respuesta && respuesta.j && respuesta.j.datos;
  if (d && d.token && d.csrf_token) CSRF.set(d.token, d.csrf_token);
  return respuesta;
}

function lista(respuesta) {
  const d = respuesta && respuesta.j ? respuesta.j.datos : null;
  if (d && Array.isArray(d.items)) return d.items;
  return Array.isArray(d) ? d : [];
}

// ---------- RF9 / RNF5 / RNF6 : Autenticacion ----------
seccion('RF9 Inicio de sesion');
let admin = registrarCsrf(await api('POST', '/auth/login', { body: { usuario: 'admin', password: '123' } }));
ok('RF9 login administrador', admin.status === 200 && admin.j.ok === true);
const TA = admin.j?.datos?.token;

const malo = registrarCsrf(await api('POST', '/auth/login', { body: { usuario: 'admin', password: 'incorrecta' } }));
ok('RF9 rechaza contrasena incorrecta', malo.status === 401, 'status=' + malo.status);

const inexistente = registrarCsrf(await api('POST', '/auth/login', { body: { usuario: 'noexiste', password: 'x' } }));
ok('RF9 rechaza usuario inexistente', inexistente.status === 401);

const sinToken = await api('GET', '/ventas');
ok('RNF4 bloquea acceso sin token', sinToken.status === 401);

const tokenFalso = await api('GET', '/ventas', { token: 'a'.repeat(64) });
ok('RNF4 bloquea token invalido', tokenFalso.status === 401);

const sesion = await api('GET', '/auth/sesion', { token: TA });
ok('RF9 devuelve sesion vigente', sesion.status === 200 && sesion.j.datos.usuario.usuario === 'admin');
ok('RNF6 expone ventana de inactividad de 15 min', sesion.j?.datos?.minutos_sesion === 15, 'min=' + sesion.j?.datos?.minutos_sesion);

const cajero = registrarCsrf(await api('POST', '/auth/login', { body: { usuario: 'cajero', password: '123' } }));
ok('RF9 login cajero', cajero.status === 200);
const TC = cajero.j?.datos?.token;

const inventario = registrarCsrf(await api('POST', '/auth/login', { body: { usuario: 'inventario', password: '123' } }));
const TI = inventario.j?.datos?.token;
const gerente = registrarCsrf(await api('POST', '/auth/login', { body: { usuario: 'gerente', password: '123' } }));
const TG = gerente.j?.datos?.token;
ok('RNF3 varias sesiones simultaneas activas', !!TA && !!TC && !!TI && !!TG);

// ---------- RNF4 : Restriccion por rol ----------
seccion('RNF4 Restriccion por perfil');
const cajeroReportes = await api('GET', '/reportes/rentabilidad', { token: TC });
ok('RNF4 cajero no accede a rentabilidad', cajeroReportes.status === 403, 'status=' + cajeroReportes.status);

const cajeroUsuarios = await api('GET', '/sistema/usuarios', { token: TC });
ok('RNF4 cajero no gestiona usuarios', cajeroUsuarios.status === 403);

const cajeroPromo = await api('POST', '/descuentos', { token: TC, body: { codigo: 'HACK', descripcion: 'x', tipo: 'porcentaje', valor: 99 } });
ok('RNF4 cajero no crea promociones', cajeroPromo.status === 403);

const adminReportes = await api('GET', '/reportes/rentabilidad', { token: TA });
ok('RNF4 administrador si accede a rentabilidad', adminReportes.status === 200);

// ---------- RF1 : Ventas y stock ----------
seccion('RF1 Gestion de ventas y stock');
const catalogo = await api('GET', '/ventas/catalogo', { token: TC });
ok('RF1 catalogo de venta disponible', catalogo.status === 200 && lista(catalogo).length > 0);
ok('RF1 catalogo excluye insumos', lista(catalogo).every(p => p.es_insumo === false));

const catalogoItems = lista(catalogo);
const emp = catalogoItems.find(p => p.sku === 'EMP-CAR');
ok('RF1 producto EMP-CAR presente', !!emp);
const stockAntes = emp.stock_actual;

const venta = await api('POST', '/ventas', {
  token: TC,
  body: { items: [{ producto_id: emp.id, cantidad: 3 }], metodo_pago: 'Efectivo', monto_recibido: 20000 },
});
ok('RF1 registra venta', venta.status === 201 && venta.j.ok, JSON.stringify(venta.j).slice(0, 200));
const v1 = venta.j?.datos?.venta;
ok('RF1 calcula total automaticamente', v1 && Math.abs(v1.total - emp.precio_venta * 3) < 0.01, 'total=' + v1?.total);
ok('RF1 calcula base + impuesto = total', v1 && Math.abs((v1.base_gravable + v1.impuesto_total) - v1.total) < 0.02);
ok('RF1 calcula el cambio', v1 && Math.abs(v1.cambio - (20000 - v1.total)) < 0.01);
ok('RNF1 venta responde en menos de 3 s', venta.ms < 3000, venta.ms + ' ms');

const despues = await api('GET', '/inventario/productos/' + emp.id, { token: TC });
ok('RF1 descuenta stock en tiempo real', Math.abs(despues.j.datos.stock_actual - (stockAntes - 3)) < 0.001,
  `antes=${stockAntes} despues=${despues.j.datos.stock_actual}`);

// ---------- RNF10 : Validaciones ----------
seccion('RNF10 Validaciones de integridad');
const sinStock = await api('POST', '/ventas', { token: TC, body: { items: [{ producto_id: emp.id, cantidad: 999999 }] } });
ok('RNF10 rechaza venta sin existencias', sinStock.status === 409, 'status=' + sinStock.status);

const negativa = await api('POST', '/ventas', { token: TC, body: { items: [{ producto_id: emp.id, cantidad: -5 }] } });
ok('RNF10 rechaza cantidad negativa', negativa.status === 422, 'status=' + negativa.status);

const cero = await api('POST', '/ventas', { token: TC, body: { items: [{ producto_id: emp.id, cantidad: 0 }] } });
ok('RNF10 rechaza cantidad cero', cero.status === 422);

const vacia = await api('POST', '/ventas', { token: TC, body: { items: [] } });
ok('RNF10 rechaza venta sin productos', vacia.status === 422);

const pagoCorto = await api('POST', '/ventas', {
  token: TC, body: { items: [{ producto_id: emp.id, cantidad: 2 }], metodo_pago: 'Efectivo', monto_recibido: 100 },
});
ok('RNF10 rechaza pago insuficiente', pagoCorto.status === 422, 'status=' + pagoCorto.status);

const agotado = catalogoItems.find(p => p.stock_actual === 0);
if (agotado) {
  const r = await api('POST', '/ventas', { token: TC, body: { items: [{ producto_id: agotado.id, cantidad: 1 }] } });
  ok('RNF10 rechaza producto agotado (' + agotado.sku + ')', r.status === 409);
}

const insumo = await api('GET', '/inventario/productos?busqueda=INS-MAS', { token: TI });
const insumoId = lista(insumo)[0]?.id;
const ventaInsumo = await api('POST', '/ventas', { token: TC, body: { items: [{ producto_id: insumoId, cantidad: 1 }] } });
ok('RNF10 impide vender un insumo', ventaInsumo.status === 409, 'status=' + ventaInsumo.status);

// ---------- RF2 : Comprobantes ----------
seccion('RF2 Emision de comprobantes');
const comp = venta.j?.datos?.comprobante;
ok('RF2 emite comprobante junto con la venta', !!comp && !!comp.numero);
ok('RF2 folio unico con prefijo', /^KR-\d{8}$/.test(comp?.numero || ''), comp?.numero);
ok('RF2 incluye fecha y hora', !!comp?.fecha_emision && comp.fecha_emision.includes(':'));
ok('RF2 incluye detalle de productos', Array.isArray(comp?.detalle) && comp.detalle.length === 1);
ok('RF2 incluye impuestos aplicados', typeof comp?.impuesto === 'number' && comp.impuesto > 0);
ok('RF2 incluye resolucion DIAN', (comp?.resolucion_dian || '').includes('DIAN'));
ok('RF2 registra datos del emisor', !!comp?.emisor?.nit);

const compGuardado = await api('GET', '/comprobantes/' + comp.id, { token: TC });
ok('RF2 comprobante queda almacenado para consulta', compGuardado.status === 200 && compGuardado.j.datos.numero === comp.numero);

const segundo = await api('POST', '/comprobantes', { token: TC, body: { venta_id: v1.id, tipo: 'Factura de venta' } });
ok('RF2 emite factura adicional', segundo.status === 201);
ok('RF2 consecutivo avanza', segundo.j.datos.consecutivo === comp.consecutivo + 1,
  `${comp.consecutivo} -> ${segundo.j?.datos?.consecutivo}`);

const pdfComp = await api('GET', '/comprobantes/' + comp.id + '/descargar?formato=pdf', { token: TC, raw: true });
ok('RF2 descarga comprobante en PDF', pdfComp.status === 200 && pdfComp.buf.slice(0, 5).toString() === '%PDF-');

// ---------- RF3 : Descuentos ----------
seccion('RF3 Descuentos y promociones');
const simul = await api('POST', '/descuentos/simular', {
  token: TC, body: { codigo: 'BIENVENIDO10', items: [{ producto_id: emp.id, cantidad: 10 }] },
});
ok('RF3 simula descuento porcentual', simul.status === 200 && simul.j.datos.valido === true, JSON.stringify(simul.j).slice(0, 200));
ok('RF3 calcula 10 por ciento', Math.abs(simul.j.datos.descuento - simul.j.datos.subtotal * 0.1) < 0.01);

const codigoMalo = await api('POST', '/descuentos/simular', { token: TC, body: { codigo: 'NOEXISTE', items: [{ producto_id: emp.id, cantidad: 1 }] } });
ok('RF3 rechaza codigo inexistente', codigoMalo.status === 422);

const ventaDesc = await api('POST', '/ventas', {
  token: TC,
  body: { items: [{ producto_id: emp.id, cantidad: 10 }], codigo_promocion: 'BIENVENIDO10', metodo_pago: 'Tarjeta' },
});
ok('RF3 aplica descuento en la venta', ventaDesc.status === 201);
const v2 = ventaDesc.j?.datos?.venta;
ok('RF3 descuento reduce el total', v2 && v2.descuento_total > 0 && Math.abs(v2.total - (v2.subtotal - v2.descuento_total)) < 0.01);

const hist = await api('GET', '/descuentos/historial', { token: TA });
const reg = lista(hist).find(d => d.folio === v2.folio);
ok('RF3 audita valor original vs final', !!reg && reg.valor_original > reg.valor_final,
  reg ? `${reg.valor_original} -> ${reg.valor_final}` : 'sin registro');
ok('RF3 audita el motivo del descuento', !!reg && reg.motivo.length > 0, reg?.motivo);

const promoAutorizada = await api('POST', '/ventas', {
  token: TC, body: { items: [{ producto_id: emp.id, cantidad: 5 }], codigo_promocion: 'ESTUDIANTE' },
});
ok('RF3 exige autorizacion para promociones restringidas', promoAutorizada.status === 403, 'status=' + promoAutorizada.status);

const promoAdmin = await api('POST', '/ventas', {
  token: TA, body: { items: [{ producto_id: emp.id, cantidad: 5 }], codigo_promocion: 'ESTUDIANTE' },
});
ok('RF3 supervisor si aplica promocion restringida', promoAdmin.status === 201, 'status=' + promoAdmin.status);

const manualCajero = await api('POST', '/ventas', {
  token: TC, body: { items: [{ producto_id: emp.id, cantidad: 2 }], descuento_manual: { valor: 1000, motivo: 'cortesia' } },
});
ok('RF3 cajero no aplica descuento manual', manualCajero.status === 403);

const nuevaPromo = await api('POST', '/descuentos', {
  token: TA,
  body: { codigo: 'PRUEBA' + Date.now().toString().slice(-5), descripcion: 'Promocion de prueba', tipo: 'fijo', valor: 2000, alcance: 'venta' },
});
ok('RF3 administrador crea promocion', nuevaPromo.status === 201, JSON.stringify(nuevaPromo.j).slice(0, 150));

const alternada = await api('POST', '/descuentos/' + nuevaPromo.j.datos.id + '/alternar', { token: TA });
ok('RF3 activa y desactiva promocion', alternada.status === 200 && alternada.j.datos.activa === false);

const excede = await api('POST', '/descuentos', { token: TA, body: { codigo: 'MAL' + Date.now().toString().slice(-4), descripcion: 'x', tipo: 'porcentaje', valor: 150 } });
ok('RF3 rechaza porcentaje mayor a 100', excede.status === 422);

// ---------- RF4 : Alertas de stock ----------
seccion('RF4 Alertas automaticas de stock');
const alertas = await api('GET', '/inventario/alertas', { token: TI });
ok('RF4 entrega alertas activas', alertas.status === 200 && alertas.j.datos.alertas.length > 0,
  'n=' + alertas.j?.datos?.alertas?.length);
const res = alertas.j.datos.resumen;
ok('RF4 resumen por severidad', typeof res.Agotado === 'number' && typeof res.Critico === 'number' && typeof res.Bajo === 'number',
  JSON.stringify(res));
ok('RF4 detecta producto agotado', res.Agotado >= 1);
ok('RF4 detecta stock critico', res.Critico >= 1);
ok('RF4 detecta stock bajo', res.Bajo >= 1);

const objetivo = catalogoItems.find(p => p.stock_actual > 5 && p.estado_stock === 'Disponible');
const subir = await api('PUT', '/inventario/productos/' + objetivo.id + '/reorden', {
  token: TI, body: { punto_reorden: objetivo.stock_actual + 100 },
});
ok('RF4 configura punto de reorden', subir.status === 200);
ok('RF4 genera alerta al cruzar el umbral', subir.j.datos.estado_stock !== 'Disponible', subir.j?.datos?.estado_stock);

const alertasTras = await api('GET', '/inventario/alertas', { token: TI });
ok('RF4 la nueva alerta aparece en el panel',
  alertasTras.j.datos.alertas.some(a => a.producto_id === objetivo.id));

const marcar = await api('POST', '/inventario/alertas/vistas', { token: TI });
ok('RF4 marca alertas como vistas', marcar.status === 200);

await api('PUT', '/inventario/productos/' + objetivo.id + '/reorden', { token: TI, body: { punto_reorden: objetivo.punto_reorden } });

// ---------- RF5 : Auditoria de movimientos ----------
seccion('RF5 Auditoria de movimientos de inventario');
const prodAjuste = catalogoItems.find(p => p.stock_actual > 20);
const antesAj = (await api('GET', '/inventario/productos/' + prodAjuste.id, { token: TI })).j.datos.stock_actual;

const merma = await api('POST', '/inventario/movimientos', {
  token: TI,
  body: { producto_id: prodAjuste.id, tipo: 'Merma', cantidad: 4, motivo: 'Producto quemado', observaciones: 'Lote de la tarde' },
});
ok('RF5 registra merma', merma.status === 201, JSON.stringify(merma.j).slice(0, 150));
ok('RF5 la merma descuenta stock', Math.abs(merma.j.datos.producto.stock_actual - (antesAj - 4)) < 0.001);

const movs = await api('GET', '/inventario/movimientos?producto_id=' + prodAjuste.id, { token: TI });
const movsItems = lista(movs);
const ultimo = movsItems[0];
ok('RF5 guarda stock anterior y nuevo', ultimo.stock_anterior === antesAj && ultimo.stock_nuevo === antesAj - 4,
  `${ultimo.stock_anterior} -> ${ultimo.stock_nuevo}`);
ok('RF5 guarda quien realizo el ajuste', ultimo.usuario.includes('Esteban'), ultimo.usuario);
ok('RF5 guarda la razon', ultimo.motivo === 'Producto quemado', ultimo.motivo);
ok('RF5 guarda la fecha exacta', !!ultimo.fecha && ultimo.fecha.includes(':'));

const ajuste = await api('POST', '/inventario/movimientos', {
  token: TI, body: { producto_id: prodAjuste.id, tipo: 'Ajuste', cantidad: 2, motivo: 'Correccion de conteo' },
});
ok('RF5 registra ajuste por conteo', ajuste.status === 201);

const salidaVenta = movsItems.find(m => m.referencia === 'Venta');
ok('RF5 las ventas tambien quedan trazadas', !!salidaVenta);

const mermaExcesiva = await api('POST', '/inventario/movimientos', {
  token: TI, body: { producto_id: prodAjuste.id, tipo: 'Salida', cantidad: 999999, motivo: 'prueba' },
});
ok('RNF10 impide dejar el stock negativo', mermaExcesiva.status === 422, 'status=' + mermaExcesiva.status);

const cajeroAjuste = await api('POST', '/inventario/movimientos', {
  token: TC, body: { producto_id: prodAjuste.id, tipo: 'Ajuste', cantidad: 1, motivo: 'prueba' },
});
ok('RNF4 cajero no ajusta inventario', cajeroAjuste.status === 403);

// ---------- RF6 : Proveedores ----------
seccion('RF6 Gestion de proveedores');
const provs = await api('GET', '/proveedores', { token: TG });
const provsItems = lista(provs);
ok('RF6 lista proveedores', provs.status === 200 && provsItems.length >= 12, 'n=' + provsItems.length);
const p0 = provsItems[0];
ok('RF6 almacena nombre de empresa', !!p0.nombre_empresa);
ok('RF6 almacena NIT', !!p0.nit);
ok('RF6 almacena telefono', !!p0.telefono);
ok('RF6 almacena tipo de productos', !!p0.tipo_productos);

const nit = '901.' + Date.now().toString().slice(-6) + '-1';
const nuevoProv = await api('POST', '/proveedores', {
  token: TG,
  body: { nombre_empresa: 'Proveedor de prueba', nit, telefono: '3000000000', correo: 'prueba@demo.co', tipo_productos: 'Insumos varios', estado: 'Activo', calificacion: 4.2 },
});
ok('RF6 crea proveedor', nuevoProv.status === 201, JSON.stringify(nuevoProv.j).slice(0, 150));

const dupNit = await api('POST', '/proveedores', {
  token: TG, body: { nombre_empresa: 'Otro', nit, tipo_productos: 'Otros' },
});
ok('RF6 impide NIT duplicado', dupNit.status === 422);

const provId = nuevoProv.j.datos.id;
const editado = await api('PUT', '/proveedores/' + provId, {
  token: TG, body: { nombre_empresa: 'Proveedor de prueba', nit, telefono: '3111111111', tipo_productos: 'Insumos varios', estado: 'En revision', calificacion: 3.1 },
});
ok('RF6 actualiza proveedor', editado.status === 200 && editado.j.datos.estado === 'En revision');

const stockCompraAntes = (await api('GET', '/inventario/productos/' + insumoId, { token: TI })).j.datos.stock_actual;
const compra = await api('POST', '/proveedores/compras', {
  token: TI,
  body: { proveedor_id: provId, items: [{ producto_id: insumoId, cantidad: 25, costo_unitario: 4300 }], estado: 'Recibida' },
});
ok('RF6 registra compra asociada al proveedor', compra.status === 201, JSON.stringify(compra.j).slice(0, 150));
const stockCompraDespues = (await api('GET', '/inventario/productos/' + insumoId, { token: TI })).j.datos.stock_actual;
ok('RF6 la compra aumenta el stock', Math.abs(stockCompraDespues - (stockCompraAntes + 25)) < 0.001,
  `${stockCompraAntes} -> ${stockCompraDespues}`);
ok('RF6 la compra rastrea el origen del producto', compra.j.datos.proveedor === 'Proveedor de prueba');

const movCompra = await api('GET', '/inventario/movimientos?producto_id=' + insumoId, { token: TI });
ok('RF5 la compra queda trazada como entrada', lista(movCompra)[0].tipo === 'Entrada' && lista(movCompra)[0].referencia === 'Compra');

const pendiente = await api('POST', '/proveedores/compras', {
  token: TI, body: { proveedor_id: provId, items: [{ producto_id: insumoId, cantidad: 5, costo_unitario: 4300 }], estado: 'Pendiente' },
});
ok('RF6 registra compra pendiente', pendiente.status === 201 && pendiente.j.datos.estado === 'Pendiente');
const recibida = await api('POST', '/proveedores/compras/' + pendiente.j.datos.id + '/recibir', { token: TI });
ok('RF6 recibe compra pendiente', recibida.status === 200 && recibida.j.datos.estado === 'Recibida');

// ---------- RF7 : Rentabilidad ----------
seccion('RF7 Analisis de rentabilidad');
const rent = await api('GET', '/reportes/rentabilidad?desde=2026-09-01&hasta=2026-09-30', { token: TG });
ok('RF7 genera reporte de rentabilidad', rent.status === 200);
ok('RNF2 reporte mensual en menos de 10 s', rent.ms < 10000, rent.ms + ' ms');
const t = rent.j.datos.totales;
ok('RF7 cruza ingresos con costo de ventas', t.ingresos_brutos > 0 && t.costo_ventas > 0);
ok('RF7 calcula utilidad bruta', Math.abs(t.utilidad_bruta - (t.base_gravable - t.costo_ventas)) < 0.05,
  `${t.utilidad_bruta} vs ${t.base_gravable - t.costo_ventas}`);
ok('RF7 calcula margen porcentual', t.margen > 0 && t.margen < 100, 'margen=' + t.margen);
ok('RF7 desglosa utilidad por producto', rent.j.datos.productos.length > 0 && rent.j.datos.productos[0].margen > 0);
ok('RF7 desglosa por categoria', rent.j.datos.categorias.length > 0);
ok('RF7 desglosa por periodo de tiempo', rent.j.datos.dias.length > 0);

const prod0 = rent.j.datos.productos[0];
ok('RF7 margen por producto coherente',
  Math.abs(prod0.utilidad - (prod0.ingresos - prod0.costo)) < 0.05);

const panel = await api('GET', '/reportes/panel', { token: TG });
ok('RF7 panel principal consolida indicadores', panel.status === 200 && !!panel.j.datos.ventas_hoy && !!panel.j.datos.alertas);

// ---------- RF8 : Bitacora ----------
seccion('RF8 Registro de actividad');
const bit = await api('GET', '/sistema/bitacora?por_pagina=200', { token: TA });
const bitRegistros = bit.j?.datos?.registros?.items || [];
ok('RF8 entrega la bitacora', bit.status === 200 && bitRegistros.length > 0);
const acciones = bitRegistros.map(r => r.accion);
ok('RF8 registra inicios de sesion', acciones.includes('LOGIN'));
ok('RF8 registra intentos fallidos', acciones.includes('LOGIN_FALLIDO'));
ok('RF8 registra ventas', acciones.includes('VENTA_REGISTRAR'));
ok('RF8 registra descuentos aplicados', acciones.includes('DESCUENTO_APLICAR'));
ok('RF8 registra movimientos de inventario', acciones.some(a => a.startsWith('INVENTARIO_')));
ok('RF8 registra emision de comprobantes', acciones.includes('COMPROBANTE_EMITIR'));
ok('RF8 registra accesos denegados', acciones.includes('ACCESO_DENEGADO'));
ok('RF8 registra cambios de proveedor', acciones.includes('PROVEEDOR_CREAR'));
ok('RF8 registra generacion de reportes', acciones.includes('REPORTE_GENERAR'));
const conUsuario = bitRegistros.filter(r => r.usuario && r.usuario !== 'Sistema');
ok('RF8 identifica que usuario hizo cada accion', conUsuario.length > 0);
ok('RF8 clasifica por nivel', bitRegistros.some(r => r.nivel === 'Error' || r.nivel === 'Advertencia'));
ok('RF8 registra la IP de origen', bitRegistros.some(r => r.ip.length > 0));

const cajeroBitacora = await api('GET', '/sistema/bitacora', { token: TC });
ok('RNF4 cajero no consulta la bitacora', cajeroBitacora.status === 403);

// ---------- RNF14 : Exportaciones ----------
seccion('RNF14 Exportacion PDF y Excel');
const pdfInv = await api('GET', '/inventario/exportar?formato=pdf', { token: TA, raw: true });
ok('RNF14 exporta inventario en PDF', pdfInv.status === 200 && pdfInv.buf.slice(0, 5).toString() === '%PDF-',
  pdfInv.buf?.slice(0, 20).toString());
ok('RNF14 el PDF tiene contenido', pdfInv.buf.length > 3000, pdfInv.buf.length + ' bytes');
ok('RNF14 el PDF cierra correctamente', pdfInv.buf.slice(-6).toString().includes('%%EOF'));

const xlsInv = await api('GET', '/inventario/exportar?formato=excel', { token: TA, raw: true });
ok('RNF14 exporta inventario en Excel', xlsInv.status === 200 && xlsInv.buf.slice(0, 2).toString() === 'PK');
ok('RNF14 el xlsx tiene contenido', xlsInv.buf.length > 1000, xlsInv.buf.length + ' bytes');

const pdfRent = await api('GET', '/reportes/rentabilidad/exportar?formato=pdf&desde=2026-09-01&hasta=2026-09-30', { token: TA, raw: true });
ok('RNF14 exporta rentabilidad en PDF', pdfRent.status === 200 && pdfRent.buf.slice(0, 5).toString() === '%PDF-');

const xlsRent = await api('GET', '/reportes/rentabilidad/exportar?formato=excel&desde=2026-09-01&hasta=2026-09-30', { token: TA, raw: true });
ok('RNF14 exporta rentabilidad en Excel', xlsRent.status === 200 && xlsRent.buf.slice(0, 2).toString() === 'PK');

const pdfMov = await api('GET', '/inventario/movimientos/exportar?formato=pdf&desde=2026-09-01&hasta=2026-09-30', { token: TA, raw: true });
ok('RNF14 exporta auditoria de movimientos en PDF', pdfMov.status === 200 && pdfMov.buf.slice(0, 5).toString() === '%PDF-');

const csvVentas = await api('GET', '/ventas/exportar?formato=csv&desde=2026-09-01&hasta=2026-09-30', { token: TA, raw: true });
ok('RNF14 exporta ventas en CSV', csvVentas.status === 200 && csvVentas.buf.length > 100);

const pdfBit = await api('GET', '/sistema/bitacora/exportar?formato=pdf&desde=2026-09-01&hasta=2026-09-30', { token: TA, raw: true });
ok('RNF14 exporta bitacora en PDF', pdfBit.status === 200 && pdfBit.buf.slice(0, 5).toString() === '%PDF-');

const formatoMalo = await api('GET', '/inventario/exportar?formato=doc', { token: TA });
ok('RNF14 rechaza formato no soportado', formatoMalo.status === 422);

// ---------- RNF7 : Respaldos ----------
seccion('RNF7 Respaldos automaticos');
const resp = await api('POST', '/sistema/respaldos', { token: TA, body: { tipo: 'Manual' } });
ok('RNF7 genera respaldo', resp.status === 201 && resp.j.datos.estado === 'Completado', JSON.stringify(resp.j).slice(0, 200));
ok('RNF7 el respaldo tiene contenido', resp.j.datos.tamano_bytes > 10000, resp.j?.datos?.tamano_bytes + ' bytes');

const listaResp = await api('GET', '/sistema/respaldos', { token: TA });
ok('RNF7 lista los respaldos', listaResp.status === 200 && listaResp.j.datos.respaldos.length > 0);
ok('RNF7 controla intervalo de 24 horas', listaResp.j.datos.pendiente === false);

const descargaResp = await api('GET', '/sistema/respaldos/' + resp.j.datos.id + '/descargar', { token: TA, raw: true });
ok('RNF7 descarga el respaldo', descargaResp.status === 200 && descargaResp.buf.toString().includes('INSERT INTO'));

const cajeroResp = await api('POST', '/sistema/respaldos', { token: TC, body: {} });
ok('RNF4 cajero no genera respaldos', cajeroResp.status === 403);

// ---------- RNF8 / estado ----------
seccion('Estado del sistema');
const estado = await api('GET', '/sistema/estado', { token: TA });
ok('RNF8 expone el estado del sistema', estado.status === 200 && estado.j.datos.estado !== 'caido');
ok('RNF8 informa version de base de datos', !!estado.j.datos.bd);
ok('RNF6 informa minutos de inactividad', estado.j.datos.minutos_inactividad === 15);

const sesiones = await api('GET', '/sistema/sesiones', { token: TA });
ok('RNF3 lista sesiones concurrentes', sesiones.status === 200 && lista(sesiones).length >= 4, 'n=' + lista(sesiones).length);

// ---------- Anulacion ----------
seccion('Anulacion de ventas');
const stockPrevio = (await api('GET', '/inventario/productos/' + emp.id, { token: TA })).j.datos.stock_actual;
const anular = await api('POST', '/ventas/' + v1.id + '/anular', { token: TA, body: { motivo: 'Prueba de anulacion del sistema' } });
ok('Anula la venta', anular.status === 200 && anular.j.datos.estado === 'Anulada');
const stockPost = (await api('GET', '/inventario/productos/' + emp.id, { token: TA })).j.datos.stock_actual;
ok('La anulacion devuelve el stock', Math.abs(stockPost - (stockPrevio + 3)) < 0.001, `${stockPrevio} -> ${stockPost}`);
const compAnulado = await api('GET', '/comprobantes/' + comp.id, { token: TA });
ok('La anulacion invalida el comprobante', compAnulado.j.datos.estado === 'Anulado');
const reAnular = await api('POST', '/ventas/' + v1.id + '/anular', { token: TA, body: { motivo: 'otra vez' } });
ok('No permite anular dos veces', reAnular.status === 409);
const cajeroAnula = await api('POST', '/ventas/' + v2.id + '/anular', { token: TC, body: { motivo: 'intento no autorizado' } });
ok('RNF4 cajero no anula ventas', cajeroAnula.status === 403);

// ---------- RNF6 : Cierre por inactividad ----------
seccion('RNF6 Cierre de sesion');
const logout = await api('POST', '/auth/logout', { token: TI });
ok('Cierra sesion manualmente', logout.status === 200);
const trasLogout = await api('GET', '/auth/sesion', { token: TI });
ok('El token deja de servir tras cerrar sesion', trasLogout.status === 401);

console.log('\n================================');
console.log('PASADAS: ' + pasadas + '   FALLIDAS: ' + fallidas);
if (fallos.length) {
  console.log('\nFALLOS:');
  fallos.forEach(f => console.log('  - ' + f));
}
console.log('================================');
process.exit(fallidas > 0 ? 1 : 0);
