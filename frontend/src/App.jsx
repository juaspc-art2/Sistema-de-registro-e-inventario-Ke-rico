import { useMemo, useState } from 'react';
import { ProveedorSesion, useSesion } from './contexto/Sesion.jsx';
import { Boton, Cargando, Isotipo } from './ui/Componentes.jsx';
import Acceso from './componentes/Acceso.jsx';
import Panel from './componentes/Panel.jsx';
import GestionVentas from './componentes/GestionVentas.jsx';
import EmisionComprobantes from './componentes/EmisionComprobantes.jsx';
import Descuentos from './componentes/Descuentos.jsx';
import AlertasStock from './componentes/AlertasStock.jsx';
import Inventario from './componentes/Inventario.jsx';
import Auditoria from './componentes/Auditoria.jsx';
import Proveedores from './componentes/Proveedores.jsx';
import Rentabilidad from './componentes/Rentabilidad.jsx';
import Bitacora from './componentes/Bitacora.jsx';
import Administracion from './componentes/Administracion.jsx';

const MODULOS = [
  { id: 'panel', etiqueta: 'Panel principal', icono: '▦', permiso: null, componente: Panel },
  { id: 'ventas', etiqueta: 'Ventas y stock', icono: '▶', permiso: 'ventas.ver', componente: GestionVentas, rf: 'RF1' },
  { id: 'comprobantes', etiqueta: 'Comprobantes', icono: '☷', permiso: 'comprobantes.ver', componente: EmisionComprobantes, rf: 'RF2' },
  { id: 'descuentos', etiqueta: 'Descuentos', icono: '✂', permiso: 'descuentos.aplicar', componente: Descuentos, rf: 'RF3' },
  { id: 'alertas', etiqueta: 'Alertas de stock', icono: '⚠', permiso: 'alertas.ver', componente: AlertasStock, rf: 'RF4' },
  { id: 'inventario', etiqueta: 'Inventario', icono: '☰', permiso: 'inventario.ver', componente: Inventario },
  { id: 'auditoria', etiqueta: 'Auditoría', icono: '⇅', permiso: 'inventario.ver', componente: Auditoria, rf: 'RF5' },
  { id: 'proveedores', etiqueta: 'Proveedores', icono: '⊞', permiso: 'proveedores.ver', componente: Proveedores, rf: 'RF6' },
  { id: 'rentabilidad', etiqueta: 'Rentabilidad', icono: '↗', permiso: 'reportes.ver', componente: Rentabilidad, rf: 'RF7' },
  { id: 'bitacora', etiqueta: 'Registro de actividad', icono: '≡', permiso: 'bitacora.ver', componente: Bitacora, rf: 'RF8' },
  { id: 'administracion', etiqueta: 'Administración', icono: '⚙', permiso: 'usuarios.gestionar', componente: Administracion },
];

function Aplicacion() {
  const { usuario, cargando, puede, salir, notificacion, porExpirar, minutosSesion, empresa } = useSesion();
  const [activo, setActivo] = useState('panel');
  const [alertasPendientes, setAlertasPendientes] = useState(0);

  const disponibles = useMemo(
    () => MODULOS.filter((m) => m.permiso === null || puede(m.permiso)),
    [puede]
  );

  if (cargando) {
    return (
      <div style={{ minHeight: '100vh', display: 'grid', placeItems: 'center' }}>
        <Cargando texto="Abriendo el sistema Ke-Rico!" />
      </div>
    );
  }

  if (!usuario) return <Acceso />;

  const moduloActivo = disponibles.find((m) => m.id === activo) || disponibles[0];
  const Vista = moduloActivo.componente;

  return (
    <div className="app">
      <aside className="barra">
        <div className="barra-marca">
          <Isotipo tamano={38} />
          <div>
            <div className="nombre">Ke-Rico!</div>
            <div className="claim">Tradición en cada bocado</div>
          </div>
        </div>

        <div className="barra-titulo">Módulos</div>
        <nav>
          {disponibles.map((m) => (
            <button
              key={m.id}
              type="button"
              className={moduloActivo.id === m.id ? 'activo' : ''}
              onClick={() => setActivo(m.id)}
            >
              <span className="icono" aria-hidden="true">{m.icono}</span>
              {m.etiqueta}
              {m.id === 'alertas' && alertasPendientes > 0 ? (
                <span className="globo">{alertasPendientes}</span>
              ) : null}
            </button>
          ))}
        </nav>

        <div className="barra-pie">
          <div>
            <div className="usuario">{usuario.nombre} {usuario.apellido}</div>
            <div className="rol">{usuario.rol}</div>
          </div>
          <Boton pequeno onClick={salir}>Cerrar sesión</Boton>
        </div>
      </aside>

      <main className="contenido">
        <Vista
          onAlertasPendientes={setAlertasPendientes}
          empresa={empresa}
          irA={setActivo}
        />
      </main>

      {notificacion ? (
        <div className={'notificacion aviso ' + (notificacion.tipo === 'error' ? 'error' : 'exito')}>
          <span className="icono" aria-hidden="true">{notificacion.tipo === 'error' ? '✕' : '✓'}</span>
          <span>{notificacion.texto}</span>
        </div>
      ) : null}

      {porExpirar ? (
        <div className="aviso-sesion">
          <strong>Sesión por expirar.</strong> Se cerrará sola tras {minutosSesion} minutos sin actividad.
        </div>
      ) : null}
    </div>
  );
}

export default function App() {
  return (
    <ProveedorSesion>
      <Aplicacion />
    </ProveedorSesion>
  );
}
