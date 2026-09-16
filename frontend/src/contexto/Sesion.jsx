import { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState } from 'react';
import { alExpirarSesion, api, guardarCsrf, guardarToken, leerToken } from '../api.js';
import { escribirRuta } from '../rutas.js';

const ContextoSesion = createContext(null);

export function ProveedorSesion({ children }) {
  const [usuario, setUsuario] = useState(null);
  const [permisos, setPermisos] = useState([]);
  const [empresa, setEmpresa] = useState({});
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
    setMinutosSesion(datos.minutos_sesion || 15);
    ultimaActividad.current = Date.now();
  }, []);

  const cargarSesion = useCallback(
    () => api.get('/auth/sesion').then(aplicarSesion),
    [aplicarSesion]
  );

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

    temporizador.current = setInterval(() => {
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
    if (!notificacion) return undefined;
    const t = setTimeout(() => setNotificacion(null), 4200);
    return () => clearTimeout(t);
  }, [notificacion]);

  const puede = useCallback((codigo) => permisos.includes(codigo), [permisos]);

  const valor = useMemo(() => ({
    usuario,
    permisos,
    empresa,
    cargando,
    mensajeSalida,
    notificacion,
    porExpirar,
    minutosSesion,
    ingresar,
    salir,
    puede,
    avisar,
    refrescar: cargarSesion,
    limpiarMensajeSalida: () => setMensajeSalida(''),
  }), [usuario, permisos, empresa, cargando, mensajeSalida, notificacion, porExpirar,
       minutosSesion, ingresar, salir, puede, avisar, cargarSesion]);

  return <ContextoSesion.Provider value={valor}>{children}</ContextoSesion.Provider>;
}

export function useSesion() {
  const contexto = useContext(ContextoSesion);
  if (!contexto) throw new Error('useSesion debe usarse dentro de ProveedorSesion');
  return contexto;
}
