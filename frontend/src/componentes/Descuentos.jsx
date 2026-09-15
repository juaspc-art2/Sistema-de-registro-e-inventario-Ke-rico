import { useEffect, useState } from 'react';
import { api, consulta, descargar, dinero, fechaCorta, fechaHora, hoy, primerDiaDelMes } from '../api.js';
import { useSesion } from '../contexto/Sesion.jsx';
import { useListado } from '../ganchos/useListado.js';
import {
  Aviso, Boton, Cargando, EncabezadoOrden, Entrada, EtiquetaEstado, Metrica, Modal,
  Paginacion, Pestanas, Seleccion, Tabla, Tarjeta,
} from '../ui/Componentes.jsx';

const VACIA = {
  codigo: '', descripcion: '', tipo: 'porcentaje', valor: '', alcance: 'venta',
  producto_id: '', categoria_id: '', monto_minimo: '', fecha_inicio: '', fecha_fin: '',
  usos_maximos: '', requiere_autorizacion: false, activa: true,
};

function Formulario({ promocion, categorias, productos, onCerrar, onGuardado, avisar }) {
  const [datos, setDatos] = useState(promocion ? {
    codigo: promocion.codigo,
    descripcion: promocion.descripcion,
    tipo: promocion.tipo,
    valor: String(promocion.valor),
    alcance: promocion.alcance,
    producto_id: promocion.producto_id || '',
    categoria_id: promocion.categoria_id || '',
    monto_minimo: String(promocion.monto_minimo || ''),
    fecha_inicio: promocion.fecha_inicio || '',
    fecha_fin: promocion.fecha_fin || '',
    usos_maximos: String(promocion.usos_maximos || ''),
    requiere_autorizacion: promocion.requiere_autorizacion,
    activa: promocion.activa,
  } : { ...VACIA });
  const [errores, setErrores] = useState({});
  const [error, setError] = useState('');
  const [guardando, setGuardando] = useState(false);

  const poner = (clave, valor) => setDatos((d) => ({ ...d, [clave]: valor }));

  const validar = () => {
    const e = {};
    if (!datos.codigo.trim()) e.codigo = 'El código es obligatorio';
    else if (datos.codigo.trim().length < 3) e.codigo = 'Use al menos 3 caracteres';
    if (!datos.descripcion.trim()) e.descripcion = 'La descripción es obligatoria';
    if (datos.valor === '' || Number(datos.valor) <= 0) e.valor = 'Indique un valor mayor que cero';
    else if (datos.tipo === 'porcentaje' && Number(datos.valor) > 100) e.valor = 'Un porcentaje no puede superar 100';
    if (datos.alcance === 'producto' && !datos.producto_id) e.producto_id = 'Seleccione el producto';
    if (datos.alcance === 'categoria' && !datos.categoria_id) e.categoria_id = 'Seleccione la categoría';
    if (datos.fecha_inicio && datos.fecha_fin && datos.fecha_fin < datos.fecha_inicio) {
      e.fecha_fin = 'La fecha final no puede ser anterior a la inicial';
    }
    setErrores(e);
    return Object.keys(e).length === 0;
  };

  const guardar = async () => {
    setError('');
    if (!validar()) return;
    setGuardando(true);
    try {
      const cuerpo = {
        ...datos,
        valor: Number(datos.valor),
        monto_minimo: datos.monto_minimo === '' ? 0 : Number(datos.monto_minimo),
        usos_maximos: datos.usos_maximos === '' ? 0 : Number(datos.usos_maximos),
        producto_id: datos.alcance === 'producto' ? Number(datos.producto_id) : null,
        categoria_id: datos.alcance === 'categoria' ? Number(datos.categoria_id) : null,
      };
      if (promocion) await api.put('/descuentos/' + promocion.id, cuerpo);
      else await api.post('/descuentos', cuerpo);
      avisar(promocion ? 'Promoción actualizada' : 'Promoción creada');
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
      titulo={promocion ? 'Editar promoción ' + promocion.codigo : 'Nueva promoción'}
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
        <Entrada etiqueta="Codigo" value={datos.codigo}
          onChange={(e) => poner('codigo', e.target.value.toUpperCase())} error={errores.codigo} />
        <Seleccion etiqueta="Tipo" value={datos.tipo} onChange={(e) => poner('tipo', e.target.value)}
          opciones={[{ valor: 'porcentaje', texto: 'Porcentaje (%)' }, { valor: 'fijo', texto: 'Monto fijo ($)' }]} />
        <Entrada etiqueta={datos.tipo === 'porcentaje' ? 'Porcentaje' : 'Monto'} type="number" min="0"
          value={datos.valor} onChange={(e) => poner('valor', e.target.value)} error={errores.valor} />
        <Seleccion etiqueta="Alcance" value={datos.alcance} onChange={(e) => poner('alcance', e.target.value)}
          opciones={[
            { valor: 'venta', texto: 'Toda la venta' },
            { valor: 'producto', texto: 'Un producto' },
            { valor: 'categoria', texto: 'Una categoría' },
          ]} />

        {datos.alcance === 'producto' ? (
          <Seleccion etiqueta="Producto" value={datos.producto_id}
            onChange={(e) => poner('producto_id', e.target.value)} error={errores.producto_id}
            vacio="Seleccione" opciones={productos.map((p) => ({ valor: p.id, texto: p.nombre }))} />
        ) : null}

        {datos.alcance === 'categoria' ? (
          <Seleccion etiqueta="Categoria" value={datos.categoria_id}
            onChange={(e) => poner('categoria_id', e.target.value)} error={errores.categoria_id}
            vacio="Seleccione" opciones={categorias.map((c) => ({ valor: c.id, texto: c.nombre }))} />
        ) : null}

        <Entrada etiqueta="Monto mínimo de venta" type="number" min="0" value={datos.monto_minimo}
          onChange={(e) => poner('monto_minimo', e.target.value)} />
        <Entrada etiqueta="Usos máximos (0 = sin límite)" type="number" min="0" value={datos.usos_maximos}
          onChange={(e) => poner('usos_maximos', e.target.value)} />
        <Entrada etiqueta="Vigente desde" type="date" value={datos.fecha_inicio}
          onChange={(e) => poner('fecha_inicio', e.target.value)} />
        <Entrada etiqueta="Vigente hasta" type="date" value={datos.fecha_fin}
          onChange={(e) => poner('fecha_fin', e.target.value)} error={errores.fecha_fin} />
      </div>

      <Entrada etiqueta="Descripción visible al cliente" value={datos.descripcion}
        onChange={(e) => poner('descripcion', e.target.value)} error={errores.descripcion} ancho />

      <label style={{ display: 'flex', gap: 8, alignItems: 'center', fontSize: 14 }}>
        <input type="checkbox" checked={datos.requiere_autorizacion}
          onChange={(e) => poner('requiere_autorizacion', e.target.checked)} />
        Requiere autorización de un supervisor para aplicarse
      </label>

      <label style={{ display: 'flex', gap: 8, alignItems: 'center', fontSize: 14 }}>
        <input type="checkbox" checked={datos.activa} onChange={(e) => poner('activa', e.target.checked)} />
        Promocion activa
      </label>
    </Modal>
  );
}

function Promociones() {
  const { puede, avisar } = useSesion();
  const lista = useListado('/descuentos', { busqueda: '', activa: '' }, { ordenInicial: 'codigo', direccionInicial: 'ASC' });
  const [categorias, setCategorias] = useState([]);
  const [productos, setProductos] = useState([]);
  const [editando, setEditando] = useState(undefined);
  const [borrar, setBorrar] = useState(null);

  useEffect(() => {
    if (!puede('descuentos.gestionar')) return;
    api.get('/inventario/categorias').then(setCategorias).catch(() => setCategorias([]));
    api.get('/inventario/productos?por_pagina=200').then((r) => setProductos(r.items || [])).catch(() => setProductos([]));
  }, [puede]);

  const alternar = async (p) => {
    try {
      await api.post('/descuentos/' + p.id + '/alternar', {});
      avisar('Promocion ' + p.codigo + (p.activa ? ' desactivada' : ' activada'));
      lista.recargar();
    } catch (e) {
      avisar(e.message, 'error');
    }
  };

  const eliminar = async () => {
    try {
      await api.del('/descuentos/' + borrar.id);
      avisar('Promocion ' + borrar.codigo + ' enviada a la papelera');
      setBorrar(null);
      lista.recargar();
    } catch (e) {
      avisar(e.message, 'error');
    }
  };

  return (
    <>
      <div className="filtros">
        <Entrada etiqueta="Buscar" placeholder="Código o descripción" value={lista.filtros.busqueda}
          onChange={(e) => lista.cambiarFiltro('busqueda', e.target.value)} />
        <Seleccion etiqueta="Estado" value={lista.filtros.activa}
          onChange={(e) => lista.cambiarFiltro('activa', e.target.value)} vacio="Todas"
          opciones={[{ valor: '1', texto: 'Activas' }, { valor: '0', texto: 'Inactivas' }]} />
        {puede('descuentos.gestionar') ? (
          <Boton variante="primario" onClick={() => setEditando(null)}>+ Nueva promoción</Boton>
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
                    <EncabezadoOrden texto="Codigo" campo="codigo" orden={lista.orden} direccion={lista.direccion} onOrdenar={lista.ordenar} />
                    <th>Descripción</th>
                    <th>Alcance</th>
                    <EncabezadoOrden texto="Valor" campo="valor" orden={lista.orden} direccion={lista.direccion} onOrdenar={lista.ordenar} derecha />
                    <th>Vigencia</th>
                    <EncabezadoOrden texto="Usos" campo="usos" orden={lista.orden} direccion={lista.direccion} onOrdenar={lista.ordenar} derecha />
                    <EncabezadoOrden texto="Estado" campo="estado" orden={lista.orden} direccion={lista.direccion} onOrdenar={lista.ordenar} />
                    <th />
                  </tr>
                </thead>
                <tbody>
                  {lista.items.length === 0 ? (
                    <tr><td colSpan={8}><div className="vacio">No hay promociones registradas.</div></td></tr>
                  ) : lista.items.map((p) => (
                    <tr key={p.id}>
                      <td className="principal tabular">{p.codigo}</td>
                      <td>
                        {p.descripcion}
                        {p.requiere_autorizacion ? (
                          <><br /><span className="tenue">Requiere autorización</span></>
                        ) : null}
                      </td>
                      <td className="tenue">
                        {p.alcance === 'venta' ? 'Toda la venta'
                          : p.alcance === 'producto' ? 'Producto: ' + p.producto_nombre
                          : 'Categoria: ' + p.categoria_nombre}
                      </td>
                      <td className="derecha tabular principal">
                        {p.tipo === 'porcentaje' ? p.valor + ' %' : dinero(p.valor)}
                      </td>
                      <td className="tenue tabular">
                        {p.fecha_inicio ? fechaCorta(p.fecha_inicio) : 'Sin inicio'} — {p.fecha_fin ? fechaCorta(p.fecha_fin) : 'Sin fin'}
                      </td>
                      <td className="derecha tabular">
                        {p.usos_actuales}{p.usos_maximos > 0 ? ' / ' + p.usos_maximos : ''}
                      </td>
                      <td><EtiquetaEstado valor={p.activa ? 'Activa' : 'Inactiva'} /></td>
                      <td style={{ textAlign: 'right', whiteSpace: 'nowrap' }}>
                        {puede('descuentos.gestionar') ? (
                          <>
                            <Boton pequeno onClick={() => setEditando(p)}>Editar</Boton>
                            <Boton pequeno onClick={() => alternar(p)}>{p.activa ? 'Desactivar' : 'Activar'}</Boton>
                            <Boton pequeno variante="peligro" onClick={() => setBorrar(p)}>Eliminar</Boton>
                          </>
                        ) : <span className="tenue">Solo lectura</span>}
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
        <Formulario
          promocion={editando}
          categorias={categorias}
          productos={productos}
          avisar={avisar}
          onCerrar={() => setEditando(undefined)}
          onGuardado={lista.recargar}
        />
      ) : null}

      {borrar ? (
        <Modal
          titulo={'Eliminar ' + borrar.codigo}
          angosto
          onCerrar={() => setBorrar(null)}
          pie={
            <>
              <Boton onClick={() => setBorrar(null)}>Cancelar</Boton>
              <Boton variante="peligro" onClick={eliminar}>Eliminar</Boton>
            </>
          }
        >
          <Aviso tipo="aviso-amarillo">
            La promoción se envía a la papelera con borrado lógico: el registro se conserva
            en la base de datos y el historial de descuentos aplicados se mantiene intacto.
          </Aviso>
        </Modal>
      ) : null}
    </>
  );
}

function Historial() {
  const lista = useListado('/descuentos/historial', { desde: primerDiaDelMes(), hasta: hoy(), codigo: '' });
  const registros = Array.isArray(lista.respuesta) ? lista.respuesta : lista.items;
  const totalDescontado = registros.reduce((a, d) => a + d.valor_descuento, 0);

  return (
    <>
      <div className="rejilla-metricas">
        <Metrica etiqueta="Aplicaciones" valor={String(registros.length)} />
        <Metrica etiqueta="Total descontado" valor={dinero(totalDescontado)} tono="amarillo" />
        <Metrica
          etiqueta="Descuento promedio"
          valor={dinero(registros.length ? totalDescontado / registros.length : 0)}
          tono="info"
        />
      </div>

      <div className="filtros">
        <Entrada etiqueta="Desde" type="date" value={lista.filtros.desde}
          onChange={(e) => lista.cambiarFiltro('desde', e.target.value)} />
        <Entrada etiqueta="Hasta" type="date" value={lista.filtros.hasta}
          onChange={(e) => lista.cambiarFiltro('hasta', e.target.value)} />
        <Entrada etiqueta="Codigo" value={lista.filtros.codigo}
          onChange={(e) => lista.cambiarFiltro('codigo', e.target.value.toUpperCase())} />
        <Boton onClick={() => descargar(
          '/descuentos/exportar' + consulta({ formato: 'pdf', desde: lista.filtros.desde, hasta: lista.filtros.hasta }),
          'descuentos.pdf'
        )}>PDF</Boton>
        <Boton onClick={() => descargar(
          '/descuentos/exportar' + consulta({ formato: 'csv', desde: lista.filtros.desde, hasta: lista.filtros.hasta }),
          'descuentos.csv'
        )}>CSV</Boton>
      </div>

      <Tarjeta plana>
        {lista.cargando ? <Cargando /> : (
          <Tabla
            columnas={[
              { texto: 'Fecha' }, { texto: 'Venta' }, { texto: 'Código' }, { texto: 'Motivo' },
              { texto: 'Valor original', derecha: true }, { texto: 'Descuento', derecha: true },
              { texto: 'Valor final', derecha: true }, { texto: 'Autorizo' },
            ]}
            filas={registros}
            clave={(d) => d.id}
            vacio="No se aplicaron descuentos en el periodo."
            render={(d) => (
              <>
                <td className="tenue">{fechaHora(d.fecha)}</td>
                <td className="tabular">{d.folio}</td>
                <td className="principal tabular">{d.codigo}</td>
                <td className="tenue">{d.motivo}</td>
                <td className="derecha tabular">{dinero(d.valor_original)}</td>
                <td className="derecha tabular" style={{ color: 'var(--exito)' }}>- {dinero(d.valor_descuento)}</td>
                <td className="derecha tabular principal">{dinero(d.valor_final)}</td>
                <td className="tenue">{d.autorizado_por || 'Automatico'}</td>
              </>
            )}
          />
        )}
      </Tarjeta>
    </>
  );
}

export default function Descuentos() {
  const { puede } = useSesion();
  const [pestana, setPestana] = useState('promociones');

  const opciones = [{ id: 'promociones', texto: 'Promociones' }];
  if (puede('descuentos.gestionar')) opciones.push({ id: 'historial', texto: 'Auditoría de descuentos' });

  return (
    <>
      <div className="encabezado">
        <div>
          <h1>Descuentos y promociones</h1>
          <p>Cada descuento queda registrado con su motivo y el valor antes y después.</p>
        </div>
      </div>

      <div className="panel">
        <Pestanas opciones={opciones} activa={pestana} onCambiar={setPestana} />
        {pestana === 'promociones' ? <Promociones /> : <Historial />}
      </div>
    </>
  );
}
