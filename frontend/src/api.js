const BASE = (() => {
  if (typeof window !== 'undefined' && window.KERICO_API) {
    return String(window.KERICO_API).replace(/\/+$/, '');
  }
  if (import.meta.env.VITE_API_URL) {
    return String(import.meta.env.VITE_API_URL).replace(/\/+$/, '');
  }
  if (typeof document !== 'undefined' && document.baseURI) {
    return new URL('api', document.baseURI).pathname;
  }
  return '/api';
})();

const CLAVE_TOKEN = 'kerico.token';
const CLAVE_CSRF = 'kerico.csrf';

export function leerToken() {
  try {
    return localStorage.getItem(CLAVE_TOKEN) || '';
  } catch {
    return '';
  }
}

export function guardarToken(token) {
  try {
    if (token) localStorage.setItem(CLAVE_TOKEN, token);
    else localStorage.removeItem(CLAVE_TOKEN);
  } catch {
    return;
  }
}

export function leerCsrf() {
  try {
    return localStorage.getItem(CLAVE_CSRF) || '';
  } catch {
    return '';
  }
}

export function guardarCsrf(token) {
  try {
    if (token) localStorage.setItem(CLAVE_CSRF, token);
    else localStorage.removeItem(CLAVE_CSRF);
  } catch {
    return;
  }
}

export function consulta(parametros) {
  const partes = Object.entries(parametros || {})
    .filter(([, v]) => v !== undefined && v !== null && v !== '')
    .map(([k, v]) => encodeURIComponent(k) + '=' + encodeURIComponent(v));
  return partes.length ? '?' + partes.join('&') : '';
}

const oyentes = new Set();

export function alExpirarSesion(fn) {
  oyentes.add(fn);
  return () => oyentes.delete(fn);
}

export class ErrorApi extends Error {
  constructor(mensaje, estado, errores) {
    super(mensaje);
    this.estado = estado;
    this.errores = errores || {};
  }
}

async function peticion(metodo, ruta, { cuerpo, sinToken } = {}) {
  const cabeceras = {};
  if (cuerpo !== undefined) cabeceras['Content-Type'] = 'application/json';
  const token = leerToken();
  if (token && !sinToken) {
    cabeceras.Authorization = 'Bearer ' + token;
    const csrf = leerCsrf();
    if (csrf && metodo !== 'GET') cabeceras['X-CSRF-Kerico'] = csrf;
  }

  let respuesta;
  try {
    respuesta = await fetch(BASE + ruta, {
      method: metodo,
      headers: cabeceras,
      body: cuerpo === undefined ? undefined : JSON.stringify(cuerpo),
    });
  } catch {
    throw new ErrorApi('No hay conexión con el servidor. Verifique que Apache y MySQL estén encendidos.', 0);
  }

  if (respuesta.status === 401 && !sinToken) {
    guardarToken('');
    guardarCsrf('');
    const cuerpo = await respuesta.json().catch(() => null);
    const mensaje = (cuerpo && cuerpo.mensaje) || 'La sesión expiró. Vuelva a iniciar sesión.';
    oyentes.forEach((fn) => fn(mensaje));
    throw new ErrorApi(mensaje, 401);
  }

  const datos = await respuesta.json().catch(() => null);

  if (!respuesta.ok || !datos || datos.ok === false) {
    throw new ErrorApi(
      (datos && datos.mensaje) || 'Ocurrió un error inesperado',
      respuesta.status,
      datos && datos.errores
    );
  }

  return datos.datos === undefined ? null : datos.datos;
}

export const api = {
  get: (ruta) => peticion('GET', ruta),
  post: (ruta, cuerpo) => peticion('POST', ruta, { cuerpo }),
  put: (ruta, cuerpo) => peticion('PUT', ruta, { cuerpo }),
  del: (ruta) => peticion('DELETE', ruta),
  publico: {
    post: (ruta, cuerpo) => peticion('POST', ruta, { cuerpo, sinToken: true }),
  },
};

export async function descargar(ruta, nombrePorDefecto) {
  const token = leerToken();
  const respuesta = await fetch(BASE + ruta, {
    headers: token ? { Authorization: 'Bearer ' + token } : {},
  });

  if (respuesta.status === 401) {
    guardarToken('');
    guardarCsrf('');
    oyentes.forEach((fn) => fn('La sesión expiró. Vuelva a iniciar sesión.'));
    throw new ErrorApi('La sesion expiro', 401);
  }

  if (!respuesta.ok) {
    const cuerpo = await respuesta.json().catch(() => null);
    throw new ErrorApi((cuerpo && cuerpo.mensaje) || 'No fue posible generar el archivo', respuesta.status);
  }

  const nombre = respuesta.headers.get('X-Nombre-Archivo') || nombrePorDefecto;
  const blob = await respuesta.blob();
  const url = URL.createObjectURL(blob);
  const enlace = document.createElement('a');
  enlace.href = url;
  enlace.download = nombre;
  document.body.appendChild(enlace);
  enlace.click();
  document.body.removeChild(enlace);
  setTimeout(() => URL.revokeObjectURL(url), 2000);
  return nombre;
}

export function dinero(valor) {
  const numero = Number(valor) || 0;
  return '$' + numero.toLocaleString('es-CO', { maximumFractionDigits: 0 });
}

export function cantidad(valor) {
  const numero = Number(valor) || 0;
  return Number.isInteger(numero) ? String(numero) : numero.toFixed(3).replace(/0+$/, '').replace(/\.$/, '');
}

export function porcentaje(valor) {
  return (Number(valor) || 0).toFixed(1).replace('.', ',') + ' %';
}

export function fechaCorta(valor) {
  if (!valor) return '';
  const texto = String(valor).replace(' ', 'T');
  const f = new Date(texto);
  if (Number.isNaN(f.getTime())) return String(valor);
  return f.toLocaleDateString('es-CO', { day: '2-digit', month: '2-digit', year: 'numeric' });
}

export function fechaHora(valor) {
  if (!valor) return '';
  const texto = String(valor).replace(' ', 'T');
  const f = new Date(texto);
  if (Number.isNaN(f.getTime())) return String(valor);
  return f.toLocaleString('es-CO', {
    day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit',
  });
}

export function soloHora(valor) {
  if (!valor) return '';
  const texto = String(valor).replace(' ', 'T');
  const f = new Date(texto);
  if (Number.isNaN(f.getTime())) return '';
  return f.toLocaleTimeString('es-CO', { hour: '2-digit', minute: '2-digit' });
}

export function hoy() {
  return new Date().toISOString().slice(0, 10);
}

export function primerDiaDelMes() {
  const f = new Date();
  return new Date(f.getFullYear(), f.getMonth(), 1).toISOString().slice(0, 10);
}
