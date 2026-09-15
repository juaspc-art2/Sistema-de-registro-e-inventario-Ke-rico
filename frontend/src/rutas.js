const BASE = (() => {
  if (typeof document === 'undefined') return '/';
  const ruta = new URL('.', document.baseURI).pathname;
  return ruta.endsWith('/') ? ruta : ruta + '/';
})();

export function rutaActual() {
  if (typeof window === 'undefined') return '';
  const actual = window.location.pathname;
  if (!actual.startsWith(BASE)) return '';
  return actual.slice(BASE.length).replace(/^\/+|\/+$/g, '');
}

export function escribirRuta(id, reemplazar = false) {
  if (typeof window === 'undefined') return;
  const destino = BASE + (id === 'panel' ? '' : id);
  if (window.location.pathname === destino) return;
  const estado = { modulo: id };
  if (reemplazar) {
    window.history.replaceState(estado, '', destino);
  } else {
    window.history.pushState(estado, '', destino);
  }
}

export function alCambiarRuta(manejador) {
  if (typeof window === 'undefined') return () => {};
  window.addEventListener('popstate', manejador);
  return () => window.removeEventListener('popstate', manejador);
}
