import { consulta, descargar, fechaHora, hoy, primerDiaDelMes } from '../api.js';
import { useSesion } from '../contexto/Sesion.jsx';
import { useListado } from '../ganchos/useListado.js';
import {
  Anillo, Aviso, Boton, Cargando, EncabezadoOrden, Entrada, EtiquetaEstado, Metrica,
  Paginacion, Seleccion, Tarjeta, nombreModulo,
} from '../ui/Componentes.jsx';

const NIVELES = ['Info', 'Advertencia', 'Error', 'Sistema'];

const COLOR_NIVEL = {
  Info: 'var(--info)',
  Advertencia: 'var(--aviso)',
  Error: 'var(--error)',
  Sistema: 'var(--piedra)',
};

const ICONO = {
  LOGIN: '✓',
  LOGIN_FALLIDO: '✕',
  SESION_EXPIRADA: '⏱',
  ACCESO_DENEGADO: '⛔',
  CSRF_RECHAZADO: '⛔',
  VENTA_REGISTRAR: '▶',
  VENTA_ANULAR: '✕',
  DESCUENTO_APLICAR: '✂',
  COMPROBANTE_EMITIR: '☷',
  PRECIO_MODIFICAR: '⚠',
  ALERTA_STOCK: '⚠',
  RESPALDO: '☁',
};

export default function Bitacora() {
  const { puede } = useSesion();
  const lista = useListado(
    '/sistema/bitacora',
    { desde: primerDiaDelMes(), hasta: hoy(), busqueda: '', nivel: '', modulo: '' },
    { ordenInicial: 'fecha', extraer: (r) => r.registros }
  );

  const resumen = lista.respuesta ? lista.respuesta.resumen : null;

  const distribucion = resumen ? NIVELES.map((n) => ({
    etiqueta: n,
    valor: resumen.niveles[n] || 0,
    color: COLOR_NIVEL[n],
  })) : [];

  return (
    <>
      <div className="encabezado">
        <div>
          <h1>Registro de actividad</h1>
          <p>Historial de acciones, accesos y alertas del sistema.</p>
        </div>
        <div className="acciones">
          <Boton onClick={lista.recargar}>Actualizar</Boton>
          <Boton onClick={() => descargar(
            '/sistema/bitacora/exportar' + consulta({
              formato: 'pdf', desde: lista.filtros.desde, hasta: lista.filtros.hasta,
              nivel: lista.filtros.nivel, modulo: lista.filtros.modulo,
            }),
            'bitacora.pdf'
          )}>PDF</Boton>
          <Boton onClick={() => descargar(
            '/sistema/bitacora/exportar' + consulta({
              formato: 'csv', desde: lista.filtros.desde, hasta: lista.filtros.hasta,
              nivel: lista.filtros.nivel, modulo: lista.filtros.modulo,
            }),
            'bitacora.csv'
          )}>CSV</Boton>
        </div>
      </div>

      <div className="panel">
        {resumen ? (
          <div className="rejilla-metricas">
            <Metrica etiqueta="Eventos de hoy" valor={String(resumen.eventos_hoy)} />
            <Metrica etiqueta="Errores hoy" valor={String(resumen.niveles.Error || 0)} tono="error" />
            <Metrica etiqueta="Advertencias hoy" valor={String(resumen.niveles.Advertencia || 0)} tono="amarillo" />
            <Metrica etiqueta="Usuarios conectados" valor={String(resumen.usuarios_activos)} tono="info" />
            <Metrica etiqueta="Eventos en el filtro" valor={String(lista.total)} tono="neutro" />
          </div>
        ) : null}

        {resumen && resumen.eventos_hoy > 0 ? (
          <Tarjeta titulo="Distribución de eventos de hoy" sub="Por nivel de severidad">
            <Anillo
              datos={distribucion}
              centroValor={String(resumen.eventos_hoy)}
              centroTitulo="eventos hoy"
            />
          </Tarjeta>
        ) : null}

        <div className="filtros">
          <Entrada etiqueta="Buscar" placeholder="Descripción, usuario o acción"
            value={lista.filtros.busqueda}
            onChange={(e) => lista.cambiarFiltro('busqueda', e.target.value)} />
          <Entrada etiqueta="Desde" type="date" value={lista.filtros.desde}
            onChange={(e) => lista.cambiarFiltro('desde', e.target.value)} />
          <Entrada etiqueta="Hasta" type="date" value={lista.filtros.hasta}
            onChange={(e) => lista.cambiarFiltro('hasta', e.target.value)} />
          <Seleccion etiqueta="Nivel" value={lista.filtros.nivel}
            onChange={(e) => lista.cambiarFiltro('nivel', e.target.value)} vacio="Todos"
            opciones={NIVELES.map((n) => ({ valor: n, texto: n }))} />
          <Seleccion etiqueta="Módulo" value={lista.filtros.modulo}
            onChange={(e) => lista.cambiarFiltro('modulo', e.target.value)} vacio="Todos"
            opciones={(resumen ? resumen.modulos : []).map((m) => ({ valor: m, texto: nombreModulo(m) }))} />
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
                      <EncabezadoOrden texto="Usuario" campo="usuario" orden={lista.orden} direccion={lista.direccion} onOrdenar={lista.ordenar} />
                      <EncabezadoOrden texto="Módulo" campo="modulo" orden={lista.orden} direccion={lista.direccion} onOrdenar={lista.ordenar} />
                      <EncabezadoOrden texto="Acción" campo="accion" orden={lista.orden} direccion={lista.direccion} onOrdenar={lista.ordenar} />
                      <th>Descripción</th>
                      <EncabezadoOrden texto="Nivel" campo="nivel" orden={lista.orden} direccion={lista.direccion} onOrdenar={lista.ordenar} />
                      <th>Origen</th>
                    </tr>
                  </thead>
                  <tbody>
                    {lista.items.length === 0 ? (
                      <tr><td colSpan={7}><div className="vacio">No hay eventos con los filtros aplicados.</div></td></tr>
                    ) : lista.items.map((r) => (
                      <tr key={r.id}>
                        <td className="tenue tabular">{fechaHora(r.fecha)}</td>
                        <td className="principal">{r.usuario}</td>
                        <td className="tenue">{r.modulo_nombre || r.modulo}</td>
                        <td className="tabular" style={{ fontSize: 12 }}>
                          <span aria-hidden="true" style={{ marginRight: 6 }}>{ICONO[r.accion] || '•'}</span>
                          {r.accion_nombre || r.accion}
                        </td>
                        <td>{r.descripcion}</td>
                        <td><EtiquetaEstado valor={r.nivel} /></td>
                        <td className="tenue tabular">{r.ip}</td>
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

        {puede('bitacora.ver') ? (
          <Aviso tipo="info">
            La bitácora conserva quién hizo cada acción, desde qué dirección IP y sobre qué
            registro. Los intentos de acceso sin permiso y los rechazos por token de
            seguridad también quedan registrados.
          </Aviso>
        ) : null}
      </div>
    </>
  );
}
