import { useCallback, useEffect, useMemo, useState } from 'react';
import { api, cantidad, consulta, descargar, dinero, fechaHora, hoy, primerDiaDelMes } from '../api.js';
import { useSesion } from '../contexto/Sesion.jsx';
import { useListado } from '../ganchos/useListado.js';
import {
  Aviso, Boton, Cargando, EncabezadoOrden, Entrada, EtiquetaEstado, Metrica, Modal,
  Paginacion, Pestanas, Seleccion, Tabla, Tarjeta,
} from '../ui/Componentes.jsx';

function Punto({ avisar, alRegistrar }) {
  const { puede } = useSesion();
  const [catalogo, setCatalogo] = useState([]);
  const [clientes, setClientes] = useState([]);
  const [cargando, setCargando] = useState(true);
  const [busqueda, setBusqueda] = useState('');
  const [carrito, setCarrito] = useState([]);
  const [clienteId, setClienteId] = useState('');
  const [metodo, setMetodo] = useState('Efectivo');
  const [recibido, setRecibido] = useState('');
  const [codigo, setCodigo] = useState('');
  const [promo, setPromo] = useState(null);
  const [mensajePromo, setMensajePromo] = useState(null);
  const [tipoComprobante, setTipoComprobante] = useState('Tirilla POS');
  const [error, setError] = useState('');
  const [guardando, setGuardando] = useState(false);
  const [recibo, setRecibo] = useState(null);

  const cargarCatalogo = useCallback(() => {
    return Promise.all([
      api.get('/ventas/catalogo'),
      api.get('/ventas/clientes'),
    ])
      .then(([prods, cls]) => {
        setCatalogo(prods.items || prods);
        setClientes(cls);
      })
      .catch((e) => setError(e.message))
      .finally(() => setCargando(false));
  }, []);

  useEffect(() => {
    cargarCatalogo();
  }, [cargarCatalogo]);

  const filtrados = useMemo(() => {
    const t = busqueda.trim().toLowerCase();
    if (!t) return catalogo;
    return catalogo.filter((p) => (p.nombre + ' ' + p.sku + ' ' + p.categoria).toLowerCase().includes(t));
  }, [catalogo, busqueda]);

  const agregar = (producto) => {
    setError('');
    const existe = carrito.find((l) => l.id === producto.id);

    if (existe) {
      if (existe.cantidad + 1 > producto.stock_actual) {
        setError('Solo quedan ' + cantidad(producto.stock_actual) + ' unidades de ' + producto.nombre + '.');
        return;
      }
      actualizarCarrito(carrito.map((l) => (
        l.id === producto.id ? { ...l, cantidad: l.cantidad + 1 } : l
      )));
      return;
    }

    if (producto.stock_actual < 1) {
      setError(producto.nombre + ' no tiene existencias.');
      return;
    }

    actualizarCarrito([...carrito, {
      id: producto.id,
      nombre: producto.nombre,
      sku: producto.sku,
      precio: producto.precio_venta,
      iva: producto.iva_porcentaje,
      stock: producto.stock_actual,
      cantidad: 1,
    }]);
  };

  const cambiarCantidad = (id, delta) => {
    setError('');
    const linea = carrito.find((l) => l.id === id);
    if (!linea) return;

    const nueva = linea.cantidad + delta;
    if (nueva > linea.stock) {
      setError('Solo quedan ' + cantidad(linea.stock) + ' unidades de ' + linea.nombre + '.');
      return;
    }

    actualizarCarrito(
      nueva <= 0
        ? carrito.filter((l) => l.id !== id)
        : carrito.map((l) => (l.id === id ? { ...l, cantidad: nueva } : l))
    );
  };

  const quitar = (id) => actualizarCarrito(carrito.filter((l) => l.id !== id));

  const subtotal = carrito.reduce((a, l) => a + l.precio * l.cantidad, 0);
  const descuento = promo && promo.valido ? promo.descuento : 0;
  const total = Math.max(subtotal - descuento, 0);
  const baseGravable = carrito.reduce((a, l) => a + (l.precio * l.cantidad) / (1 + l.iva / 100), 0);
  const factor = subtotal > 0 ? total / subtotal : 0;
  const impuesto = Math.round((total - baseGravable * factor) * 100) / 100;
  const cambio = metodo === 'Efectivo' && recibido !== '' ? Number(recibido) - total : 0;

  const simular = useCallback(async (codigoUsado, lineas) => {
    if (!codigoUsado.trim()) return;
    if (lineas.length === 0) {
      setPromo(null);
      setMensajePromo({ tipo: 'error', texto: 'Agregue productos antes de aplicar un código.' });
      return;
    }
    try {
      const r = await api.post('/descuentos/simular', {
        codigo: codigoUsado.trim(),
        items: lineas.map((l) => ({ producto_id: l.id, cantidad: l.cantidad })),
      });
      setPromo(r);
      setMensajePromo(
        r.valido
          ? { tipo: 'exito', texto: r.promocion.descripcion + ': -' + dinero(r.descuento) }
          : { tipo: 'aviso-amarillo', texto: r.motivo }
      );
    } catch (e) {
      setPromo(null);
      setMensajePromo({ tipo: 'error', texto: e.message });
    }
  }, []);

  const aplicarCodigo = () => simular(codigo, carrito);

  const actualizarCarrito = (nuevo) => {
    setCarrito(nuevo);
    if (promo !== null) simular(codigo, nuevo);
  };

  const quitarPromo = () => {
    setPromo(null);
    setCodigo('');
    setMensajePromo(null);
  };

  const confirmar = async () => {
    setError('');
    if (carrito.length === 0) {
      setError('Agregue al menos un producto a la venta.');
      return;
    }
    if (metodo === 'Efectivo' && (recibido === '' || Number(recibido) < total)) {
      setError('El monto recibido debe cubrir el total de la venta.');
      return;
    }

    setGuardando(true);
    try {
      const r = await api.post('/ventas', {
        items: carrito.map((l) => ({ producto_id: l.id, cantidad: l.cantidad })),
        cliente_id: clienteId || null,
        metodo_pago: metodo,
        monto_recibido: metodo === 'Efectivo' ? Number(recibido) : total,
        codigo_promocion: promo && promo.valido ? codigo.trim() : '',
        tipo_comprobante: tipoComprobante,
      });
      setRecibo(r.comprobante);
      setCarrito([]);
      quitarPromo();
      setRecibido('');
      setClienteId('');
      cargarCatalogo();
      avisar('Venta ' + r.venta.folio + ' registrada por ' + dinero(r.venta.total));
      if (alRegistrar) alRegistrar();
    } catch (e) {
      setError(e.message);
    } finally {
      setGuardando(false);
    }
  };

  if (cargando) return <Cargando texto="Cargando el catálogo" />;

  return (
    <div className="pos">
      <div style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
        <Entrada
          etiqueta="Buscar producto"
          placeholder="Nombre, código o categoría"
          value={busqueda}
          onChange={(e) => setBusqueda(e.target.value)}
        />

        {filtrados.length === 0 ? (
          <Aviso tipo="info">Ningún producto coincide con la búsqueda.</Aviso>
        ) : (
          <div className="rejilla-productos">
            {filtrados.map((p) => (
              <button
                key={p.id}
                type="button"
                className="producto"
                onClick={() => agregar(p)}
                disabled={p.stock_actual <= 0}
              >
                <span
                  className="imagen"
                  style={p.imagen ? { backgroundImage: `url(${p.imagen})` } : undefined}
                >
                  {p.imagen ? '' : p.nombre.slice(0, 1)}
                </span>
                <span className="cuerpo">
                  <span className="nombre">{p.nombre}</span>
                  <span className="precio tabular">{dinero(p.precio_venta)}</span>
                  <span className="existencias">
                    {p.stock_actual <= 0 ? 'Sin existencias' : cantidad(p.stock_actual) + ' disponibles'}
                  </span>
                </span>
              </button>
            ))}
          </div>
        )}
      </div>

      <div className="carrito">
        <Tarjeta titulo="Venta en curso" sub={carrito.length + ' linea(s)'}>
          {error ? <Aviso tipo="error">{error}</Aviso> : null}

          <div className="carrito-lineas">
            {carrito.length === 0 ? (
              <p style={{ color: 'var(--piedra)', fontSize: 13.5, margin: '12px 0' }}>
                Toque un producto para agregarlo a la venta.
              </p>
            ) : carrito.map((l) => (
              <div className="carrito-linea" key={l.id}>
                <div>
                  <div className="nombre">{l.nombre}</div>
                  <div className="detalle tabular">
                    {dinero(l.precio)} c/u · {dinero(l.precio * l.cantidad)}
                  </div>
                </div>
                <div style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
                  <span className="contador">
                    <button type="button" onClick={() => cambiarCantidad(l.id, -1)} aria-label="Quitar uno">−</button>
                    <span>{l.cantidad}</span>
                    <button type="button" onClick={() => cambiarCantidad(l.id, 1)} aria-label="Agregar uno">+</button>
                  </span>
                  <Boton variante="sutil" pequeno onClick={() => quitar(l.id)} aria-label="Quitar línea">✕</Boton>
                </div>
              </div>
            ))}
          </div>
        </Tarjeta>

        {puede('descuentos.aplicar') ? (
          <Tarjeta titulo="Código de descuento">
            <div style={{ display: 'flex', gap: 8, alignItems: 'flex-end' }}>
              <Entrada
                value={codigo}
                onChange={(e) => setCodigo(e.target.value.toUpperCase())}
                placeholder="BIENVENIDO10"
              />
              <Boton onClick={aplicarCodigo}>Aplicar</Boton>
              {promo ? <Boton variante="sutil" onClick={quitarPromo}>Quitar</Boton> : null}
            </div>
            {mensajePromo ? (
              <div style={{ marginTop: 10 }}>
                <Aviso tipo={mensajePromo.tipo}>{mensajePromo.texto}</Aviso>
              </div>
            ) : null}
          </Tarjeta>
        ) : null}

        <Tarjeta titulo="Cobro">
          <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
            <Seleccion
              etiqueta="Cliente"
              value={clienteId}
              onChange={(e) => setClienteId(e.target.value)}
              vacio="Consumidor final"
              opciones={clientes
                .filter((c) => c.nombre !== 'Consumidor final')
                .map((c) => ({ valor: c.id, texto: c.nombre }))}
            />

            <Seleccion
              etiqueta="Método de pago"
              value={metodo}
              onChange={(e) => setMetodo(e.target.value)}
              opciones={['Efectivo', 'Tarjeta', 'Transferencia', 'Mixto'].map((m) => ({ valor: m, texto: m }))}
            />

            <Seleccion
              etiqueta="Comprobante"
              value={tipoComprobante}
              onChange={(e) => setTipoComprobante(e.target.value)}
              opciones={['Tirilla POS', 'Factura de venta'].map((m) => ({ valor: m, texto: m }))}
            />

            {metodo === 'Efectivo' ? (
              <Entrada
                etiqueta="Monto recibido"
                type="number"
                min="0"
                value={recibido}
                onChange={(e) => setRecibido(e.target.value)}
                placeholder={String(Math.ceil(total / 1000) * 1000)}
              />
            ) : null}

            <div className="totales">
              <div className="fila"><span>Subtotal</span><span className="tabular">{dinero(subtotal)}</span></div>
              {descuento > 0 ? (
                <div className="fila descuento">
                  <span>Descuento</span><span className="tabular">- {dinero(descuento)}</span>
                </div>
              ) : null}
              <div className="fila"><span>Impuesto incluido</span><span className="tabular">{dinero(impuesto)}</span></div>
              <div className="fila grande"><span>Total</span><span className="tabular">{dinero(total)}</span></div>
              {metodo === 'Efectivo' && recibido !== '' && cambio >= 0 ? (
                <div className="fila"><span>Cambio</span><span className="tabular">{dinero(cambio)}</span></div>
              ) : null}
            </div>

            <Boton
              variante="primario"
              onClick={confirmar}
              disabled={guardando || carrito.length === 0}
              style={{ width: '100%' }}
            >
              {guardando ? 'Registrando...' : 'Confirmar venta'}
            </Boton>
          </div>
        </Tarjeta>
      </div>

      {recibo ? (
        <Modal
          titulo={'Comprobante ' + recibo.numero}
          angosto
          onCerrar={() => setRecibo(null)}
          pie={
            <>
              <Boton onClick={() => setRecibo(null)}>Cerrar</Boton>
              <Boton
                variante="primario"
                onClick={() => descargar('/comprobantes/' + recibo.id + '/descargar?formato=pdf', 'comprobante.pdf')}
              >
                Descargar PDF
              </Boton>
            </>
          }
        >
          <Tirilla comprobante={recibo} />
        </Modal>
      ) : null}
    </div>
  );
}

export function Tirilla({ comprobante }) {
  const e = comprobante.emisor || {};
  const c = comprobante.cliente || {};
  return (
    <div className="tirilla">
      <div className="centro">
        <div className="marca" style={{ fontSize: 22, color: 'var(--naranja)' }}>Ke-Rico!</div>
        <div style={{ fontSize: 11, color: 'var(--piedra)' }}>{e.razon_social}</div>
        <div style={{ fontSize: 11, color: 'var(--piedra)' }}>NIT {e.nit}</div>
        <div style={{ fontSize: 11, color: 'var(--piedra)' }}>{e.direccion}</div>
        <div style={{ fontSize: 11, color: 'var(--piedra)' }}>{e.telefono}</div>
      </div>
      <hr />
      <div style={{ fontSize: 12 }}>
        <div><strong>{comprobante.tipo}</strong> N.º {comprobante.numero}</div>
        <div>Fecha: {fechaHora(comprobante.fecha_emision)}</div>
        <div>Venta: {comprobante.folio}</div>
        <div>Cajero: {comprobante.cajero}</div>
        <div>Cliente: {c.nombre || 'Consumidor final'}</div>
        {c.documento ? <div>{c.tipo_documento} {c.documento}</div> : null}
      </div>
      <hr />
      <table>
        <tbody>
          {(comprobante.detalle || []).map((d, i) => (
            <tr key={i}>
              <td>
                {d.nombre}
                <br />
                <span style={{ color: 'var(--piedra)' }} className="tabular">
                  {cantidad(d.cantidad)} x {dinero(d.precio_unitario)}
                </span>
              </td>
              <td style={{ textAlign: 'right' }} className="tabular">{dinero(d.total)}</td>
            </tr>
          ))}
        </tbody>
      </table>
      <hr />
      <table>
        <tbody>
          <tr><td>Subtotal</td><td style={{ textAlign: 'right' }} className="tabular">{dinero(comprobante.subtotal)}</td></tr>
          {comprobante.descuento > 0 ? (
            <tr><td>Descuento</td><td style={{ textAlign: 'right' }} className="tabular">- {dinero(comprobante.descuento)}</td></tr>
          ) : null}
          <tr><td>Base gravable</td><td style={{ textAlign: 'right' }} className="tabular">{dinero(comprobante.base_gravable)}</td></tr>
          <tr><td>Impuesto</td><td style={{ textAlign: 'right' }} className="tabular">{dinero(comprobante.impuesto)}</td></tr>
          <tr>
            <td style={{ fontWeight: 700, fontSize: 15 }}>TOTAL</td>
            <td style={{ textAlign: 'right', fontWeight: 700, fontSize: 15 }} className="tabular">
              {dinero(comprobante.total)}
            </td>
          </tr>
        </tbody>
      </table>
      <hr />
      <div className="centro" style={{ fontSize: 10, color: 'var(--piedra)' }}>
        {comprobante.resolucion_dian}
      </div>
      <div className="centro" style={{ fontSize: 11, marginTop: 8 }}>
        Gracias por su compra
      </div>
    </div>
  );
}

function Historial() {
  const { puede, avisar } = useSesion();
  const lista = useListado('/ventas', { desde: primerDiaDelMes(), hasta: hoy(), busqueda: '', estado: '' }, { ordenInicial: 'fecha' });
  const [detalle, setDetalle] = useState(null);
  const [anulando, setAnulando] = useState(null);
  const [motivo, setMotivo] = useState('');
  const [errorAnular, setErrorAnular] = useState('');

  const abrir = async (id) => {
    try {
      setDetalle(await api.get('/ventas/' + id));
    } catch (e) {
      avisar(e.message, 'error');
    }
  };

  const anular = async () => {
    setErrorAnular('');
    if (motivo.trim().length < 5) {
      setErrorAnular('Describa el motivo con al menos 5 caracteres.');
      return;
    }
    try {
      await api.post('/ventas/' + anulando.id + '/anular', { motivo });
      avisar('Venta ' + anulando.folio + ' anulada y existencias devueltas');
      setAnulando(null);
      setMotivo('');
      setDetalle(null);
      lista.recargar();
    } catch (e) {
      setErrorAnular(e.message);
    }
  };

  return (
    <>
      <div className="filtros">
        <Entrada
          etiqueta="Buscar"
          placeholder="Folio o cliente"
          value={lista.filtros.busqueda}
          onChange={(e) => lista.cambiarFiltro('busqueda', e.target.value)}
        />
        <Entrada etiqueta="Desde" type="date" value={lista.filtros.desde}
          onChange={(e) => lista.cambiarFiltro('desde', e.target.value)} />
        <Entrada etiqueta="Hasta" type="date" value={lista.filtros.hasta}
          onChange={(e) => lista.cambiarFiltro('hasta', e.target.value)} />
        <Seleccion
          etiqueta="Estado"
          value={lista.filtros.estado}
          onChange={(e) => lista.cambiarFiltro('estado', e.target.value)}
          vacio="Todos"
          opciones={[{ valor: 'Completada', texto: 'Completada' }, { valor: 'Anulada', texto: 'Anulada' }]}
        />
        <Boton
          onClick={() => descargar(
            '/ventas/exportar' + consulta({ formato: 'pdf', desde: lista.filtros.desde, hasta: lista.filtros.hasta, estado: lista.filtros.estado }),
            'ventas.pdf'
          )}
        >
          PDF
        </Boton>
        <Boton
          onClick={() => descargar(
            '/ventas/exportar' + consulta({ formato: 'csv', desde: lista.filtros.desde, hasta: lista.filtros.hasta, estado: lista.filtros.estado }),
            'ventas.csv'
          )}
        >
          CSV
        </Boton>
      </div>

      <Tarjeta plana>
        {lista.error ? <div style={{ padding: 16 }}><Aviso tipo="error">{lista.error}</Aviso></div> : null}
        {lista.cargando ? <Cargando /> : (
          <>
            <div className="tabla-envoltura">
              <table className="tabla">
                <thead>
                  <tr>
                    <EncabezadoOrden texto="Folio" campo="folio" orden={lista.orden} direccion={lista.direccion} onOrdenar={lista.ordenar} />
                    <EncabezadoOrden texto="Fecha" campo="fecha" orden={lista.orden} direccion={lista.direccion} onOrdenar={lista.ordenar} />
                    <EncabezadoOrden texto="Cajero" campo="cajero" orden={lista.orden} direccion={lista.direccion} onOrdenar={lista.ordenar} />
                    <EncabezadoOrden texto="Cliente" campo="cliente" orden={lista.orden} direccion={lista.direccion} onOrdenar={lista.ordenar} />
                    <th className="derecha">Ítems</th>
                    <th className="derecha">Descuento</th>
                    <EncabezadoOrden texto="Total" campo="total" orden={lista.orden} direccion={lista.direccion} onOrdenar={lista.ordenar} derecha />
                    <EncabezadoOrden texto="Pago" campo="pago" orden={lista.orden} direccion={lista.direccion} onOrdenar={lista.ordenar} />
                    <EncabezadoOrden texto="Estado" campo="estado" orden={lista.orden} direccion={lista.direccion} onOrdenar={lista.ordenar} />
                    <th />
                  </tr>
                </thead>
                <tbody>
                  {lista.items.length === 0 ? (
                    <tr><td colSpan={10}><div className="vacio">No hay ventas en el periodo seleccionado.</div></td></tr>
                  ) : lista.items.map((v) => (
                    <tr key={v.id}>
                      <td className="principal tabular">{v.folio}</td>
                      <td className="tenue">{fechaHora(v.fecha)}</td>
                      <td>{v.cajero}</td>
                      <td className="tenue">{v.cliente}</td>
                      <td className="derecha tabular">{v.lineas}</td>
                      <td className="derecha tabular">{v.descuento_total > 0 ? dinero(v.descuento_total) : '—'}</td>
                      <td className="derecha tabular principal">{dinero(v.total)}</td>
                      <td className="tenue">{v.metodo_pago}</td>
                      <td><EtiquetaEstado valor={v.estado} /></td>
                      <td style={{ textAlign: 'right', whiteSpace: 'nowrap' }}>
                        <Boton pequeno onClick={() => abrir(v.id)}>Ver</Boton>
                        {puede('ventas.anular') && v.estado === 'Completada' ? (
                          <Boton pequeno variante="peligro" onClick={() => { setAnulando(v); setMotivo(''); setErrorAnular(''); }}>
                            Anular
                          </Boton>
                        ) : null}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
            <Paginacion
              pagina={lista.pagina}
              paginas={lista.paginas}
              total={lista.total}
              porPagina={lista.porPagina}
              onPagina={lista.setPagina}
              onPorPagina={lista.setPorPagina}
            />
          </>
        )}
      </Tarjeta>

      {detalle ? (
        <Modal titulo={'Venta ' + detalle.folio} ancho onCerrar={() => setDetalle(null)}>
          <div className="rejilla-metricas">
            <Metrica etiqueta="Total" valor={dinero(detalle.total)} />
            <Metrica etiqueta="Impuesto" valor={dinero(detalle.impuesto_total)} tono="amarillo" />
            <Metrica etiqueta="Costo" valor={dinero(detalle.costo_total)} tono="neutro" />
            <Metrica etiqueta="Utilidad" valor={dinero(detalle.utilidad)} tono="exito" />
          </div>

          <Tabla
            columnas={[
              { texto: 'Código' }, { texto: 'Producto' }, { texto: 'Cant.', derecha: true },
              { texto: 'Precio', derecha: true }, { texto: 'Base', derecha: true },
              { texto: 'Impuesto', derecha: true }, { texto: 'Total', derecha: true },
            ]}
            filas={detalle.items}
            clave={(d, i) => i}
            render={(d) => (
              <>
                <td className="tabular tenue">{d.sku}</td>
                <td className="principal">{d.nombre}</td>
                <td className="derecha tabular">{cantidad(d.cantidad)}</td>
                <td className="derecha tabular">{dinero(d.precio_unitario)}</td>
                <td className="derecha tabular">{dinero(d.subtotal)}</td>
                <td className="derecha tabular">{dinero(d.impuesto)}</td>
                <td className="derecha tabular principal">{dinero(d.total)}</td>
              </>
            )}
          />

          {detalle.descuentos.length > 0 ? (
            <Aviso tipo="info">
              Descuentos aplicados: {detalle.descuentos.map((d) => d.codigo + ' (' + dinero(d.valor) + ')').join(', ')}
            </Aviso>
          ) : null}

          {detalle.estado === 'Anulada' ? (
            <Aviso tipo="error">Venta anulada. Motivo: {detalle.motivo_anulacion}</Aviso>
          ) : null}

          <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
            {detalle.comprobantes.map((c) => (
              <Boton
                key={c.id}
                onClick={() => descargar('/comprobantes/' + c.id + '/descargar?formato=pdf', 'comprobante.pdf')}
              >
                Descargar {c.numero}
              </Boton>
            ))}
          </div>
        </Modal>
      ) : null}

      {anulando ? (
        <Modal
          titulo={'Anular venta ' + anulando.folio}
          angosto
          onCerrar={() => setAnulando(null)}
          pie={
            <>
              <Boton onClick={() => setAnulando(null)}>Cancelar</Boton>
              <Boton variante="peligro" onClick={anular}>Anular venta</Boton>
            </>
          }
        >
          <Aviso tipo="aviso-amarillo">
            Al anular la venta se devuelven las existencias al inventario y el comprobante
            queda invalidado. La acción queda registrada en la bitácora.
          </Aviso>
          <Entrada
            etiqueta="Motivo de la anulación"
            value={motivo}
            onChange={(e) => setMotivo(e.target.value)}
            error={errorAnular}
            placeholder="Devolución del cliente, error de digitación, etc."
          />
        </Modal>
      ) : null}
    </>
  );
}

export default function GestionVentas() {
  const { puede, avisar } = useSesion();
  const [pestana, setPestana] = useState(puede('ventas.registrar') ? 'punto' : 'historial');
  const [resumen, setResumen] = useState(null);

  const cargarResumen = useCallback(() => {
    api.get('/ventas/resumen').then(setResumen).catch(() => setResumen(null));
  }, []);

  useEffect(() => { cargarResumen(); }, [cargarResumen]);

  const opciones = [];
  if (puede('ventas.registrar')) opciones.push({ id: 'punto', texto: 'Punto de venta' });
  opciones.push({ id: 'historial', texto: 'Historial de ventas' });

  return (
    <>
      <div className="encabezado">
        <div>
          <h1>Ventas y stock</h1>
          <p>Registro de ventas y control de existencias en tiempo real.</p>
        </div>
      </div>

      <div className="panel">
        {resumen ? (
          <div className="rejilla-metricas">
            <Metrica etiqueta="Ventas de hoy" valor={dinero(resumen.total)} nota={resumen.transacciones + ' transacciones'} />
            <Metrica etiqueta="Unidades vendidas" valor={cantidad(resumen.unidades)} tono="amarillo" />
            <Metrica etiqueta="Descuentos de hoy" valor={dinero(resumen.descuentos)} tono="info" />
            <Metrica etiqueta="Utilidad de hoy" valor={dinero(resumen.utilidad)} tono="exito" />
          </div>
        ) : null}

        <Pestanas opciones={opciones} activa={pestana} onCambiar={setPestana} />

        {pestana === 'punto'
          ? <Punto avisar={avisar} alRegistrar={cargarResumen} />
          : <Historial />}
      </div>
    </>
  );
}
