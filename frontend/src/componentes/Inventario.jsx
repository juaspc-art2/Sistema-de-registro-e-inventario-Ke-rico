import { useEffect, useState } from 'react';
import { api, cantidad, descargar, dinero, fechaHora } from '../api.js';
import { useSesion } from '../contexto/Sesion.jsx';
import { useListado } from '../ganchos/useListado.js';
import {
  Aviso, Boton, Cargando, EncabezadoOrden, Entrada, EtiquetaEstado, Metrica, Modal,
  Paginacion, Pestanas, Seleccion, Tabla, Tarjeta,
  opcionesEstado,
} from '../ui/Componentes.jsx';

const VACIO = {
  sku: '', nombre: '', descripcion: '', categoria_id: '', proveedor_id: '',
  unidad_medida: 'unidad', precio_costo: '', precio_venta: '', iva_porcentaje: '8',
  punto_reorden: '', stock_maximo: '', stock_inicial: '', es_insumo: false, imagen: '', activo: true,
};

function Formulario({ producto, categorias, proveedores, onCerrar, onGuardado, avisar, puedePrecio }) {
  const [datos, setDatos] = useState(() => {
    if (!producto) return { ...VACIO };
    const cat = categorias.find((c) => c.nombre === producto.categoria);
    const prov = proveedores.find((p) => p.nombre_empresa === producto.proveedor);
    return {
      sku: producto.sku,
      nombre: producto.nombre,
      descripcion: producto.descripcion || '',
      categoria_id: cat ? String(cat.id) : '',
      proveedor_id: prov ? String(prov.id) : '',
      unidad_medida: producto.unidad_medida,
      precio_costo: String(producto.precio_costo),
      precio_venta: String(producto.precio_venta),
      iva_porcentaje: String(producto.iva_porcentaje),
      punto_reorden: String(producto.punto_reorden),
      stock_maximo: String(producto.stock_maximo),
      es_insumo: producto.es_insumo,
      imagen: producto.imagen || '',
      activo: producto.activo,
    };
  });
  const [errores, setErrores] = useState({});
  const [error, setError] = useState('');
  const [guardando, setGuardando] = useState(false);

  const poner = (clave, valor) => setDatos((d) => ({ ...d, [clave]: valor }));

  const validar = () => {
    const e = {};
    if (!datos.sku.trim()) e.sku = 'El código SKU es obligatorio';
    if (!datos.nombre.trim()) e.nombre = 'El nombre es obligatorio';
    if (!datos.categoria_id) e.categoria_id = 'Seleccione la categoría';
    if (datos.precio_costo === '' || Number(datos.precio_costo) < 0) e.precio_costo = 'Indique el costo';
    if (!datos.es_insumo && (datos.precio_venta === '' || Number(datos.precio_venta) <= 0)) {
      e.precio_venta = 'Un producto de venta necesita precio mayor que cero';
    }
    if (datos.iva_porcentaje !== '' && (Number(datos.iva_porcentaje) < 0 || Number(datos.iva_porcentaje) > 100)) {
      e.iva_porcentaje = 'El impuesto va de 0 a 100';
    }
    if (datos.punto_reorden !== '' && Number(datos.punto_reorden) < 0) e.punto_reorden = 'No puede ser negativo';
    setErrores(e);
    return Object.keys(e).length === 0;
  };

  const guardar = async () => {
    setError('');
    if (!validar()) return;
    setGuardando(true);
    try {
      const cuerpo = {
        sku: datos.sku.trim().toUpperCase(),
        nombre: datos.nombre.trim(),
        descripcion: datos.descripcion,
        categoria_id: Number(datos.categoria_id),
        proveedor_id: datos.proveedor_id ? Number(datos.proveedor_id) : null,
        unidad_medida: datos.unidad_medida || 'unidad',
        precio_costo: Number(datos.precio_costo),
        precio_venta: datos.es_insumo ? 0 : Number(datos.precio_venta),
        iva_porcentaje: datos.iva_porcentaje === '' ? 0 : Number(datos.iva_porcentaje),
        punto_reorden: datos.punto_reorden === '' ? 0 : Number(datos.punto_reorden),
        stock_maximo: datos.stock_maximo === '' ? 0 : Number(datos.stock_maximo),
        es_insumo: datos.es_insumo,
        imagen: datos.imagen,
        activo: datos.activo,
      };
      if (producto) {
        await api.put('/inventario/productos/' + producto.id, cuerpo);
        avisar('Producto actualizado');
      } else {
        cuerpo.stock_inicial = datos.stock_inicial === '' ? 0 : Number(datos.stock_inicial);
        await api.post('/inventario/productos', cuerpo);
        avisar('Producto creado');
      }
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
      titulo={producto ? 'Editar ' + producto.nombre : 'Nuevo producto'}
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
      {producto && !puedePrecio ? (
        <Aviso tipo="aviso-amarillo">
          Su perfil no puede modificar precios. Los campos de costo y venta están bloqueados.
        </Aviso>
      ) : null}

      <div className="rejilla-formulario">
        <Entrada etiqueta="Código SKU" value={datos.sku}
          onChange={(e) => poner('sku', e.target.value.toUpperCase())} error={errores.sku} />
        <Entrada etiqueta="Nombre" value={datos.nombre}
          onChange={(e) => poner('nombre', e.target.value)} error={errores.nombre} />
        <Seleccion etiqueta="Categoría" value={datos.categoria_id}
          onChange={(e) => poner('categoria_id', e.target.value)} error={errores.categoria_id}
          vacio="Seleccione" opciones={categorias.map((c) => ({ valor: c.id, texto: c.nombre }))} />
        <Seleccion etiqueta="Proveedor" value={datos.proveedor_id}
          onChange={(e) => poner('proveedor_id', e.target.value)}
          vacio="Sin proveedor" opciones={proveedores.map((p) => ({ valor: p.id, texto: p.nombre_empresa }))} />
        <Entrada etiqueta="Unidad de medida" value={datos.unidad_medida}
          onChange={(e) => poner('unidad_medida', e.target.value)} />
        <Entrada etiqueta="Precio de costo" type="number" min="0" value={datos.precio_costo}
          onChange={(e) => poner('precio_costo', e.target.value)} error={errores.precio_costo}
          disabled={producto && !puedePrecio} />
        <Entrada etiqueta="Precio de venta" type="number" min="0" value={datos.precio_venta}
          onChange={(e) => poner('precio_venta', e.target.value)} error={errores.precio_venta}
          disabled={(producto && !puedePrecio) || datos.es_insumo} />
        <Entrada etiqueta="Impuesto (%)" type="number" min="0" max="100" value={datos.iva_porcentaje}
          onChange={(e) => poner('iva_porcentaje', e.target.value)} error={errores.iva_porcentaje} />
        <Entrada etiqueta="Punto de reorden" type="number" min="0" value={datos.punto_reorden}
          onChange={(e) => poner('punto_reorden', e.target.value)} error={errores.punto_reorden} />
        <Entrada etiqueta="Stock máximo" type="number" min="0" value={datos.stock_maximo}
          onChange={(e) => poner('stock_maximo', e.target.value)} />
        {!producto ? (
          <Entrada etiqueta="Existencias iniciales" type="number" min="0" value={datos.stock_inicial}
            onChange={(e) => poner('stock_inicial', e.target.value)} />
        ) : null}
        <Entrada etiqueta="Ruta de imagen" value={datos.imagen}
          onChange={(e) => poner('imagen', e.target.value)}
          placeholder="marca/productos/empanaditas.jpg" />
      </div>

      <Entrada etiqueta="Descripción" value={datos.descripcion}
        onChange={(e) => poner('descripcion', e.target.value)} ancho />

      <label style={{ display: 'flex', gap: 8, alignItems: 'center', fontSize: 14 }}>
        <input type="checkbox" checked={datos.es_insumo} onChange={(e) => poner('es_insumo', e.target.checked)} />
        Es un insumo de cocina (no se vende directamente al público)
      </label>

      <label style={{ display: 'flex', gap: 8, alignItems: 'center', fontSize: 14 }}>
        <input type="checkbox" checked={datos.activo} onChange={(e) => poner('activo', e.target.checked)} />
        Producto activo
      </label>
    </Modal>
  );
}

function Papelera({ onCerrar, avisar, onRestaurado }) {
  const [items, setItems] = useState(null);

  useEffect(() => {
    api.get('/inventario/papelera').then(setItems).catch(() => setItems([]));
  }, []);

  const restaurar = async (id) => {
    try {
      await api.post('/inventario/productos/' + id + '/restaurar', {});
      avisar('Producto restaurado');
      setItems((actual) => actual.filter((p) => p.id !== id));
      onRestaurado();
    } catch (e) {
      avisar(e.message, 'error');
    }
  };

  return (
    <Modal titulo="Papelera de productos" onCerrar={onCerrar}>
      <Aviso tipo="info">
        Los productos eliminados conservan su historial. Restaurarlos los devuelve al
        inventario con las mismas existencias.
      </Aviso>
      {items === null ? <Cargando /> : (
        <Tabla
          columnas={[{ texto: 'Código' }, { texto: 'Producto' }, { texto: 'Categoría' }, { texto: 'Eliminado' }, { texto: '' }]}
          filas={items}
          clave={(p) => p.id}
          vacio="La papelera está vacía."
          render={(p) => (
            <>
              <td className="tabular tenue">{p.sku}</td>
              <td className="principal">{p.nombre}</td>
              <td className="tenue">{p.categoria}</td>
              <td className="tenue">{fechaHora(p.deleted_at)}</td>
              <td style={{ textAlign: 'right' }}>
                <Boton pequeno onClick={() => restaurar(p.id)}>Restaurar</Boton>
              </td>
            </>
          )}
        />
      )}
    </Modal>
  );
}

export default function Inventario() {
  const { puede, avisar } = useSesion();
  const lista = useListado(
    '/inventario/productos',
    { busqueda: '', categoria_id: '', estado: '', activo: '' },
    { ordenInicial: 'nombre', direccionInicial: 'ASC' }
  );
  const [categorias, setCategorias] = useState([]);
  const [proveedores, setProveedores] = useState([]);
  const [editando, setEditando] = useState(undefined);
  const [borrar, setBorrar] = useState(null);
  const [papelera, setPapelera] = useState(false);
  const [pestana, setPestana] = useState('productos');

  useEffect(() => {
    api.get('/inventario/categorias').then(setCategorias).catch(() => setCategorias([]));
    if (puede('proveedores.ver')) {
      api.get('/proveedores?por_pagina=200').then((r) => setProveedores(r.items || [])).catch(() => setProveedores([]));
    }
  }, [puede]);

  const eliminar = async () => {
    try {
      await api.del('/inventario/productos/' + borrar.id);
      avisar('Producto ' + borrar.nombre + ' enviado a la papelera');
      setBorrar(null);
      lista.recargar();
    } catch (e) {
      avisar(e.message, 'error');
    }
  };

  const soloInsumos = pestana === 'insumos';
  const visibles = lista.items.filter((p) => (soloInsumos ? p.es_insumo : !p.es_insumo));
  const valorTotal = lista.items.reduce((a, p) => a + p.valor_inventario, 0);

  return (
    <>
      <div className="encabezado">
        <div>
          <h1>Inventario</h1>
          <p>Catálogo de productos e insumos con control de existencias.</p>
        </div>
        <div className="acciones">
          {puede('reportes.exportar') ? (
            <>
              <Boton onClick={() => descargar('/inventario/exportar?formato=pdf', 'inventario.pdf')}>PDF</Boton>
              <Boton onClick={() => descargar('/inventario/exportar?formato=csv', 'inventario.csv')}>CSV</Boton>
              <Boton onClick={() => descargar('/inventario/exportar?formato=excel', 'inventario.xlsx')}>Excel</Boton>
            </>
          ) : null}
          {puede('productos.gestionar') ? (
            <>
              <Boton onClick={() => setPapelera(true)}>Papelera</Boton>
              <Boton variante="primario" onClick={() => setEditando(null)}>+ Nuevo producto</Boton>
            </>
          ) : null}
        </div>
      </div>

      <div className="panel">
        <div className="rejilla-metricas">
          <Metrica etiqueta="Referencias" valor={String(lista.total)} />
          <Metrica etiqueta="Valor en pantalla" valor={dinero(valorTotal)} tono="amarillo" />
          <Metrica etiqueta="Categorías" valor={String(categorias.length)} tono="info" />
          <Metrica
            etiqueta="En alerta"
            valor={String(lista.items.filter((p) => p.estado_stock !== 'Disponible').length)}
            tono="error"
          />
        </div>

        <Pestanas
          opciones={[{ id: 'productos', texto: 'Productos de venta' }, { id: 'insumos', texto: 'Insumos de cocina' }]}
          activa={pestana}
          onCambiar={setPestana}
        />

        <div className="filtros">
          <Entrada etiqueta="Buscar" placeholder="Nombre, SKU o categoría" value={lista.filtros.busqueda}
            onChange={(e) => lista.cambiarFiltro('busqueda', e.target.value)} />
          <Seleccion etiqueta="Categoría" value={lista.filtros.categoria_id}
            onChange={(e) => lista.cambiarFiltro('categoria_id', e.target.value)} vacio="Todas"
            opciones={categorias.map((c) => ({ valor: c.id, texto: c.nombre }))} />
          <Seleccion etiqueta="Estado de stock" value={lista.filtros.estado}
            onChange={(e) => lista.cambiarFiltro('estado', e.target.value)} vacio="Todos"
            opciones={opcionesEstado(['Disponible', 'Bajo', 'Critico', 'Agotado'])} />
          <Seleccion etiqueta="Visibilidad" value={lista.filtros.activo}
            onChange={(e) => lista.cambiarFiltro('activo', e.target.value)} vacio="Todos"
            opciones={[{ valor: '1', texto: 'Activos' }, { valor: '0', texto: 'Inactivos' }]} />
        </div>

        <Tarjeta plana>
          {lista.error ? <div style={{ padding: 16 }}><Aviso tipo="error">{lista.error}</Aviso></div> : null}
          {lista.cargando ? <Cargando /> : (
            <>
              <div className="tabla-envoltura">
                <table className="tabla">
                  <thead>
                    <tr>
                      <EncabezadoOrden texto="Código" campo="sku" orden={lista.orden} direccion={lista.direccion} onOrdenar={lista.ordenar} />
                      <EncabezadoOrden texto="Producto" campo="nombre" orden={lista.orden} direccion={lista.direccion} onOrdenar={lista.ordenar} />
                      <EncabezadoOrden texto="Categoría" campo="categoria" orden={lista.orden} direccion={lista.direccion} onOrdenar={lista.ordenar} />
                      <th>Proveedor</th>
                      <EncabezadoOrden texto="Stock" campo="stock" orden={lista.orden} direccion={lista.direccion} onOrdenar={lista.ordenar} derecha />
                      <th className="derecha">Reorden</th>
                      <EncabezadoOrden texto="Costo" campo="precio_costo" orden={lista.orden} direccion={lista.direccion} onOrdenar={lista.ordenar} derecha />
                      <EncabezadoOrden texto="Venta" campo="precio_venta" orden={lista.orden} direccion={lista.direccion} onOrdenar={lista.ordenar} derecha />
                      <EncabezadoOrden texto="Estado" campo="estado" orden={lista.orden} direccion={lista.direccion} onOrdenar={lista.ordenar} />
                      <th />
                    </tr>
                  </thead>
                  <tbody>
                    {visibles.length === 0 ? (
                      <tr><td colSpan={10}><div className="vacio">Ningún registro coincide con los filtros.</div></td></tr>
                    ) : visibles.map((p) => (
                      <tr key={p.id} style={p.activo ? undefined : { opacity: 0.55 }}>
                        <td className="tabular tenue">{p.sku}</td>
                        <td className="principal">{p.nombre}</td>
                        <td className="tenue">{p.categoria}</td>
                        <td className="tenue">{p.proveedor || '—'}</td>
                        <td className="derecha tabular principal">{cantidad(p.stock_actual)} {p.unidad_medida}</td>
                        <td className="derecha tabular tenue">{cantidad(p.punto_reorden)}</td>
                        <td className="derecha tabular">{dinero(p.precio_costo)}</td>
                        <td className="derecha tabular">{p.es_insumo ? '—' : dinero(p.precio_venta)}</td>
                        <td><EtiquetaEstado valor={p.estado_stock} texto={p.estado_stock === 'Bajo' ? 'Stock bajo' : p.estado_stock} /></td>
                        <td style={{ textAlign: 'right', whiteSpace: 'nowrap' }}>
                          {puede('productos.gestionar') ? (
                            <>
                              <Boton pequeno onClick={() => setEditando(p)}>Editar</Boton>
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
      </div>

      {editando !== undefined ? (
        <Formulario
          producto={editando}
          categorias={categorias}
          proveedores={proveedores}
          avisar={avisar}
          puedePrecio={puede('productos.precio')}
          onCerrar={() => setEditando(undefined)}
          onGuardado={lista.recargar}
        />
      ) : null}

      {borrar ? (
        <Modal
          titulo={'Eliminar ' + borrar.nombre}
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
            El producto se envía a la papelera. La fila permanece en la base de datos con
            su fecha de eliminación, y el historial de ventas y movimientos se conserva.
          </Aviso>
        </Modal>
      ) : null}

      {papelera ? (
        <Papelera onCerrar={() => setPapelera(false)} avisar={avisar} onRestaurado={lista.recargar} />
      ) : null}
    </>
  );
}
