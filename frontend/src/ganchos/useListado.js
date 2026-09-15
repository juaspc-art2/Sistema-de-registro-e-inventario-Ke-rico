import { useCallback, useEffect, useMemo, useState } from 'react';
import { api, consulta } from '../api.js';

const INICIAL = { clave: null, items: [], total: 0, paginas: 1, error: '', respuesta: null };

export function useListado(ruta, filtrosIniciales = {}, opciones = {}) {
  const { ordenInicial = '', direccionInicial = 'DESC', porPaginaInicial = 25, extraer } = opciones;

  const [filtros, setFiltros] = useState(filtrosIniciales);
  const [pagina, setPagina] = useState(1);
  const [porPagina, setPorPaginaEstado] = useState(porPaginaInicial);
  const [orden, setOrden] = useState(ordenInicial);
  const [direccion, setDireccion] = useState(direccionInicial);
  const [version, setVersion] = useState(0);
  const [estado, setEstado] = useState(INICIAL);

  const direccionUsada = direccion === 'ASC' ? 'ASC' : 'DESC';

  const url = useMemo(() => {
    const parametros = { ...filtros, pagina, por_pagina: porPagina };
    if (orden) {
      parametros.orden = orden;
      parametros.direccion = direccionUsada;
    }
    return ruta + consulta(parametros);
  }, [ruta, filtros, pagina, porPagina, orden, direccionUsada]);

  const clave = url + '|' + version;

  useEffect(() => {
    let vigente = true;

    api.get(url)
      .then((respuesta) => {
        if (!vigente) return;
        const bloque = extraer ? extraer(respuesta) : respuesta;
        setEstado({
          clave,
          items: (bloque && bloque.items) || [],
          total: (bloque && bloque.total) || 0,
          paginas: (bloque && bloque.paginas) || 1,
          error: '',
          respuesta,
        });
      })
      .catch((e) => {
        if (!vigente) return;
        setEstado({ clave, items: [], total: 0, paginas: 1, error: e.message, respuesta: null });
      });

    return () => { vigente = false; };
  }, [url, clave, extraer]);

  const cambiarFiltro = useCallback((nombre, valor) => {
    setFiltros((previos) => ({ ...previos, [nombre]: valor }));
    setPagina(1);
  }, []);

  const reemplazarFiltros = useCallback((nuevos) => {
    setFiltros(nuevos);
    setPagina(1);
  }, []);

  const ordenar = useCallback((campo) => {
    setOrden((actual) => {
      if (actual === campo) {
        setDireccion((d) => (d === 'ASC' ? 'DESC' : 'ASC'));
        return actual;
      }
      setDireccion('ASC');
      return campo;
    });
    setPagina(1);
  }, []);

  const setPorPagina = useCallback((n) => {
    setPorPaginaEstado(n);
    setPagina(1);
  }, []);

  const recargar = useCallback(() => setVersion((v) => v + 1), []);

  return {
    items: estado.items,
    total: estado.total,
    paginas: estado.paginas,
    respuesta: estado.respuesta,
    error: estado.error,
    cargando: estado.clave !== clave,
    filtros,
    pagina,
    porPagina,
    orden,
    direccion: direccionUsada,
    setPagina,
    setPorPagina,
    cambiarFiltro,
    reemplazarFiltros,
    ordenar,
    recargar,
  };
}
