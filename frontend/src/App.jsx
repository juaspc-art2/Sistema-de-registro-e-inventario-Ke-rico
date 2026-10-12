import { useCallback, useEffect, useMemo, useState } from 'react';
import { ProveedorSesion, useSesion } from './contexto/Sesion.jsx';
import { alCambiarRuta, escribirRuta, rutaActual } from './rutas.js';
import { api } from './api.js';
import { Aviso, Boton, Cargando, Entrada, Isotipo, Modal } from './ui/Componentes.jsx';
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

// Cada persona cambia su propia contraseña sin pasar por el administrador
function CambiarClave({ onCerrar, avisar }) {
  const [actual, setActual] = useState('');
  const [nueva, setNueva] = useState('');
  const [repetida, setRepetida] = useState('');
  const [errores, setErrores] = useState({});
  const [error, setError] = useState('');
  const [guardando, setGuardando] = useState(false);

  const guardar = async () => {
    setError('');
    if (nueva !== repetida) {
      setErrores({ repetida: 'No coincide con la contraseña nueva' });
      return;
    }
    setGuardando(true);
    try {
      await api.post('/auth/password', { password_actual: actual, password_nueva: nueva });
      avisar('Contraseña actualizada. Las demás sesiones abiertas de su cuenta se cerraron.');
      onCerrar();
    } catch (e) {
      setError(e.message);
      setErrores(e.errores || {});
    } finally {
      setGuardando(false);
    }
  };

  return (
    <Modal
      titulo="Cambiar contraseña"
      angosto
      onCerrar={onCerrar}
      pie={
        <>
          <Boton onClick={onCerrar}>Cancelar</Boton>
          <Boton variante="primario" onClick={guardar} disabled={guardando || !actual || !nueva}>
            {guardando ? 'Guardando...' : 'Guardar'}
          </Boton>
        </>
      }
    >
      {error ? <Aviso tipo="error">{error}</Aviso> : null}
      <Entrada etiqueta="Contraseña actual" type="password" value={actual}
        onChange={(e) => setActual(e.target.value)} error={errores.password_actual} autoComplete="current-password" autoFocus />
      <Entrada etiqueta="Contraseña nueva" type="password" value={nueva}
        onChange={(e) => setNueva(e.target.value)} error={errores.password_nueva} autoComplete="new-password" />
      <Entrada etiqueta="Repita la contraseña nueva" type="password" value={repetida}
        onChange={(e) => setRepetida(e.target.value)} error={errores.repetida} autoComplete="new-password" />
    </Modal>
  );
}

function Aplicacion() {
  const { usuario, cargando, puede, salir, avisar, notificacion, porExpirar, minutosSesion, empresa } = useSesion();
  const [cambiandoClave, setCambiandoClave] = useState(false);
  const [activo, setActivo] = useState(() => rutaActual() || 'panel');
  const [alertasPendientes, setAlertasPendientes] = useState(0);
  const [negadoDescartado, setNegadoDescartado] = useState('');

  const abrir = useCallback((id) => {
    setNegadoDescartado('');
    setActivo(id);
  }, []);

  useEffect(() => alCambiarRuta(() => {
    setNegadoDescartado('');
    setActivo(rutaActual() || 'panel');
  }), []);

  const disponibles = useMemo(
    () => MODULOS.filter((m) => m.permiso === null || puede(m.permiso)),
    [puede]
  );

  const solicitado = MODULOS.find((m) => m.id === activo);
  const permitido = !!solicitado && (solicitado.permiso === null || puede(solicitado.permiso));
  const idEfectivo = permitido ? activo : 'panel';

  useEffect(() => {
    if (!usuario) return;
    escribirRuta(idEfectivo, !permitido);
  }, [usuario, idEfectivo, permitido]);

  useEffect(() => {
    if (permitido || negadoDescartado === activo) return undefined;
    const t = setTimeout(() => setNegadoDescartado(activo), 6000);
    return () => clearTimeout(t);
  }, [permitido, negadoDescartado, activo]);

  if (cargando) {
    return (
      <div style={{ minHeight: '100vh', display: 'grid', placeItems: 'center' }}>
        <Cargando texto="Abriendo el sistema Ke-Rico!" />
      </div>
    );
  }

  if (!usuario) return <Acceso />;

  const moduloActivo = permitido ? solicitado : disponibles[0];
  const Vista = moduloActivo.componente;

  const rutaNegada = (permitido || negadoDescartado === activo)
    ? ''
    : (solicitado
      ? 'Su rol no tiene permiso para abrir ' + solicitado.etiqueta
      : 'La dirección solicitada no corresponde a ningún módulo');

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
              onClick={() => abrir(m.id)}
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
          <Boton pequeno variante="sutil" onClick={() => setCambiandoClave(true)}>Cambiar contraseña</Boton>
          <Boton pequeno onClick={salir}>Cerrar sesión</Boton>
        </div>
      </aside>

      {cambiandoClave ? <CambiarClave onCerrar={() => setCambiandoClave(false)} avisar={avisar} /> : null}

      <main className="contenido">
        <Vista
          onAlertasPendientes={setAlertasPendientes}
          empresa={empresa}
          irA={abrir}
        />
      </main>

      {rutaNegada ? (
        <div className="notificacion aviso error" role="alert">
          <span className="icono" aria-hidden="true">⚠</span>
          <span>{rutaNegada}</span>
          <button
            type="button"
            className="cerrar-aviso"
            onClick={() => setNegadoDescartado(activo)}
            aria-label="Cerrar aviso"
          >
            ✕
          </button>
        </div>
      ) : null}

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

function Contenedor() {
  const { usuario } = useSesion();
  return <Aplicacion key={usuario ? usuario.id : 'anonimo'} />;
}

export default function App() {
  return (
    <ProveedorSesion>
      <Contenedor />
    </ProveedorSesion>
  );
}
