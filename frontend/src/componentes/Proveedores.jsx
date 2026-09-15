import { useCallback, useEffect, useState } from 'react';
import { api, cantidad, consulta, descargar, dinero, fechaCorta, fechaHora, hoy, primerDiaDelMes } from '../api.js';
import { useSesion } from '../contexto/Sesion.jsx';
import { useListado } from '../ganchos/useListado.js';
import {
  Aviso, Boton, Cargando, EncabezadoOrden, Entrada, EtiquetaEstado, Metrica, Modal,
  Paginacion, Pestanas, Seleccion, Tabla, Tarjeta,
  opcionesEstado,
} from '../ui/Componentes.jsx';

const ESTADOS = ['Activo', 'Inactivo', 'En revision'];

const VACIO = {
  nombre_empresa: '', nit: '', telefono: '', correo: '', direccion: '',
  contacto_nombre: '', tipo_productos: '', estado: 'Activo', calificacion: '', notas: '',
};

function Formulario({ proveedor, onCerrar, onGuardado, avisar }) {
  const [datos, setDatos] = useState(proveedor ? {
    nombre_empresa: proveedor.nombre_empresa,
    nit: proveedor.nit,
    telefono: proveedor.telefono,
    correo: proveedor.correo,
    direccion: proveedor.direccion,
    contacto_nombre: proveedor.contacto_nombre,
    tipo_productos: proveedor.tipo_productos,
    estado: proveedor.estado,
    calificacion: String(proveedor.calificacion),
    notas: proveedor.notas,
  } : { ...VACIO });
  const [errores, setErrores] = useState({});
  const [error, setError] = useState('');
  const [guardando, setGuardando] = useState(false);

  const poner = (clave, valor) => setDatos((d) => ({ ...d, [clave]: valor }));

  const validar = () => {
    const e = {};
    if (!datos.nombre_empresa.trim()) e.nombre_empresa = 'El nombre de la empresa es obligatorio';
    if (!datos.nit.trim()) e.nit = 'El NIT es obligatorio';
    else if (datos.nit.trim().length < 5) e.nit = 'El NIT es demasiado corto';
    if (!datos.tipo_productos.trim()) e.tipo_productos = 'Indique qué productos suministra';
    if (datos.correo && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(datos.correo)) e.correo = 'El correo no es válido';
    if (datos.calificacion !== '' && (Number(datos.calificacion) < 0 || Number(datos.calificacion) > 5)) {
      e.calificacion = 'La calificación va de 0 a 5';
    }
    setErrores(e);
    return Object.keys(e).length === 0;
  };

  const guardar = async () => {
    setError('');
    if (!validar()) return;
    setGuardando(true);
    try {
      const cuerpo = { ...datos, calificacion: datos.calificacion === '' ? 0 : Number(datos.calificacion) };
      if (proveedor) await api.put('/proveedores/' + proveedor.id, cuerpo);
      else await api.post('/proveedores', cuerpo);
      avisar(proveedor ? 'Proveedor actualizado' : 'Proveedor registrado');
      onGuardado();
      onCerrar();
    } catch (e) {
      setError(e.message);
      if (e.errores) setErrores(e.errores);
    } finally {
      setGuardando(false);
    }
  };

  return (
    <Modal
      titulo={proveedor ? 'Editar ' + proveedor.nombre_empresa : 'Nuevo proveedor'}
      ancho
      onCerrar={onCerrar}
      pie={
        <>
          <Boton onClick={onCerrar}>Cancelar</Boton>
          <Boton variante="primario" onClick={guardar} disabled={guardando}>
            {guardando ? 'Guardando...' : 'Guardar'}
          </Boton>
        </>
      }
    >
      {error ? <Aviso tipo="error">{error}</Aviso> : null}

      <div className="rejilla-formulario">
        <Entrada etiqueta="Nombre de la empresa" value={datos.nombre_empresa}
          onChange={(e) => poner('nombre_empresa', e.target.value)} error={errores.nombre_empresa} />
        <Entrada etiqueta="NIT" value={datos.nit}
          onChange={(e) => poner('nit', e.target.value)} error={errores.nit} placeholder="900.100.001-1" />
        <Entrada etiqueta="Teléfono" value={datos.telefono}
          onChange={(e) => poner('telefono', e.target.value)} />
        <Entrada etiqueta="Correo" type="email" value={datos.correo}
          onChange={(e) => poner('correo', e.target.value)} error={errores.correo} />
        <Entrada etiqueta="Persona de contacto" value={datos.contacto_nombre}
          onChange={(e) => poner('contacto_nombre', e.target.value)} />
        <Seleccion etiqueta="Estado" value={datos.estado}
          onChange={(e) => poner('estado', e.target.value)}
          opciones={opcionesEstado(ESTADOS)} />
        <Entrada etiqueta="Calificación (0 a 5)" type="number" min="0" max="5" step="0.1"
          value={datos.calificacion} onChange={(e) => poner('calificacion', e.target.value)}
          error={errores.calificacion} />
        <Entrada etiqueta="Dirección" value={datos.direccion}
          onChange={(e) => poner('direccion', e.target.value)} />
      </div>

      <Entrada etiqueta="Qué productos suministra" value={datos.tipo_productos}
        onChange={(e) => poner('tipo_productos', e.target.value)} error={errores.tipo_productos} ancho />
      <Entrada etiqueta="Notas" value={datos.notas}
        onChange={(e) => poner('notas', e.target.value)} ancho />
    </Modal>
  );
}

function Directorio() {
  const { puede, avisar } = useSesion();
  const lista = useListado('/proveedores', { busqueda: '', estado: '' }, { ordenInicial: 'nombre', direccionInicial: 'ASC' });
  const [editando, setEditando] = useState(undefined);
  const [detalle, setDetalle] = useState(null);
  const [borrar, setBorrar] = useState(null);
  const [papelera, setPapelera] = useState(null);

  const ver = async (id) => {
    try {
      setDetalle(await api.get('/proveedores/' + id));
    } catch (e) {
      avisar(e.message, 'error');
    }
  };

  const eliminar = async () => {
    try {
      await api.del('/proveedores/' + borrar.id);
      avisar('Proveedor enviado a la papelera');
      setBorrar(null);
      lista.recargar();
    } catch (e) {
      avisar(e.message, 'error');
    }
  };

  const abrirPapelera = async () => {
    try {
      setPapelera(await api.get('/proveedores/papelera'));
    } catch (e) {
      avisar(e.message, 'error');
    }
  };

  const restaurar = async (id) => {
    try {
      await api.post('/proveedores/' + id + '/restaurar', {});
      avisar('Proveedor restaurado');
      setPapelera((p) => p.filter((x) => x.id !== id));
      lista.recargar();
    } catch (e) {
      avisar(e.message, 'error');
    }
  };

  const activos = lista.items.filter((p) => p.estado === 'Activo').length;
  const comprado = lista.items.reduce((a, p) => a + p.monto_comprado, 0);

  return (
    <>
      <div className="rejilla-metricas">
        <Metrica etiqueta="Proveedores" valor={String(lista.total)} />
        <Metrica etiqueta="Activos" valor={String(activos)} tono="exito" />
        <Metrica etiqueta="En revisión" valor={String(lista.items.filter((p) => p.estado === 'En revision').length)} tono="amarillo" />
        <Metrica etiqueta="Comprado histórico" valor={dinero(comprado)} tono="info" />
      </div>

      <div className="filtros">
        <Entrada etiqueta="Buscar" placeholder="Empresa, NIT o suministro" value={lista.filtros.busqueda}
          onChange={(e) => lista.cambiarFiltro('busqueda', e.target.value)} />
        <Seleccion etiqueta="Estado" value={lista.filtros.estado}
          onChange={(e) => lista.cambiarFiltro('estado', e.target.value)} vacio="Todos"
          opciones={opcionesEstado(ESTADOS)} />
        {puede('reportes.exportar') ? (
          <>
            <Boton onClick={() => descargar('/proveedores/exportar?formato=pdf', 'proveedores.pdf')}>PDF</Boton>
            <Boton onClick={() => descargar('/proveedores/exportar?formato=csv', 'proveedores.csv')}>CSV</Boton>
          </>
        ) : null}
        {puede('proveedores.gestionar') ? (
          <>
            <Boton onClick={abrirPapelera}>Papelera</Boton>
            <Boton variante="primario" onClick={() => setEditando(null)}>+ Nuevo proveedor</Boton>
          </>
        ) : null}
      </div>

      <Tarjeta plana>
        {lista.error ? <div style={{ padding: 16 }}><Aviso tipo="error">{lista.error}</Aviso></div> : null}
        {lista.cargando ? <Cargando /> : (
          <>
            <div className="tabla-envoltura">
              <table className="tabla">
                <thead>
                  <tr>
                    <EncabezadoOrden texto="Empresa" campo="nombre" orden={lista.orden} direccion={lista.direccion} onOrdenar={lista.ordenar} />
                    <EncabezadoOrden texto="NIT" campo="nit" orden={lista.orden} direccion={lista.direccion} onOrdenar={lista.ordenar} />
                    <th>Contacto</th>
                    <th>Suministra</th>
                    <EncabezadoOrden texto="Referencias" campo="productos" orden={lista.orden} direccion={lista.direccion} onOrdenar={lista.ordenar} derecha />
                    <EncabezadoOrden texto="Comprado" campo="monto" orden={lista.orden} direccion={lista.direccion} onOrdenar={lista.ordenar} derecha />
                    <EncabezadoOrden texto="Calif." campo="calificacion" orden={lista.orden} direccion={lista.direccion} onOrdenar={lista.ordenar} derecha />
                    <EncabezadoOrden texto="Estado" campo="estado" orden={lista.orden} direccion={lista.direccion} onOrdenar={lista.ordenar} />
                    <th />
                  </tr>
                </thead>
                <tbody>
                  {lista.items.length === 0 ? (
                    <tr><td colSpan={9}><div className="vacio">No hay proveedores registrados.</div></td></tr>
                  ) : lista.items.map((p) => (
                    <tr key={p.id}>
                      <td>
                        <span className="principal">{p.nombre_empresa}</span>
                        {p.telefono ? <><br /><span className="tenue tabular">{p.telefono}</span></> : null}
                      </td>
                      <td className="tabular tenue">{p.nit}</td>
                      <td className="tenue">{p.contacto_nombre || '—'}</td>
                      <td className="tenue">{p.tipo_productos}</td>
                      <td className="derecha tabular">{p.productos}</td>
                      <td className="derecha tabular">{dinero(p.monto_comprado)}</td>
                      <td className="derecha tabular">{p.calificacion.toFixed(1)}</td>
                      <td><EtiquetaEstado valor={p.estado} /></td>
                      <td style={{ textAlign: 'right', whiteSpace: 'nowrap' }}>
                        <Boton pequeno onClick={() => ver(p.id)}>Ver</Boton>
                        {puede('proveedores.gestionar') ? (
                          <>
                            <Boton pequeno onClick={() => setEditando(p)}>Editar</Boton>
                            <Boton pequeno variante="peligro" onClick={() => setBorrar(p)}>Eliminar</Boton>
                          </>
                        ) : null}
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

      {editando !== undefined ? (
        <Formulario proveedor={editando} avisar={avisar}
          onCerrar={() => setEditando(undefined)} onGuardado={lista.recargar} />
      ) : null}

      {detalle ? (
        <Modal titulo={detalle.nombre_empresa} ancho onCerrar={() => setDetalle(null)}>
          <div className="rejilla-metricas">
            <Metrica etiqueta="Referencias" valor={String(detalle.productos)} />
            <Metrica etiqueta="Compras" valor={String(detalle.compras)} tono="amarillo" />
            <Metrica etiqueta="Monto comprado" valor={dinero(detalle.monto_comprado)} tono="exito" />
            <Metrica etiqueta="Última compra"
              valor={detalle.ultima_compra ? fechaCorta(detalle.ultima_compra) : 'Sin compras'} tono="info" />
          </div>

          <div className="totales">
            <div className="fila"><span>NIT</span><span className="tabular">{detalle.nit}</span></div>
            <div className="fila"><span>Contacto</span><span>{detalle.contacto_nombre || 'Sin contacto'}</span></div>
            <div className="fila"><span>Teléfono</span><span className="tabular">{detalle.telefono || '—'}</span></div>
            <div className="fila"><span>Correo</span><span>{detalle.correo || '—'}</span></div>
            <div className="fila"><span>Dirección</span><span>{detalle.direccion || '—'}</span></div>
            <div className="fila"><span>Suministra</span><span>{detalle.tipo_productos}</span></div>
            <div className="fila"><span>Estado</span><span><EtiquetaEstado valor={detalle.estado} /></span></div>
          </div>

          {detalle.notas ? <Aviso tipo="info">{detalle.notas}</Aviso> : null}

          <Tarjeta titulo="Productos que suministra" plana>
            <Tabla
              columnas={[{ texto: 'Código' }, { texto: 'Producto' }, { texto: 'Costo', derecha: true }, { texto: 'Stock', derecha: true }]}
              filas={detalle.catalogo}
              clave={(p) => p.id}
              vacio="Este proveedor no tiene productos asociados."
              render={(p) => (
                <>
                  <td className="tabular tenue">{p.sku}</td>
                  <td className="principal">{p.nombre}</td>
                  <td className="derecha tabular">{dinero(p.precio_costo)}</td>
                  <td className="derecha tabular">{cantidad(p.stock_actual)}</td>
                </>
              )}
            />
          </Tarjeta>
        </Modal>
      ) : null}

      {borrar ? (
        <Modal titulo={'Eliminar ' + borrar.nombre_empresa} angosto onCerrar={() => setBorrar(null)}
          pie={<><Boton onClick={() => setBorrar(null)}>Cancelar</Boton><Boton variante="peligro" onClick={eliminar}>Eliminar</Boton></>}>
          <Aviso tipo="aviso-amarillo">
            El proveedor pasa a la papelera con borrado lógico. El historial de compras y el
            origen de la mercancía se conservan intactos.
          </Aviso>
        </Modal>
      ) : null}

      {papelera ? (
        <Modal titulo="Papelera de proveedores" onCerrar={() => setPapelera(null)}>
          <Tabla
            columnas={[{ texto: 'Empresa' }, { texto: 'NIT' }, { texto: 'Eliminado' }, { texto: '' }]}
            filas={papelera}
            clave={(p) => p.id}
            vacio="La papelera está vacía."
            render={(p) => (
              <>
                <td className="principal">{p.nombre_empresa}</td>
                <td className="tabular tenue">{p.nit}</td>
                <td className="tenue">{fechaHora(p.deleted_at)}</td>
                <td style={{ textAlign: 'right' }}>
                  <Boton pequeno onClick={() => restaurar(p.id)}>Restaurar</Boton>
                </td>
              </>
            )}
          />
        </Modal>
      ) : null}
    </>
  );
}

function Compras() {
  const { puede, avisar } = useSesion();
  const [compras, setCompras] = useState(null);
  const [proveedores, setProveedores] = useState([]);
  const [productos, setProductos] = useState([]);
  const [filtros, setFiltros] = useState({ desde: primerDiaDelMes(), hasta: hoy(), estado: '' });
  const [registrando, setRegistrando] = useState(false);
  const [detalle, setDetalle] = useState(null);
  const [error, setError] = useState('');
  const [nueva, setNueva] = useState({ proveedor_id: '', estado: 'Recibida', observaciones: '', items: [] });
  const [errorNueva, setErrorNueva] = useState('');

  const cargar = useCallback(() => {
    api.get('/proveedores/compras' + consulta(filtros))
      .then(setCompras)
      .catch((e) => setError(e.message));
  }, [filtros]);

  useEffect(() => { cargar(); }, [cargar]);

  useEffect(() => {
    api.get('/proveedores?por_pagina=200').then((r) => setProveedores(r.items || [])).catch(() => setProveedores([]));
    api.get('/inventario/productos?por_pagina=200').then((r) => setProductos(r.items || [])).catch(() => setProductos([]));
  }, []);

  const agregarLinea = () => setNueva((n) => ({
    ...n, items: [...n.items, { producto_id: '', cantidad: '', costo_unitario: '' }],
  }));

  const cambiarLinea = (i, clave, valor) => setNueva((n) => ({
    ...n, items: n.items.map((l, j) => (j === i ? { ...l, [clave]: valor } : l)),
  }));

  const quitarLinea = (i) => setNueva((n) => ({ ...n, items: n.items.filter((_, j) => j !== i) }));

  const registrar = async () => {
    setErrorNueva('');
    if (!nueva.proveedor_id) { setErrorNueva('Seleccione el proveedor.'); return; }
    if (nueva.items.length === 0) { setErrorNueva('Agregue al menos un producto.'); return; }
    if (nueva.items.some((l) => !l.producto_id || Number(l.cantidad) <= 0 || Number(l.costo_unitario) < 0)) {
      setErrorNueva('Revise que cada línea tenga producto, cantidad mayor que cero y costo válido.');
      return;
    }
    try {
      const c = await api.post('/proveedores/compras', {
        proveedor_id: Number(nueva.proveedor_id),
        estado: nueva.estado,
        observaciones: nueva.observaciones,
        items: nueva.items.map((l) => ({
          producto_id: Number(l.producto_id),
          cantidad: Number(l.cantidad),
          costo_unitario: Number(l.costo_unitario),
        })),
      });
      avisar('Compra ' + c.numero + ' registrada');
      setRegistrando(false);
      setNueva({ proveedor_id: '', estado: 'Recibida', observaciones: '', items: [] });
      cargar();
    } catch (e) {
      setErrorNueva(e.message);
    }
  };

  const recibir = async (id) => {
    try {
      const c = await api.post('/proveedores/compras/' + id + '/recibir', {});
      avisar('Compra ' + c.numero + ' recibida e ingresada al inventario');
      cargar();
    } catch (e) {
      avisar(e.message, 'error');
    }
  };

  const totalNueva = nueva.items.reduce((a, l) => a + (Number(l.cantidad) || 0) * (Number(l.costo_unitario) || 0), 0);

  return (
    <>
      <div className="filtros">
        <Entrada etiqueta="Desde" type="date" value={filtros.desde}
          onChange={(e) => setFiltros((f) => ({ ...f, desde: e.target.value }))} />
        <Entrada etiqueta="Hasta" type="date" value={filtros.hasta}
          onChange={(e) => setFiltros((f) => ({ ...f, hasta: e.target.value }))} />
        <Seleccion etiqueta="Estado" value={filtros.estado}
          onChange={(e) => setFiltros((f) => ({ ...f, estado: e.target.value }))} vacio="Todos"
          opciones={['Recibida', 'Pendiente', 'Anulada'].map((s) => ({ valor: s, texto: s }))} />
        {puede('compras.registrar') ? (
          <Boton variante="primario" onClick={() => { setRegistrando(true); setErrorNueva(''); }}>
            + Registrar compra
          </Boton>
        ) : null}
      </div>

      <Tarjeta plana>
        {error ? <div style={{ padding: 16 }}><Aviso tipo="error">{error}</Aviso></div> : null}
        {compras === null ? <Cargando /> : (
          <Tabla
            columnas={[
              { texto: 'Número' }, { texto: 'Proveedor' }, { texto: 'Fecha' }, { texto: 'Registro' },
              { texto: 'Líneas', derecha: true }, { texto: 'Total', derecha: true }, { texto: 'Estado' }, { texto: '' },
            ]}
            filas={compras}
            clave={(c) => c.id}
            vacio="No hay compras en el periodo seleccionado."
            render={(c) => (
              <>
                <td className="principal tabular">{c.numero}</td>
                <td>{c.proveedor}</td>
                <td className="tenue">{fechaCorta(c.fecha)}</td>
                <td className="tenue">{c.usuario}</td>
                <td className="derecha tabular">{c.lineas}</td>
                <td className="derecha tabular principal">{dinero(c.total)}</td>
                <td><EtiquetaEstado valor={c.estado} /></td>
                <td style={{ textAlign: 'right', whiteSpace: 'nowrap' }}>
                  <Boton pequeno onClick={async () => setDetalle(await api.get('/proveedores/compras/' + c.id))}>Ver</Boton>
                  {puede('compras.registrar') && c.estado === 'Pendiente' ? (
                    <Boton pequeno variante="primario" onClick={() => recibir(c.id)}>Recibir</Boton>
                  ) : null}
                </td>
              </>
            )}
          />
        )}
      </Tarjeta>

      {registrando ? (
        <Modal
          titulo="Registrar compra a proveedor"
          ancho
          onCerrar={() => setRegistrando(false)}
          pie={
            <>
              <Boton onClick={() => setRegistrando(false)}>Cancelar</Boton>
              <Boton variante="primario" onClick={registrar}>Registrar compra</Boton>
            </>
          }
        >
          {errorNueva ? <Aviso tipo="error">{errorNueva}</Aviso> : null}
          <Aviso tipo="info">
            Al marcar la compra como recibida, el stock sube automáticamente y el costo del
            producto se actualiza con el precio pagado.
          </Aviso>

          <div className="rejilla-formulario">
            <Seleccion etiqueta="Proveedor" value={nueva.proveedor_id}
              onChange={(e) => setNueva((n) => ({ ...n, proveedor_id: e.target.value }))}
              vacio="Seleccione" opciones={proveedores.map((p) => ({ valor: p.id, texto: p.nombre_empresa }))} />
            <Seleccion etiqueta="Estado" value={nueva.estado}
              onChange={(e) => setNueva((n) => ({ ...n, estado: e.target.value }))}
              opciones={[{ valor: 'Recibida', texto: 'Recibida ahora' }, { valor: 'Pendiente', texto: 'Pendiente de recibir' }]} />
          </div>

          <div className="tarjeta-titulo" style={{ marginTop: 6 }}>
            <span>Detalle de la compra</span>
            <Boton pequeno onClick={agregarLinea}>+ Agregar linea</Boton>
          </div>

          {nueva.items.length === 0 ? (
            <p style={{ fontSize: 13.5, color: 'var(--piedra)' }}>Agregue al menos un producto.</p>
          ) : nueva.items.map((l, i) => (
            <div key={i} style={{ display: 'grid', gridTemplateColumns: '2fr 1fr 1fr auto', gap: 10, alignItems: 'flex-end' }}>
              <Seleccion etiqueta={i === 0 ? 'Producto' : ''} value={l.producto_id}
                onChange={(e) => cambiarLinea(i, 'producto_id', e.target.value)}
                vacio="Seleccione" opciones={productos.map((p) => ({ valor: p.id, texto: p.nombre }))} />
              <Entrada etiqueta={i === 0 ? 'Cantidad' : ''} type="number" min="0" step="0.001" value={l.cantidad}
                onChange={(e) => cambiarLinea(i, 'cantidad', e.target.value)} />
              <Entrada etiqueta={i === 0 ? 'Costo unitario' : ''} type="number" min="0" value={l.costo_unitario}
                onChange={(e) => cambiarLinea(i, 'costo_unitario', e.target.value)} />
              <Boton variante="sutil" onClick={() => quitarLinea(i)}>✕</Boton>
            </div>
          ))}

          <div className="totales">
            <div className="fila grande"><span>Total de la compra</span><span className="tabular">{dinero(totalNueva)}</span></div>
          </div>

          <Entrada etiqueta="Observaciones" value={nueva.observaciones}
            onChange={(e) => setNueva((n) => ({ ...n, observaciones: e.target.value }))} ancho />
        </Modal>
      ) : null}

      {detalle ? (
        <Modal titulo={'Compra ' + detalle.numero} ancho onCerrar={() => setDetalle(null)}>
          <div className="totales">
            <div className="fila"><span>Proveedor</span><span>{detalle.proveedor}</span></div>
            <div className="fila"><span>NIT</span><span className="tabular">{detalle.nit}</span></div>
            <div className="fila"><span>Registro</span><span>{detalle.usuario}</span></div>
            <div className="fila"><span>Fecha</span><span className="tabular">{fechaHora(detalle.fecha)}</span></div>
            <div className="fila"><span>Estado</span><span><EtiquetaEstado valor={detalle.estado} /></span></div>
            <div className="fila grande"><span>Total</span><span className="tabular">{dinero(detalle.total)}</span></div>
          </div>
          <Tabla
            columnas={[{ texto: 'Código' }, { texto: 'Producto' }, { texto: 'Cantidad', derecha: true },
              { texto: 'Costo', derecha: true }, { texto: 'Subtotal', derecha: true }]}
            filas={detalle.items}
            clave={(d, i) => i}
            render={(d) => (
              <>
                <td className="tabular tenue">{d.sku}</td>
                <td className="principal">{d.nombre}</td>
                <td className="derecha tabular">{cantidad(d.cantidad)} {d.unidad_medida}</td>
                <td className="derecha tabular">{dinero(d.costo_unitario)}</td>
                <td className="derecha tabular principal">{dinero(d.subtotal)}</td>
              </>
            )}
          />
          {detalle.observaciones ? <Aviso tipo="info">{detalle.observaciones}</Aviso> : null}
        </Modal>
      ) : null}
    </>
  );
}

export default function Proveedores() {
  const { puede } = useSesion();
  const [pestana, setPestana] = useState('directorio');

  const opciones = [{ id: 'directorio', texto: 'Directorio' }];
  if (puede('compras.registrar')) opciones.push({ id: 'compras', texto: 'Compras y abastecimiento' });

  return (
    <>
      <div className="encabezado">
        <div>
          <h1>Proveedores</h1>
          <p>Directorio de proveedores y trazabilidad del origen de la mercancía.</p>
        </div>
      </div>

      <div className="panel">
        <Pestanas opciones={opciones} activa={pestana} onCambiar={setPestana} />
        {pestana === 'directorio' ? <Directorio /> : <Compras />}
      </div>
    </>
  );
}
