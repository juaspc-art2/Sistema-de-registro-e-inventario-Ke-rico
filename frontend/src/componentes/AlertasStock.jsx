import { useCallback, useEffect, useState } from 'react';
import { api, cantidad, dinero, fechaHora } from '../api.js';
import { useSesion } from '../contexto/Sesion.jsx';
import {
  Anillo, Aviso, Boton, Cargando, Entrada, EtiquetaEstado, Metrica, Modal, nombreEstado, Tabla, Tarjeta,
} from '../ui/Componentes.jsx';

const COLORES = {
  Agotado: 'var(--error)',
  Critico: 'var(--naranja)',
  Bajo: 'var(--aviso)',
  Disponible: 'var(--exito)',
};

export default function AlertasStock({ onAlertasPendientes }) {
  const { puede, avisar } = useSesion();
  const [datos, setDatos] = useState(null);
  const [error, setError] = useState('');
  const [filtro, setFiltro] = useState('');
  const [ajustando, setAjustando] = useState(null);
  const [nuevoReorden, setNuevoReorden] = useState('');
  const [errorReorden, setErrorReorden] = useState('');

  const cargar = useCallback(() => {
    api.get('/inventario/alertas')
      .then((d) => {
        setDatos(d);
        if (onAlertasPendientes) onAlertasPendientes(d.resumen.pendientes || 0);
      })
      .catch((e) => setError(e.message));
  }, [onAlertasPendientes]);

  useEffect(() => { cargar(); }, [cargar]);

  const marcarTodas = async () => {
    try {
      const r = await api.post('/inventario/alertas/vistas', {});
      avisar(r.actualizadas + ' alerta(s) marcadas como vistas');
      cargar();
    } catch (e) {
      avisar(e.message, 'error');
    }
  };

  const marcarUna = async (id) => {
    try {
      await api.post('/inventario/alertas/' + id + '/vista', {});
      cargar();
    } catch (e) {
      avisar(e.message, 'error');
    }
  };

  const recalcular = async () => {
    try {
      const r = await api.post('/inventario/alertas/recalcular', {});
      avisar(r.productos_evaluados + ' productos evaluados');
      cargar();
    } catch (e) {
      avisar(e.message, 'error');
    }
  };

  const guardarReorden = async () => {
    setErrorReorden('');
    const valor = Number(nuevoReorden);
    if (nuevoReorden === '' || Number.isNaN(valor) || valor < 0) {
      setErrorReorden('Escriba un número mayor o igual a cero.');
      return;
    }
    try {
      await api.put('/inventario/productos/' + ajustando.producto_id + '/reorden', { punto_reorden: valor });
      avisar('Punto de reorden de ' + ajustando.producto + ' actualizado');
      setAjustando(null);
      cargar();
    } catch (e) {
      setErrorReorden(e.message);
    }
  };

  if (error) {
    return (
      <>
        <div className="encabezado"><div><h1>Alertas de stock</h1></div></div>
        <div className="panel"><Aviso tipo="error">{error}</Aviso></div>
      </>
    );
  }

  if (!datos) {
    return (
      <>
        <div className="encabezado"><div><h1>Alertas de stock</h1></div></div>
        <div className="panel"><Cargando /></div>
      </>
    );
  }

  const { resumen, alertas } = datos;
  const visibles = filtro ? alertas.filter((a) => a.tipo === filtro) : alertas;
  const distribucion = ['Agotado', 'Critico', 'Bajo', 'Disponible'].map((k) => ({
    etiqueta: nombreEstado(k),
    valor: resumen[k] || 0,
    color: COLORES[k],
  }));

  return (
    <>
      <div className="encabezado">
        <div>
          <h1>Alertas de stock</h1>
          <p>El sistema vigila cada referencia frente a su punto de reorden.</p>
        </div>
        <div className="acciones">
          {puede('alertas.configurar') ? <Boton onClick={recalcular}>Recalcular</Boton> : null}
          {resumen.pendientes > 0 ? (
            <Boton variante="primario" onClick={marcarTodas}>
              Marcar {resumen.pendientes} como vistas
            </Boton>
          ) : null}
        </div>
      </div>

      <div className="panel">
        <div className="rejilla-metricas">
          {['Agotado', 'Critico', 'Bajo', 'Disponible'].map((k) => (
            <button
              key={k}
              type="button"
              onClick={() => setFiltro(filtro === k ? '' : (k === 'Disponible' ? '' : k))}
              style={{ border: 'none', background: 'transparent', padding: 0, textAlign: 'left', cursor: 'pointer' }}
            >
              <Metrica
                etiqueta={k === 'Disponible' ? 'En nivel normal' : nombreEstado(k)}
                valor={String(resumen[k] || 0)}
                nota={filtro === k ? 'Filtro activo, toque para quitar' : 'Referencias'}
                tono={k === 'Agotado' ? 'error' : k === 'Critico' ? 'naranja' : k === 'Bajo' ? 'amarillo' : 'exito'}
              />
            </button>
          ))}
          <Metrica etiqueta="Inventario valorizado" valor={dinero(resumen.valor_inventario)} tono="neutro" />
          <Metrica etiqueta="Alertas sin revisar" valor={String(resumen.pendientes)} tono="info" />
        </div>

        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(300px, 1fr))', gap: 20 }}>
          <Tarjeta titulo="Distribución del inventario" sub="Referencias activas por estado">
            <Anillo
              datos={distribucion}
              centroValor={String(distribucion.reduce((a, d) => a + d.valor, 0))}
              centroTitulo="referencias"
            />
          </Tarjeta>

          <Tarjeta titulo="Cómo funciona" sub="Regla de evaluación">
            <ul style={{ margin: 0, paddingLeft: 18, fontSize: 13.5, color: 'var(--cacao)', lineHeight: 1.9 }}>
              <li><strong>Agotado:</strong> la referencia llegó a cero unidades.</li>
              <li><strong>Crítico:</strong> el stock cayó a la mitad del punto de reorden o menos.</li>
              <li><strong>Stock bajo:</strong> el stock está en el punto de reorden o por debajo.</li>
              <li><strong>Normal:</strong> hay existencias por encima del punto de reorden.</li>
            </ul>
            <p style={{ fontSize: 13, color: 'var(--piedra)', marginTop: 12 }}>
              Cada venta, compra o ajuste vuelve a evaluar la referencia y genera o resuelve
              la alerta automáticamente.
            </p>
          </Tarjeta>
        </div>

        <Tarjeta
          titulo={filtro ? 'Alertas: ' + nombreEstado(filtro) : 'Alertas activas'}
          sub={visibles.length + ' referencia(s)'}
          plana
          acciones={filtro ? <Boton pequeno onClick={() => setFiltro('')}>Quitar filtro</Boton> : null}
        >
          <Tabla
            columnas={[
              { texto: 'Producto' }, { texto: 'Categoría' }, { texto: 'Proveedor' },
              { texto: 'Stock', derecha: true }, { texto: 'Reorden', derecha: true },
              { texto: 'Estado' }, { texto: 'Generada' }, { texto: 'Revisión' }, { texto: '' },
            ]}
            filas={visibles}
            clave={(a) => a.id}
            vacio="Ninguna referencia está por debajo de su punto de reorden."
            render={(a) => (
              <>
                <td>
                  <span className="principal">{a.producto}</span>
                  <br /><span className="tenue tabular">{a.sku}</span>
                </td>
                <td className="tenue">{a.categoria}</td>
                <td className="tenue">{a.proveedor || 'Sin proveedor'}</td>
                <td className="derecha tabular principal">{cantidad(a.stock_actual)} {a.unidad_medida}</td>
                <td className="derecha tabular tenue">{cantidad(a.punto_reorden)}</td>
                <td><EtiquetaEstado valor={a.tipo} texto={a.tipo === 'Bajo' ? 'Stock bajo' : a.tipo} /></td>
                <td className="tenue">{fechaHora(a.generada_en)}</td>
                <td>
                  {a.estado === 'Pendiente'
                    ? <EtiquetaEstado valor="Pendiente" texto="Sin revisar" />
                    : <span className="tenue">{a.visto_por || 'Revisada'}</span>}
                </td>
                <td style={{ textAlign: 'right', whiteSpace: 'nowrap' }}>
                  {a.estado === 'Pendiente' ? (
                    <Boton pequeno onClick={() => marcarUna(a.id)}>Revisar</Boton>
                  ) : null}
                  {puede('alertas.configurar') ? (
                    <Boton pequeno onClick={() => { setAjustando(a); setNuevoReorden(String(a.punto_reorden)); setErrorReorden(''); }}>
                      Reorden
                    </Boton>
                  ) : null}
                </td>
              </>
            )}
          />
        </Tarjeta>
      </div>

      {ajustando ? (
        <Modal
          titulo={'Punto de reorden de ' + ajustando.producto}
          angosto
          onCerrar={() => setAjustando(null)}
          pie={
            <>
              <Boton onClick={() => setAjustando(null)}>Cancelar</Boton>
              <Boton variante="primario" onClick={guardarReorden}>Guardar</Boton>
            </>
          }
        >
          <Aviso tipo="info">
            Stock actual: {cantidad(ajustando.stock_actual)} {ajustando.unidad_medida}.
            El sistema avisará cuando baje de este límite.
          </Aviso>
          <Entrada
            etiqueta={'Punto de reorden en ' + ajustando.unidad_medida}
            type="number"
            min="0"
            value={nuevoReorden}
            onChange={(e) => setNuevoReorden(e.target.value)}
            error={errorReorden}
          />
        </Modal>
      ) : null}
    </>
  );
}
