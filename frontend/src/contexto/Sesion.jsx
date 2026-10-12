import { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState } from 'react';
import { alExpirarSesion, api, ErrorApi, guardarCsrf, guardarToken, leerToken } from '../api.js';
import { escribirRuta } from '../rutas.js';

const ContextoSesion = createContext(null);

export function ProveedorSesion({ children }) {
  const [usuario, setUsuario] = useState(null);
  const [permisos, setPermisos] = useState([]);
  const [empresa, setEmpresa] = useState({});
  const [correoActivo, setCorreoActivo] = useState(false);
  const [minutosSesion, setMinutosSesion] = useState(15);
  const [cargando, setCargando] = useState(() => Boolean(leerToken()));
  const [mensajeSalida, setMensajeSalida] = useState('');
  const [notificacion, setNotificacion] = useState(null);
  const [porExpirar, setPorExpirar] = useState(false);

  const ultimaActividad = useRef(0);
  const temporizador = useRef(null);

  const limpiar = useCallback((mensaje) => {
    guardarToken('');
    guardarCsrf('');
    setUsuario(null);
    setPermisos([]);
    setPorExpirar(false);
    escribirRuta('panel', true);
    if (mensaje) setMensajeSalida(mensaje);
  }, []);

  useEffect(() => alExpirarSesion((mensaje) => limpiar(mensaje)), [limpiar]);

  const aplicarSesion = useCallback((datos) => {
    if (datos.csrf_token) guardarCsrf(datos.csrf_token);
    setUsuario(datos.usuario);
    setPermisos(datos.permisos || []);
    setEmpresa(datos.empresa || {});
    setCorreoActivo(Boolean(datos.correo_activo));
    setMinutosSesion(datos.minutos_sesion || 15);
    ultimaActividad.current = Date.now();
  }, []);

  useEffect(() => {
    if (!leerToken()) return undefined;

    let vigente = true;
    api.get('/auth/sesion')
      .then((datos) => { if (vigente) aplicarSesion(datos); })
      .catch(() => { if (vigente) limpiar(''); })
      .finally(() => { if (vigente) setCargando(false); });

    return () => { vigente = false; };
  }, [aplicarSesion, limpiar]);

  const ingresar = useCallback(async (nombreUsuario, password) => {
    const datos = await api.publico.post('/auth/login', { usuario: nombreUsuario, password });
    guardarToken(datos.token);
    guardarCsrf(datos.csrf_token);
    setUsuario(datos.usuario);
    setPermisos(datos.permisos || []);
    setMinutosSesion(datos.minutos_sesion || 15);
    setMensajeSalida('');
    ultimaActividad.current = Date.now();
    try {
      const sesion = await api.get('/auth/sesion');
      setEmpresa(sesion.empresa || {});
      setCorreoActivo(Boolean(sesion.correo_activo));
    } catch {
      setEmpresa({});
    }
    return datos.usuario;
  }, []);

  const salir = useCallback(async () => {
    try {
      await api.post('/auth/logout', {});
    } catch {
      limpiar('');
      return;
    }
    limpiar('Cerró sesión correctamente.');
  }, [limpiar]);

  useEffect(() => {
    if (!usuario) return undefined;

    const registrar = () => {
      ultimaActividad.current = Date.now();
      setPorExpirar(false);
    };

    const eventos = ['click', 'keydown', 'mousemove', 'scroll', 'touchstart'];
    eventos.forEach((e) => window.addEventListener(e, registrar, { passive: true }));

    // El servidor mide la inactividad por peticiones y la página por movimiento. Mientras la persona siga
    // activa se le avisa al servidor cada pocos minutos: así no cierra una sesión que aquí sigue viva
    // (por ejemplo, mientras se arma una venta larga sin consultar nada)
    const cadaLatido = Math.max(1, Math.min(5, minutosSesion / 3)) * 60000;
    let ultimoLatido = Date.now();

    temporizador.current = setInterval(() => {
      const ahora = Date.now();
      if (ahora - ultimaActividad.current < 60000 && ahora - ultimoLatido >= cadaLatido) {
        ultimoLatido = ahora;
        api.get('/auth/sesion').catch(() => {});
      }

      const inactivo = (Date.now() - ultimaActividad.current) / 60000;
      if (inactivo >= minutosSesion) {
        limpiar('La sesión se cerró automáticamente tras ' + minutosSesion + ' minutos de inactividad.');
      } else if (inactivo >= minutosSesion - 2) {
        setPorExpirar(true);
      }
    }, 15000);

    return () => {
      eventos.forEach((e) => window.removeEventListener(e, registrar));
      if (temporizador.current) clearInterval(temporizador.current);
    };
  }, [usuario, minutosSesion, limpiar]);

  const avisar = useCallback((texto, tipo = 'exito') => {
    setNotificacion({ texto, tipo, id: Date.now() });
  }, []);

  useEffect(() => {
    const mostrar = (e) => {
      if (e.reason instanceof ErrorApi && e.reason.estado !== 401) avisar(e.reason.message, 'error');
    };
    window.addEventListener('unhandledrejection', mostrar);
    return () => window.removeEventListener('unhandledrejection', mostrar);
  }, [avisar]);

  useEffect(() => {
    if (!notificacion) return undefined;
    const t = setTimeout(() => setNotificacion(null), 4200);
    return () => clearTimeout(t);
  }, [notificacion]);

  const puede = useCallback((codigo) => permisos.includes(codigo), [permisos]);

  const valor = useMemo(() => ({
    usuario,
    permisos,
    empresa,
    correoActivo,
    cargando,
    mensajeSalida,
    notificacion,
    porExpirar,
    minutosSesion,
    ingresar,
    salir,
    puede,
    avisar,
    limpiarMensajeSalida: () => setMensajeSalida(''),
  }), [usuario, permisos, empresa, correoActivo, cargando, mensajeSalida, notificacion, porExpirar,
       minutosSesion, ingresar, salir, puede, avisar]);

  return <ContextoSesion.Provider value={valor}>{children}</ContextoSesion.Provider>;
}

export function useSesion() {
  const contexto = useContext(ContextoSesion);
  if (!contexto) throw new Error('useSesion debe usarse dentro de ProveedorSesion');
  return contexto;
}
