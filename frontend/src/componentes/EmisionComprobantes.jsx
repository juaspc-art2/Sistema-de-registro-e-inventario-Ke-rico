import { useState } from 'react';
import { api, consulta, descargar, dinero, fechaHora, hoy, primerDiaDelMes } from '../api.js';
import { useSesion } from '../contexto/Sesion.jsx';
import { useListado } from '../ganchos/useListado.js';
import {
  Aviso, Boton, Cargando, EncabezadoOrden, Entrada, EtiquetaEstado, Metrica, Modal,
  Paginacion, Seleccion, Tarjeta,
  opcionesEstado,
} from '../ui/Componentes.jsx';
import { Tirilla } from './GestionVentas.jsx';

const TIPOS = ['Tirilla POS', 'Factura de venta', 'Nota credito', 'Nota debito'];

export default function EmisionComprobantes() {
  const { puede, avisar } = useSesion();
  const lista = useListado(
    '/comprobantes',
    { desde: primerDiaDelMes(), hasta: hoy(), busqueda: '', tipo: '', estado: '' },
    { ordenInicial: 'fecha' }
  );
  const [detalle, setDetalle] = useState(null);
  const [emitiendo, setEmitiendo] = useState(false);
  const [folioVenta, setFolioVenta] = useState('');
  const [tipoNuevo, setTipoNuevo] = useState('Factura de venta');
  const [errorEmitir, setErrorEmitir] = useState('');

  const ver = async (id) => {
    try {
      setDetalle(await api.get('/comprobantes/' + id));
    } catch (e) {
      avisar(e.message, 'error');
    }
  };

  const emitir = async () => {
    setErrorEmitir('');
    const id = Number(String(folioVenta).replace(/\D/g, ''));
    if (!id) {
      setErrorEmitir('Escriba el número de la venta, por ejemplo V-000012 o 12.');
      return;
    }
    try {
      const c = await api.post('/comprobantes', { venta_id: id, tipo: tipoNuevo });
      avisar('Comprobante ' + c.numero + ' emitido');
      setEmitiendo(false);
      setFolioVenta('');
      lista.recargar();
      setDetalle(c);
    } catch (e) {
      setErrorEmitir(e.message);
    }
  };

  const emitidos = lista.items.filter((c) => c.estado === 'Emitido');
  const totalEmitido = emitidos.reduce((a, c) => a + c.total, 0);
  const impuestoEmitido = emitidos.reduce((a, c) => a + c.impuesto, 0);

  return (
    <>
      <div className="encabezado">
        <div>
          <h1>Comprobantes digitales</h1>
          <p>Cada venta genera un documento con folio único, impuestos y detalle.</p>
        </div>
        {puede('comprobantes.emitir') ? (
          <div className="acciones">
            <Boton variante="primario" onClick={() => { setEmitiendo(true); setErrorEmitir(''); }}>
              + Emitir comprobante
            </Boton>
          </div>
        ) : null}
      </div>

      <div className="panel">
        <div className="rejilla-metricas">
          <Metrica etiqueta="Comprobantes en pantalla" valor={String(lista.total)} />
          <Metrica etiqueta="Vigentes" valor={String(emitidos.length)} tono="exito" />
          <Metrica etiqueta="Valor facturado" valor={dinero(totalEmitido)} tono="amarillo" />
          <Metrica etiqueta="Impuesto recaudado" valor={dinero(impuestoEmitido)} tono="info" />
        </div>

        <div className="filtros">
          <Entrada
            etiqueta="Buscar"
            placeholder="Número, folio o cliente"
            value={lista.filtros.busqueda}
            onChange={(e) => lista.cambiarFiltro('busqueda', e.target.value)}
          />
          <Entrada etiqueta="Desde" type="date" value={lista.filtros.desde}
            onChange={(e) => lista.cambiarFiltro('desde', e.target.value)} />
          <Entrada etiqueta="Hasta" type="date" value={lista.filtros.hasta}
            onChange={(e) => lista.cambiarFiltro('hasta', e.target.value)} />
          <Seleccion
            etiqueta="Tipo"
            value={lista.filtros.tipo}
            onChange={(e) => lista.cambiarFiltro('tipo', e.target.value)}
            vacio="Todos"
            opciones={opcionesEstado(TIPOS)}
          />
          <Seleccion
            etiqueta="Estado"
            value={lista.filtros.estado}
            onChange={(e) => lista.cambiarFiltro('estado', e.target.value)}
            vacio="Todos"
            opciones={[{ valor: 'Emitido', texto: 'Emitido' }, { valor: 'Anulado', texto: 'Anulado' }]}
          />
          {puede('reportes.exportar') ? (
            <>
              <Boton onClick={() => descargar(
                '/comprobantes/exportar' + consulta({ formato: 'pdf', desde: lista.filtros.desde, hasta: lista.filtros.hasta }),
                'comprobantes.pdf'
              )}>PDF</Boton>
              <Boton onClick={() => descargar(
                '/comprobantes/exportar' + consulta({ formato: 'csv', desde: lista.filtros.desde, hasta: lista.filtros.hasta }),
                'comprobantes.csv'
              )}>CSV</Boton>
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
                      <EncabezadoOrden texto="Numero" campo="numero" orden={lista.orden} direccion={lista.direccion} onOrdenar={lista.ordenar} />
                      <EncabezadoOrden texto="Tipo" campo="tipo" orden={lista.orden} direccion={lista.direccion} onOrdenar={lista.ordenar} />
                      <EncabezadoOrden texto="Venta" campo="folio" orden={lista.orden} direccion={lista.direccion} onOrdenar={lista.ordenar} />
                      <EncabezadoOrden texto="Fecha" campo="fecha" orden={lista.orden} direccion={lista.direccion} onOrdenar={lista.ordenar} />
                      <th>Cliente</th>
                      <th className="derecha">Base</th>
                      <th className="derecha">Impuesto</th>
                      <EncabezadoOrden texto="Total" campo="total" orden={lista.orden} direccion={lista.direccion} onOrdenar={lista.ordenar} derecha />
                      <EncabezadoOrden texto="Estado" campo="estado" orden={lista.orden} direccion={lista.direccion} onOrdenar={lista.ordenar} />
                      <th />
                    </tr>
                  </thead>
                  <tbody>
                    {lista.items.length === 0 ? (
                      <tr><td colSpan={10}><div className="vacio">No hay comprobantes en el periodo seleccionado.</div></td></tr>
                    ) : lista.items.map((c) => (
                      <tr key={c.id}>
                        <td className="principal tabular">{c.numero}</td>
                        <td>{c.tipo}</td>
                        <td className="tabular tenue">{c.folio}</td>
                        <td className="tenue">{fechaHora(c.fecha_emision)}</td>
                        <td className="tenue">{(c.cliente && c.cliente.nombre) || 'Consumidor final'}</td>
                        <td className="derecha tabular">{dinero(c.base_gravable)}</td>
                        <td className="derecha tabular">{dinero(c.impuesto)}</td>
                        <td className="derecha tabular principal">{dinero(c.total)}</td>
                        <td><EtiquetaEstado valor={c.estado} /></td>
                        <td style={{ textAlign: 'right', whiteSpace: 'nowrap' }}>
                          <Boton pequeno onClick={() => ver(c.id)}>Ver</Boton>
                          <Boton pequeno onClick={() => descargar('/comprobantes/' + c.id + '/descargar?formato=pdf', 'comprobante.pdf')}>
                            PDF
                          </Boton>
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
      </div>

      {detalle ? (
        <Modal
          titulo={detalle.tipo + ' ' + detalle.numero}
          angosto
          onCerrar={() => setDetalle(null)}
          pie={
            <>
              <Boton onClick={() => setDetalle(null)}>Cerrar</Boton>
              <Boton onClick={() => descargar('/comprobantes/' + detalle.id + '/descargar?formato=excel', 'comprobante.xlsx')}>
                Excel
              </Boton>
              <Boton variante="primario" onClick={() => descargar('/comprobantes/' + detalle.id + '/descargar?formato=pdf', 'comprobante.pdf')}>
                Descargar PDF
              </Boton>
            </>
          }
        >
          {detalle.estado === 'Anulado' ? <Aviso tipo="error">Este comprobante fue anulado.</Aviso> : null}
          <Tirilla comprobante={detalle} />
        </Modal>
      ) : null}

      {emitiendo ? (
        <Modal
          titulo="Emitir comprobante"
          angosto
          onCerrar={() => setEmitiendo(false)}
          pie={
            <>
              <Boton onClick={() => setEmitiendo(false)}>Cancelar</Boton>
              <Boton variante="primario" onClick={emitir}>Emitir</Boton>
            </>
          }
        >
          <Aviso tipo="info">
            Escriba el folio de una venta existente para emitir un documento adicional.
            El consecutivo se asigna automáticamente.
          </Aviso>
          <Entrada
            etiqueta="Folio o número de venta"
            value={folioVenta}
            onChange={(e) => setFolioVenta(e.target.value)}
            error={errorEmitir}
            placeholder="V-000012"
          />
          <Seleccion
            etiqueta="Tipo de comprobante"
            value={tipoNuevo}
            onChange={(e) => setTipoNuevo(e.target.value)}
            opciones={opcionesEstado(TIPOS)}
          />
        </Modal>
      ) : null}
    </>
  );
}
