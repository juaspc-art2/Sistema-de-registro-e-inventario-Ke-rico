import { useEffect, useState } from 'react';
import { api, cantidad, consulta, descargar, dinero, fechaHora, hoy, primerDiaDelMes } from '../api.js';
import { useSesion } from '../contexto/Sesion.jsx';
import { useListado } from '../ganchos/useListado.js';
import {
  Aviso, Boton, Cargando, EncabezadoOrden, Entrada, EtiquetaEstado, Metrica, Modal,
  Paginacion, Seleccion, Tarjeta,
  opcionesEstado,
} from '../ui/Componentes.jsx';

const TIPOS = ['Entrada', 'Salida', 'Ajuste', 'Merma', 'Devolucion'];

const MOTIVOS = [
  'Corrección de conteo físico',
  'Producto quemado en freidora',
  'Producto vencido',
  'Caída o derrame',
  'Consumo interno',
  'Devolución de cliente',
  'Ingreso manual de mercancía',
];

export default function Auditoria() {
  const { puede, avisar } = useSesion();
  const lista = useListado(
    '/inventario/movimientos',
    { desde: primerDiaDelMes(), hasta: hoy(), busqueda: '', tipo: '' },
    { ordenInicial: 'fecha' }
  );
  const [resumen, setResumen] = useState(null);
  const [productos, setProductos] = useState([]);
  const [registrando, setRegistrando] = useState(false);
  const [detalle, setDetalle] = useState(null);
  const [form, setForm] = useState({ producto_id: '', tipo: 'Ajuste', cantidad: '', motivo: MOTIVOS[0], observaciones: '' });
  const [errores, setErrores] = useState({});
  const [errorForm, setErrorForm] = useState('');
  const [guardando, setGuardando] = useState(false);

  useEffect(() => {
    api.get('/inventario/movimientos/resumen').then(setResumen).catch(() => setResumen(null));
    api.get('/inventario/productos?por_pagina=200')
      .then((r) => setProductos(r.items || []))
      .catch(() => setProductos([]));
  }, [lista.respuesta]);

  const poner = (clave, valor) => setForm((f) => ({ ...f, [clave]: valor }));

  const validar = () => {
    const e = {};
    if (!form.producto_id) e.producto_id = 'Seleccione el producto';
    if (form.cantidad === '' || Number(form.cantidad) <= 0) e.cantidad = 'Indique una cantidad mayor que cero';
    if (!form.motivo.trim() || form.motivo.trim().length < 3) e.motivo = 'Describa el motivo';
    setErrores(e);
    return Object.keys(e).length === 0;
  };

  const registrar = async () => {
    setErrorForm('');
    if (!validar()) return;
    setGuardando(true);
    try {
      const r = await api.post('/inventario/movimientos', {
        producto_id: Number(form.producto_id),
        tipo: form.tipo,
        cantidad: Number(form.cantidad),
        motivo: form.motivo,
        observaciones: form.observaciones,
      });
      avisar('Movimiento registrado. Stock de ' + r.producto.nombre + ': ' + cantidad(r.producto.stock_actual));
      setRegistrando(false);
      setForm({ producto_id: '', tipo: 'Ajuste', cantidad: '', motivo: MOTIVOS[0], observaciones: '' });
      lista.recargar();
    } catch (e) {
      setErrorForm(e.message);
      if (e.errores) setErrores(e.errores);
    } finally {
      setGuardando(false);
    }
  };

  const productoElegido = productos.find((p) => String(p.id) === String(form.producto_id));

  return (
    <>
      <div className="encabezado">
        <div>
          <h1>Auditoría de movimientos</h1>
          <p>Trazabilidad completa de entradas, salidas, ajustes y mermas.</p>
        </div>
        <div className="acciones">
          {puede('reportes.exportar') ? (
            <>
              <Boton onClick={() => descargar(
                '/inventario/movimientos/exportar' + consulta({ formato: 'pdf', desde: lista.filtros.desde, hasta: lista.filtros.hasta, tipo: lista.filtros.tipo }),
                'auditoria.pdf'
              )}>PDF</Boton>
              <Boton onClick={() => descargar(
                '/inventario/movimientos/exportar' + consulta({ formato: 'csv', desde: lista.filtros.desde, hasta: lista.filtros.hasta, tipo: lista.filtros.tipo }),
                'auditoria.csv'
              )}>CSV</Boton>
            </>
          ) : null}
          {puede('inventario.ajustar') ? (
            <Boton variante="primario" onClick={() => { setRegistrando(true); setErrorForm(''); setErrores({}); }}>
              + Registrar movimiento
            </Boton>
          ) : null}
        </div>
      </div>

      <div className="panel">
        {resumen ? (
          <div className="rejilla-metricas">
            <Metrica etiqueta="Movimientos de hoy" valor={String(resumen.total)} />
            <Metrica etiqueta="Entradas" valor={String(resumen.entradas)} tono="exito" />
            <Metrica etiqueta="Salidas" valor={String(resumen.salidas)} tono="error" />
            <Metrica etiqueta="Ajustes y mermas" valor={String(resumen.ajustes)} tono="amarillo" />
          </div>
        ) : null}

        <div className="filtros">
          <Entrada etiqueta="Buscar" placeholder="Producto, SKU o motivo" value={lista.filtros.busqueda}
            onChange={(e) => lista.cambiarFiltro('busqueda', e.target.value)} />
          <Entrada etiqueta="Desde" type="date" value={lista.filtros.desde}
            onChange={(e) => lista.cambiarFiltro('desde', e.target.value)} />
          <Entrada etiqueta="Hasta" type="date" value={lista.filtros.hasta}
            onChange={(e) => lista.cambiarFiltro('hasta', e.target.value)} />
          <Seleccion etiqueta="Tipo" value={lista.filtros.tipo}
            onChange={(e) => lista.cambiarFiltro('tipo', e.target.value)} vacio="Todos"
            opciones={opcionesEstado(TIPOS)} />
        </div>

        <Tarjeta plana>
          {lista.error ? <div style={{ padding: 16 }}><Aviso tipo="error">{lista.error}</Aviso></div> : null}
          {lista.cargando ? <Cargando /> : (
            <>
              <div className="tabla-envoltura">
                <table className="tabla">
                  <thead>
                    <tr>
                      <EncabezadoOrden texto="Fecha y hora" campo="fecha" orden={lista.orden} direccion={lista.direccion} onOrdenar={lista.ordenar} />
                      <EncabezadoOrden texto="Código" campo="sku" orden={lista.orden} direccion={lista.direccion} onOrdenar={lista.ordenar} />
                      <EncabezadoOrden texto="Producto" campo="producto" orden={lista.orden} direccion={lista.direccion} onOrdenar={lista.ordenar} />
                      <EncabezadoOrden texto="Tipo" campo="tipo" orden={lista.orden} direccion={lista.direccion} onOrdenar={lista.ordenar} />
                      <EncabezadoOrden texto="Cantidad" campo="cantidad" orden={lista.orden} direccion={lista.direccion} onOrdenar={lista.ordenar} derecha />
                      <th className="derecha">Antes</th>
                      <th className="derecha">Después</th>
                      <EncabezadoOrden texto="Usuario" campo="usuario" orden={lista.orden} direccion={lista.direccion} onOrdenar={lista.ordenar} />
                      <th>Motivo</th>
                      <th />
                    </tr>
                  </thead>
                  <tbody>
                    {lista.items.length === 0 ? (
                      <tr><td colSpan={10}><div className="vacio">No hay movimientos en el periodo seleccionado.</div></td></tr>
                    ) : lista.items.map((m) => (
                      <tr key={m.id}>
                        <td className="tenue tabular">{fechaHora(m.fecha)}</td>
                        <td className="tabular tenue">{m.sku}</td>
                        <td className="principal">{m.producto}</td>
                        <td><EtiquetaEstado valor={m.tipo} /></td>
                        <td className="derecha tabular principal">
                          {m.tipo === 'Entrada' || m.tipo === 'Devolucion' ? '+' : '−'}{cantidad(m.cantidad)}
                        </td>
                        <td className="derecha tabular tenue">{cantidad(m.stock_anterior)}</td>
                        <td className="derecha tabular">{cantidad(m.stock_nuevo)}</td>
                        <td className="tenue">{m.usuario}</td>
                        <td className="tenue">{m.motivo}</td>
                        <td style={{ textAlign: 'right' }}>
                          <Boton pequeno onClick={() => setDetalle(m)}>Ver</Boton>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
              <Paginacion pagina={lista.pagina} paginas={lista.paginas} total={lista.total}
                porPagina={lista.porPagina} onPagina={lista.setPagina} onPorPagina={lista.setPorPagina} />
            </>
          )}
        </Tarjeta>
      </div>

      {registrando ? (
        <Modal
          titulo="Registrar movimiento de inventario"
          onCerrar={() => setRegistrando(false)}
          pie={
            <>
              <Boton onClick={() => setRegistrando(false)}>Cancelar</Boton>
              <Boton variante="primario" onClick={registrar} disabled={guardando}>
                {guardando ? 'Registrando...' : 'Registrar'}
              </Boton>
            </>
          }
        >
          {errorForm ? <Aviso tipo="error">{errorForm}</Aviso> : null}
          <Aviso tipo="info">
            Todo movimiento queda firmado con su usuario, la fecha exacta y el stock antes
            y después del ajuste.
          </Aviso>

          <div className="rejilla-formulario">
            <Seleccion etiqueta="Producto" value={form.producto_id}
              onChange={(e) => poner('producto_id', e.target.value)} error={errores.producto_id}
              vacio="Seleccione"
              opciones={productos.map((p) => ({
                valor: p.id,
                texto: p.nombre + ' (' + cantidad(p.stock_actual) + ' ' + p.unidad_medida + ')',
              }))} />
            <Seleccion etiqueta="Tipo de movimiento" value={form.tipo}
              onChange={(e) => poner('tipo', e.target.value)}
              opciones={opcionesEstado(TIPOS)} />
            <Entrada etiqueta="Cantidad" type="number" min="0" step="0.001" value={form.cantidad}
              onChange={(e) => poner('cantidad', e.target.value)} error={errores.cantidad} />
            <Seleccion etiqueta="Motivo" value={form.motivo}
              onChange={(e) => poner('motivo', e.target.value)} error={errores.motivo}
              opciones={MOTIVOS.map((m) => ({ valor: m, texto: m }))} />
          </div>

          {productoElegido ? (
            <Aviso tipo={form.tipo === 'Entrada' || form.tipo === 'Devolucion' ? 'exito' : 'aviso-amarillo'}>
              Stock actual {cantidad(productoElegido.stock_actual)} {productoElegido.unidad_medida}.
              {form.cantidad ? ' Quedara en ' + cantidad(
                form.tipo === 'Entrada' || form.tipo === 'Devolucion'
                  ? productoElegido.stock_actual + Number(form.cantidad)
                  : productoElegido.stock_actual - Number(form.cantidad)
              ) + ' ' + productoElegido.unidad_medida + '.' : ''}
            </Aviso>
          ) : null}

          <Entrada etiqueta="Observaciones" value={form.observaciones}
            onChange={(e) => poner('observaciones', e.target.value)} ancho />
        </Modal>
      ) : null}

      {detalle ? (
        <Modal titulo={'Movimiento #' + detalle.id} angosto onCerrar={() => setDetalle(null)}>
          <div className="totales">
            <div className="fila"><span>Producto</span><span>{detalle.producto}</span></div>
            <div className="fila"><span>Código</span><span className="tabular">{detalle.sku}</span></div>
            <div className="fila"><span>Tipo</span><span><EtiquetaEstado valor={detalle.tipo} /></span></div>
            <div className="fila"><span>Cantidad</span><span className="tabular">{cantidad(detalle.cantidad)} {detalle.unidad_medida}</span></div>
            <div className="fila"><span>Stock anterior</span><span className="tabular">{cantidad(detalle.stock_anterior)}</span></div>
            <div className="fila"><span>Stock nuevo</span><span className="tabular">{cantidad(detalle.stock_nuevo)}</span></div>
            <div className="fila"><span>Costo unitario</span><span className="tabular">{dinero(detalle.costo_unitario)}</span></div>
            <div className="fila"><span>Origen</span><span>{detalle.referencia}{detalle.referencia_id ? ' #' + detalle.referencia_id : ''}</span></div>
            <div className="fila"><span>Usuario</span><span>{detalle.usuario}</span></div>
            <div className="fila"><span>Fecha</span><span className="tabular">{fechaHora(detalle.fecha)}</span></div>
            <div className="fila"><span>Motivo</span><span>{detalle.motivo}</span></div>
          </div>
          {detalle.observaciones ? <Aviso tipo="info">{detalle.observaciones}</Aviso> : null}
        </Modal>
      ) : null}
    </>
  );
}
