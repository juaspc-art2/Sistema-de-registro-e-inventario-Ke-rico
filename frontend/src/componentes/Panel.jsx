import { useEffect, useState } from 'react';
import { api, dinero, fechaCorta, porcentaje } from '../api.js';
import { useSesion } from '../contexto/Sesion.jsx';
import {
  Anillo, Aviso, Barras, Boton, Cargando, Columnas, EtiquetaEstado, Metrica, Tabla, Tarjeta,
} from '../ui/Componentes.jsx';

const DIAS = ['dom', 'lun', 'mar', 'mie', 'jue', 'vie', 'sab'];

export default function Panel({ onAlertasPendientes, irA }) {
  const { usuario, puede } = useSesion();
  const [datos, setDatos] = useState(null);
  const [error, setError] = useState('');

  useEffect(() => {
    let vigente = true;
    api.get('/reportes/panel')
      .then((d) => {
        if (!vigente) return;
        setDatos(d);
        if (onAlertasPendientes) onAlertasPendientes(d.alertas.pendientes || 0);
      })
      .catch((e) => vigente && setError(e.message));
    return () => { vigente = false; };
  }, [onAlertasPendientes]);

  if (error) {
    return (
      <>
        <div className="encabezado"><div><h1>Panel principal</h1></div></div>
        <div className="panel"><Aviso tipo="error">{error}</Aviso></div>
      </>
    );
  }

  if (!datos) {
    return (
      <>
        <div className="encabezado"><div><h1>Panel principal</h1></div></div>
        <div className="panel"><Cargando /></div>
      </>
    );
  }

  const { ventas_hoy: hoy, variacion_ayer: variacion, alertas, movimientos_hoy: movs, mes, semana, top_productos: top, ultimas_ventas: ultimas } = datos;

  const serieSemana = semana.map((d) => {
    const fecha = new Date(d.dia + 'T00:00:00');
    return { etiqueta: DIAS[fecha.getDay()] + ' ' + fecha.getDate(), valor: d.total };
  });

  const distribucion = [
    { etiqueta: 'Disponible', valor: alertas.Disponible || 0, color: 'var(--exito)' },
    { etiqueta: 'Stock bajo', valor: alertas.Bajo || 0, color: 'var(--aviso)' },
    { etiqueta: 'Crítico', valor: alertas.Critico || 0, color: 'var(--naranja)' },
    { etiqueta: 'Agotado', valor: alertas.Agotado || 0, color: 'var(--error)' },
  ];
  const totalReferencias = distribucion.reduce((a, d) => a + d.valor, 0);

  const topBarras = top.map((p) => ({ etiqueta: p.nombre, valor: p.unidades }));

  return (
    <>
      <div className="encabezado">
        <div>
          <h1>Panel principal</h1>
          <p>Hola {usuario.nombre}, este es el estado del negocio hoy.</p>
        </div>
        <div className="acciones">
          {puede('ventas.registrar') ? (
            <Boton variante="primario" onClick={() => irA('ventas')}>+ Nueva venta</Boton>
          ) : null}
          {puede('productos.gestionar') ? (
            <Boton onClick={() => irA('inventario')}>+ Nuevo producto</Boton>
          ) : null}
          {puede('compras.registrar') ? (
            <Boton onClick={() => irA('proveedores')}>+ Registrar compra</Boton>
          ) : null}
          {puede('reportes.ver') ? (
            <Boton variante="sutil" onClick={() => irA('rentabilidad')}>Ver reportes</Boton>
          ) : null}
        </div>
      </div>

      <div className="panel">
        <div className="rejilla-metricas">
          <Metrica
            etiqueta="Ventas de hoy"
            valor={dinero(hoy.total)}
            nota={variacion === 0 ? 'Sin variacion frente a ayer' : (variacion > 0 ? '+' : '') + variacion + ' % vs. ayer'}
            tendencia={variacion > 0 ? 'sube' : variacion < 0 ? 'baja' : undefined}
          />
          <Metrica
            etiqueta="Transacciones"
            valor={String(hoy.transacciones)}
            nota={hoy.unidades + ' unidades vendidas'}
            tono="amarillo"
          />
          <Metrica
            etiqueta="Referencias en alerta"
            valor={String((alertas.Agotado || 0) + (alertas.Critico || 0) + (alertas.Bajo || 0))}
            nota={(alertas.Agotado || 0) + ' agotadas, ' + (alertas.Critico || 0) + ' críticas'}
            tono={alertas.Agotado > 0 ? 'error' : 'amarillo'}
          />
          <Metrica
            etiqueta="Margen del mes"
            valor={porcentaje(mes.margen)}
            nota={'Utilidad ' + dinero(mes.utilidad_bruta)}
            tono="exito"
            tendencia={mes.margen > 0 ? 'sube' : undefined}
          />
          <Metrica
            etiqueta="Movimientos de hoy"
            valor={String(movs.total)}
            nota={movs.entradas + ' entradas, ' + movs.salidas + ' salidas'}
            tono="info"
          />
          <Metrica
            etiqueta="Inventario valorizado"
            valor={dinero(alertas.valor_inventario)}
            nota={totalReferencias + ' referencias activas'}
            tono="neutro"
          />
        </div>

        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(320px, 1fr))', gap: 20 }}>
          <Tarjeta titulo="Ventas de la última semana" sub="Total facturado por día">
            {serieSemana.length === 0 ? (
              <Aviso tipo="info">Todavía no hay ventas registradas en la última semana.</Aviso>
            ) : (
              <Columnas datos={serieSemana} formato={(v) => dinero(v).replace('$', '')} />
            )}
          </Tarjeta>

          <Tarjeta titulo="Estado del inventario" sub="Distribución de referencias">
            <Anillo
              datos={distribucion}
              centroValor={String(totalReferencias)}
              centroTitulo="referencias"
            />
          </Tarjeta>
        </div>

        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(320px, 1fr))', gap: 20 }}>
          <Tarjeta titulo="Productos más vendidos" sub="Unidades del mes en curso">
            {topBarras.length === 0 ? (
              <Aviso tipo="info">Aún no hay ventas registradas este mes.</Aviso>
            ) : (
              <Barras datos={topBarras} formato={(v) => v + ' u.'} />
            )}
          </Tarjeta>

          <Tarjeta titulo="Resumen financiero del mes" sub={'Del 1 al ' + fechaCorta(new Date().toISOString())}>
            <div className="totales">
              <div className="fila"><span>Ingresos brutos</span><span className="tabular">{dinero(mes.ingresos_brutos)}</span></div>
              <div className="fila"><span>Base gravable</span><span className="tabular">{dinero(mes.base_gravable)}</span></div>
              <div className="fila"><span>Impuestos</span><span className="tabular">{dinero(mes.impuestos)}</span></div>
              <div className="fila descuento"><span>Descuentos aplicados</span><span className="tabular">- {dinero(mes.descuentos)}</span></div>
              <div className="fila"><span>Costo de ventas</span><span className="tabular">{dinero(mes.costo_ventas)}</span></div>
              <div className="fila"><span>Compras del periodo</span><span className="tabular">{dinero(mes.compras)}</span></div>
              <div className="fila"><span>Ticket promedio</span><span className="tabular">{dinero(mes.ticket_promedio)}</span></div>
              <div className="fila grande"><span>Utilidad bruta</span><span className="tabular">{dinero(mes.utilidad_bruta)}</span></div>
            </div>
          </Tarjeta>
        </div>

        <Tarjeta
          titulo="Últimas ventas"
          plana
          acciones={puede('ventas.ver') ? <Boton pequeno onClick={() => irA('ventas')}>Ver todas</Boton> : null}
        >
          <Tabla
            columnas={[
              { texto: 'Folio' },
              { texto: 'Fecha' },
              { texto: 'Cajero' },
              { texto: 'Cliente' },
              { texto: 'Total', derecha: true },
              { texto: 'Estado' },
            ]}
            filas={ultimas}
            clave={(v) => v.id}
            vacio="Todavía no se han registrado ventas."
            render={(v) => (
              <>
                <td className="principal tabular">{v.folio}</td>
                <td className="tenue">{fechaCorta(v.fecha)}</td>
                <td>{v.cajero}</td>
                <td className="tenue">{v.cliente}</td>
                <td className="derecha tabular principal">{dinero(v.total)}</td>
                <td><EtiquetaEstado valor={v.estado} /></td>
              </>
            )}
          />
        </Tarjeta>
      </div>
    </>
  );
}
