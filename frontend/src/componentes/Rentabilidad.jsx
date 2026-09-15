import { useEffect, useState } from 'react';
import { api, cantidad, consulta, descargar, dinero, fechaCorta, hoy, porcentaje, primerDiaDelMes } from '../api.js';
import { useSesion } from '../contexto/Sesion.jsx';
import {
  Anillo, Aviso, Barras, Boton, Cargando, Columnas, Entrada, Metrica, Tabla, Tarjeta,
} from '../ui/Componentes.jsx';

const PALETA = ['#eb5e15', '#edca34', '#2e7d32', '#00629b', '#8d7166', '#c94a08'];

export default function Rentabilidad() {
  const { puede } = useSesion();
  const [rango, setRango] = useState({ desde: primerDiaDelMes(), hasta: hoy() });
  const clave = consulta(rango);
  const [estado, setEstado] = useState({ clave: null, datos: null, error: '' });

  useEffect(() => {
    let vigente = true;
    api.get('/reportes/rentabilidad' + clave)
      .then((d) => vigente && setEstado({ clave, datos: d, error: '' }))
      .catch((e) => vigente && setEstado({ clave, datos: null, error: e.message }));
    return () => { vigente = false; };
  }, [clave]);

  const datos = estado.datos;
  const error = estado.error;
  const cargando = estado.clave !== clave;

  const aplicarAtajo = (meses) => {
    const f = new Date();
    const inicio = new Date(f.getFullYear(), f.getMonth() - meses, 1);
    setRango({ desde: inicio.toISOString().slice(0, 10), hasta: hoy() });
  };

  return (
    <>
      <div className="encabezado">
        <div>
          <h1>Análisis de rentabilidad</h1>
          <p>Margen real cruzando el costo de adquisición contra el precio de venta.</p>
        </div>
        {puede('reportes.exportar') ? (
          <div className="acciones">
            <Boton onClick={() => descargar(
              '/reportes/rentabilidad/exportar' + consulta({ ...rango, formato: 'pdf' }), 'rentabilidad.pdf'
            )}>PDF</Boton>
            <Boton onClick={() => descargar(
              '/reportes/rentabilidad/exportar' + consulta({ ...rango, formato: 'csv' }), 'rentabilidad.csv'
            )}>CSV</Boton>
            <Boton variante="primario" onClick={() => descargar(
              '/reportes/rentabilidad/exportar' + consulta({ ...rango, formato: 'excel' }), 'rentabilidad.xlsx'
            )}>Excel</Boton>
          </div>
        ) : null}
      </div>

      <div className="panel">
        <div className="filtros">
          <Entrada etiqueta="Desde" type="date" value={rango.desde}
            onChange={(e) => setRango((r) => ({ ...r, desde: e.target.value }))} />
          <Entrada etiqueta="Hasta" type="date" value={rango.hasta}
            onChange={(e) => setRango((r) => ({ ...r, hasta: e.target.value }))} />
          <Boton onClick={() => aplicarAtajo(0)}>Este mes</Boton>
          <Boton onClick={() => aplicarAtajo(1)}>Últimos 2 meses</Boton>
          <Boton onClick={() => aplicarAtajo(5)}>Últimos 6 meses</Boton>
        </div>

        {error ? <Aviso tipo="error">{error}</Aviso> : null}
        {cargando || !datos ? <Cargando texto="Calculando la rentabilidad" /> : (
          <>
            <div className="rejilla-metricas">
              <Metrica etiqueta="Ingresos brutos" valor={dinero(datos.totales.ingresos_brutos)}
                nota={datos.totales.transacciones + ' transacciones'} />
              <Metrica etiqueta="Base gravable" valor={dinero(datos.totales.base_gravable)} tono="info"
                nota={'Impuestos ' + dinero(datos.totales.impuestos)} />
              <Metrica etiqueta="Costo de ventas" valor={dinero(datos.totales.costo_ventas)} tono="neutro" />
              <Metrica etiqueta="Utilidad bruta" valor={dinero(datos.totales.utilidad_bruta)} tono="exito"
                tendencia={datos.totales.utilidad_bruta > 0 ? 'sube' : 'baja'} />
              <Metrica etiqueta="Margen" valor={porcentaje(datos.totales.margen)} tono="amarillo" />
              <Metrica etiqueta="Ticket promedio" valor={dinero(datos.totales.ticket_promedio)} />
              <Metrica etiqueta="Descuentos" valor={dinero(datos.totales.descuentos)} tono="error" />
              <Metrica etiqueta="Compras del periodo" valor={dinero(datos.totales.compras)} tono="neutro" />
            </div>

            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(320px, 1fr))', gap: 20 }}>
              <Tarjeta titulo="Utilidad por día" sub={'Del ' + fechaCorta(datos.desde) + ' al ' + fechaCorta(datos.hasta)}>
                {datos.dias.length === 0 ? (
                  <Aviso tipo="info">No hubo ventas en el rango seleccionado.</Aviso>
                ) : (
                  <Columnas
                    datos={datos.dias.map((d) => ({
                      etiqueta: fechaCorta(d.dia).slice(0, 5),
                      valor: d.utilidad,
                    }))}
                    formato={(v) => Math.round(v / 1000) + 'k'}
                  />
                )}
              </Tarjeta>

              <Tarjeta titulo="Participación por categoría" sub="Ingresos del periodo">
                {datos.categorias.length === 0 ? (
                  <Aviso tipo="info">Sin datos para el rango seleccionado.</Aviso>
                ) : (
                  <Anillo
                    datos={datos.categorias.map((c, i) => ({
                      etiqueta: c.categoria,
                      valor: Math.round(c.ingresos),
                      color: PALETA[i % PALETA.length],
                    }))}
                    centroValor={porcentaje(datos.totales.margen)}
                    centroTitulo="margen global"
                  />
                )}
              </Tarjeta>
            </div>

            <Tarjeta titulo="Margen por categoría" sub="Porcentaje de utilidad sobre la base gravable">
              {datos.categorias.length === 0 ? (
                <Aviso tipo="info">Sin datos para el rango seleccionado.</Aviso>
              ) : (
                <Barras
                  datos={datos.categorias.map((c, i) => ({
                    etiqueta: c.categoria,
                    valor: c.margen,
                    color: PALETA[i % PALETA.length],
                  }))}
                  formato={(v) => porcentaje(v)}
                />
              )}
            </Tarjeta>

            <Tarjeta titulo="Detalle por producto" sub={datos.productos.length + ' referencias vendidas'} plana>
              <Tabla
                columnas={[
                  { texto: 'Código' }, { texto: 'Producto' }, { texto: 'Categoría' },
                  { texto: 'Unidades', derecha: true }, { texto: 'Ingresos', derecha: true },
                  { texto: 'Costo', derecha: true }, { texto: 'Utilidad', derecha: true },
                  { texto: 'Margen', derecha: true },
                ]}
                filas={datos.productos}
                clave={(p) => p.producto_id}
                vacio="No se registraron ventas en el rango seleccionado."
                render={(p) => (
                  <>
                    <td className="tabular tenue">{p.sku}</td>
                    <td className="principal">{p.nombre}</td>
                    <td className="tenue">{p.categoria}</td>
                    <td className="derecha tabular">{cantidad(p.unidades)}</td>
                    <td className="derecha tabular">{dinero(p.ingresos)}</td>
                    <td className="derecha tabular tenue">{dinero(p.costo)}</td>
                    <td className="derecha tabular principal" style={{ color: p.utilidad >= 0 ? 'var(--exito)' : 'var(--error)' }}>
                      {dinero(p.utilidad)}
                    </td>
                    <td className="derecha tabular">{porcentaje(p.margen)}</td>
                  </>
                )}
              />
            </Tarjeta>

            <Tarjeta titulo="Resumen diario" plana>
              <Tabla
                columnas={[
                  { texto: 'Día' }, { texto: 'Transacciones', derecha: true },
                  { texto: 'Facturado', derecha: true }, { texto: 'Base', derecha: true },
                  { texto: 'Costo', derecha: true }, { texto: 'Utilidad', derecha: true },
                ]}
                filas={datos.dias}
                clave={(d) => d.dia}
                vacio="Sin movimiento en el rango."
                render={(d) => (
                  <>
                    <td className="principal">{fechaCorta(d.dia)}</td>
                    <td className="derecha tabular">{d.transacciones}</td>
                    <td className="derecha tabular">{dinero(d.total)}</td>
                    <td className="derecha tabular tenue">{dinero(d.base)}</td>
                    <td className="derecha tabular tenue">{dinero(d.costo)}</td>
                    <td className="derecha tabular principal" style={{ color: 'var(--exito)' }}>{dinero(d.utilidad)}</td>
                  </>
                )}
              />
            </Tarjeta>
          </>
        )}
      </div>
    </>
  );
}
