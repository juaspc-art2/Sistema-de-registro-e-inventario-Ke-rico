import { useEffect } from 'react';

export function Isotipo({ tamano = 40, mono = false }) {
  const corona = (
    <g>
      <g transform="translate(78,40) rotate(20)">
        <path d="M-16,0 L-16,-16 L-8,-7 L0,-18 L8,-7 L16,-16 L16,0 Z" />
      </g>
      <g transform="translate(99,26) scale(0.85)">
        <path d="M0,-7 C0.9,-2.5 2.5,-0.9 7,0 C2.5,0.9 0.9,2.5 0,7 C-0.9,2.5 -2.5,0.9 -7,0 C-2.5,-0.9 -0.9,-2.5 0,-7 Z" />
      </g>
      <g transform="translate(96,54) scale(0.6)">
        <path d="M0,-7 C0.9,-2.5 2.5,-0.9 7,0 C2.5,0.9 0.9,2.5 0,7 C-0.9,2.5 -2.5,0.9 -7,0 C-2.5,-0.9 -0.9,-2.5 0,-7 Z" />
      </g>
    </g>
  );

  const cara = 'M56,70 m-36,0 a36,36 0 1 0 72,0 a36,36 0 1 0 -72,0 Z';
  const boca = 'M31,74 C37,102 75,100 82,70 C67,81 46,81 31,74 Z';
  const lengua = 'M47,89 C51,99 67,97 69,86 C61,90 53,90 47,89 Z';
  const ojoI = 'M35,62 C39,54 47,54 51,62';
  const ojoD = 'M63,60 C67,52 75,52 79,60';

  if (mono) {
    return (
      <svg viewBox="0 0 120 120" width={tamano} height={tamano} aria-hidden="true">
        <g fill="currentColor">{corona}</g>
        <path d={cara} fill="none" stroke="currentColor" strokeWidth="6" />
        <path d={boca} fill="currentColor" />
        <path d={ojoI} fill="none" stroke="currentColor" strokeWidth="6.5" strokeLinecap="round" />
        <path d={ojoD} fill="none" stroke="currentColor" strokeWidth="6.5" strokeLinecap="round" />
      </svg>
    );
  }

  return (
    <svg viewBox="0 0 120 120" width={tamano} height={tamano} aria-hidden="true">
      <g fill="#eb5e15">{corona}</g>
      <path d={cara} fill="#edca34" />
      <path d={boca} fill="#eb5e15" />
      <path d={lengua} fill="#fff8f5" />
      <path d={ojoI} fill="none" stroke="#eb5e15" strokeWidth="6.5" strokeLinecap="round" />
      <path d={ojoD} fill="none" stroke="#eb5e15" strokeWidth="6.5" strokeLinecap="round" />
    </svg>
  );
}

export function Metrica({ etiqueta, valor, nota, tendencia, tono = 'naranja' }) {
  const claseNota = tendencia === 'sube' ? 'nota sube' : tendencia === 'baja' ? 'nota baja' : 'nota';
  return (
    <div className={'metrica tono-' + tono}>
      <span className="etiqueta">{etiqueta}</span>
      <span className="valor tabular">{valor}</span>
      {nota ? <span className={claseNota}>{nota}</span> : null}
    </div>
  );
}

export function Tarjeta({ titulo, sub, acciones, plana, children }) {
  return (
    <section className={plana ? 'tarjeta plana' : 'tarjeta'}>
      {titulo ? (
        <div className="tarjeta-titulo" style={plana ? { padding: '18px 20px 0', marginBottom: 10 } : undefined}>
          <span>
            {titulo}
            {sub ? <span className="sub"> · {sub}</span> : null}
          </span>
          {acciones}
        </div>
      ) : null}
      {children}
    </section>
  );
}

const TONOS_ESTADO = {
  Disponible: 'estado-exito',
  Completada: 'estado-exito',
  Emitido: 'estado-exito',
  Activo: 'estado-exito',
  Activa: 'estado-exito',
  Recibida: 'estado-exito',
  Resuelta: 'estado-exito',
  Entrada: 'estado-exito',
  Devolucion: 'estado-exito',
  Info: 'estado-info',
  Vista: 'estado-info',
  Pendiente: 'estado-aviso',
  'En revision': 'estado-aviso',
  Bajo: 'estado-aviso',
  Advertencia: 'estado-aviso',
  Ajuste: 'estado-aviso',
  Critico: 'estado-error',
  Agotado: 'estado-error',
  Anulada: 'estado-error',
  Anulado: 'estado-error',
  Error: 'estado-error',
  Merma: 'estado-error',
  Salida: 'estado-error',
  Inactivo: 'estado-neutro',
  Inactiva: 'estado-neutro',
  Sistema: 'estado-neutro',
};

const ICONOS_ESTADO = {
  Agotado: '✕',
  Critico: '▲',
  Bajo: '▼',
  Disponible: '✓',
  Completada: '✓',
  Emitido: '✓',
  Anulada: '✕',
  Anulado: '✕',
  Error: '✕',
  Advertencia: '▲',
  Pendiente: '○',
};

const NOMBRES_ESTADO = {
  Critico: 'Crítico',
  Devolucion: 'Devolución',
  'En revision': 'En revisión',
  Automatico: 'Automático',
  'Nota credito': 'Nota crédito',
  'Nota debito': 'Nota débito',
  'En proceso': 'En proceso',
  Anulacion: 'Anulación',
  Inicial: 'Inicial',
  Bajo: 'Stock bajo',
};

export function nombreEstado(valor) {
  return NOMBRES_ESTADO[valor] || valor;
}

export function opcionesEstado(valores) {
  return valores.map((v) => ({ valor: v, texto: nombreEstado(v) }));
}

export function EtiquetaEstado({ valor, texto }) {
  const clase = TONOS_ESTADO[valor] || 'estado-neutro';
  const icono = ICONOS_ESTADO[valor];
  return (
    <span className={'etiqueta-estado ' + clase}>
      {icono ? <span aria-hidden="true">{icono}</span> : <span className="punto" aria-hidden="true" />}
      {texto || nombreEstado(valor)}
    </span>
  );
}

export function Boton({ variante = 'normal', pequeno, children, ...resto }) {
  const clases = ['boton'];
  if (variante !== 'normal') clases.push(variante);
  if (pequeno) clases.push('pequeno');
  return (
    <button type="button" className={clases.join(' ')} {...resto}>
      {children}
    </button>
  );
}

export function Campo({ etiqueta, error, children, ancho }) {
  return (
    <div className="campo" style={ancho ? { gridColumn: '1 / -1' } : undefined}>
      {etiqueta ? <label>{etiqueta}</label> : null}
      {children}
      {error ? <span className="error-campo">{error}</span> : null}
    </div>
  );
}

export function Entrada({ etiqueta, error, ancho, ...resto }) {
  return (
    <Campo etiqueta={etiqueta} error={error} ancho={ancho}>
      <input {...resto} />
    </Campo>
  );
}

export function Seleccion({ etiqueta, error, ancho, opciones = [], vacio, ...resto }) {
  return (
    <Campo etiqueta={etiqueta} error={error} ancho={ancho}>
      <select {...resto}>
        {vacio !== undefined ? <option value="">{vacio}</option> : null}
        {opciones.map((o) => (
          <option key={String(o.valor)} value={o.valor}>
            {o.texto}
          </option>
        ))}
      </select>
    </Campo>
  );
}

export function Aviso({ tipo = 'info', children }) {
  const iconos = { exito: '✓', error: '✕', 'aviso-amarillo': '⚠', info: 'i' };
  return (
    <div className={'aviso ' + tipo}>
      <span className="icono" aria-hidden="true">{iconos[tipo] || 'i'}</span>
      <span>{children}</span>
    </div>
  );
}

export function Modal({ titulo, ancho, angosto, onCerrar, pie, children }) {
  useEffect(() => {
    const alPresionar = (e) => {
      if (e.key === 'Escape') onCerrar();
    };
    window.addEventListener('keydown', alPresionar);
    return () => window.removeEventListener('keydown', alPresionar);
  }, [onCerrar]);

  const clases = ['modal'];
  if (ancho) clases.push('ancho');
  if (angosto) clases.push('angosto');

  return (
    <div className="capa" role="dialog" aria-modal="true" onMouseDown={(e) => { if (e.target === e.currentTarget) onCerrar(); }}>
      <div className={clases.join(' ')} onMouseDown={(e) => e.stopPropagation()}>
        <div className="modal-cabecera">
          <h2>{titulo}</h2>
          <Boton variante="sutil" onClick={onCerrar} aria-label="Cerrar">{'✕'}</Boton>
        </div>
        <div className="modal-cuerpo">{children}</div>
        {pie ? <div className="modal-pie">{pie}</div> : null}
      </div>
    </div>
  );
}

export function Cargando({ texto = 'Cargando información' }) {
  return (
    <div className="cargando">
      <span className="girador" aria-hidden="true" />
      {texto}
    </div>
  );
}

export function Vacio({ children }) {
  return <div className="vacio">{children}</div>;
}

export function Pestanas({ opciones, activa, onCambiar }) {
  return (
    <div className="pestanas">
      {opciones.map((o) => (
        <button
          key={o.id}
          type="button"
          className={activa === o.id ? 'activo' : ''}
          onClick={() => onCambiar(o.id)}
        >
          {o.texto}
        </button>
      ))}
    </div>
  );
}

export function Tabla({ columnas, filas, render, vacio = 'No hay registros para mostrar', clave }) {
  return (
    <div className="tabla-envoltura">
      <table className="tabla">
        <thead>
          <tr>
            {columnas.map((c) => (
              <th key={c.id || c.texto} className={c.derecha ? 'derecha' : undefined}>
                {c.texto}
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {filas.length === 0 ? (
            <tr>
              <td colSpan={columnas.length}>
                <div className="vacio">{vacio}</div>
              </td>
            </tr>
          ) : (
            filas.map((f, i) => <tr key={clave ? clave(f, i) : i}>{render(f, i)}</tr>)
          )}
        </tbody>
      </table>
    </div>
  );
}

export function Paginacion({ pagina, paginas, total, porPagina, onPagina, onPorPagina }) {
  if (!total) return null;
  const desde = (pagina - 1) * porPagina + 1;
  const hasta = Math.min(pagina * porPagina, total);

  return (
    <div className="paginacion">
      <span className="resumen tabular">
        {desde}–{hasta} de {total}
      </span>
      <div className="controles">
        <select
          value={porPagina}
          onChange={(e) => onPorPagina(Number(e.target.value))}
          aria-label="Registros por página"
        >
          {[10, 25, 50, 100].map((n) => (
            <option key={n} value={n}>{n} por página</option>
          ))}
        </select>
        <Boton pequeno disabled={pagina <= 1} onClick={() => onPagina(1)} aria-label="Primera página">{'«'}</Boton>
        <Boton pequeno disabled={pagina <= 1} onClick={() => onPagina(pagina - 1)}>Anterior</Boton>
        <span className="indice tabular">{pagina} / {Math.max(paginas, 1)}</span>
        <Boton pequeno disabled={pagina >= paginas} onClick={() => onPagina(pagina + 1)}>Siguiente</Boton>
        <Boton pequeno disabled={pagina >= paginas} onClick={() => onPagina(paginas)} aria-label="Última página">{'»'}</Boton>
      </div>
    </div>
  );
}

export function EncabezadoOrden({ texto, campo, orden, direccion, onOrdenar, derecha }) {
  const activo = orden === campo;
  return (
    <th className={derecha ? 'derecha ordenable' : 'ordenable'}>
      <button type="button" onClick={() => onOrdenar(campo)}>
        {texto}
        <span className={activo ? 'flecha activa' : 'flecha'} aria-hidden="true">
          {activo ? (direccion === 'ASC' ? '▲' : '▼') : '↕'}
        </span>
      </button>
    </th>
  );
}

export function Anillo({ datos, tamano = 150, grosor = 26, centroTitulo, centroValor }) {
  const total = datos.reduce((a, d) => a + d.valor, 0);
  const radio = (tamano - grosor) / 2;
  const circunferencia = 2 * Math.PI * radio;
  let acumulado = 0;

  return (
    <div className="anillo">
      <svg width={tamano} height={tamano} viewBox={`0 0 ${tamano} ${tamano}`} role="img">
        <g transform={`rotate(-90 ${tamano / 2} ${tamano / 2})`}>
          <circle
            cx={tamano / 2} cy={tamano / 2} r={radio}
            fill="none" stroke="var(--masa)" strokeWidth={grosor}
          />
          {total > 0 && datos.map((d) => {
            const fraccion = d.valor / total;
            const trazo = fraccion * circunferencia;
            const elemento = (
              <circle
                key={d.etiqueta}
                cx={tamano / 2} cy={tamano / 2} r={radio}
                fill="none"
                stroke={d.color}
                strokeWidth={grosor}
                strokeDasharray={`${trazo} ${circunferencia - trazo}`}
                strokeDashoffset={-acumulado}
              />
            );
            acumulado += trazo;
            return elemento;
          })}
        </g>
        <text x="50%" y="46%" textAnchor="middle" className="anillo-valor">{centroValor}</text>
        <text x="50%" y="60%" textAnchor="middle" className="anillo-titulo">{centroTitulo}</text>
      </svg>
      <ul className="anillo-leyenda">
        {datos.map((d) => (
          <li key={d.etiqueta}>
            <span className="punto" style={{ background: d.color }} />
            <span className="texto">{d.etiqueta}</span>
            <span className="cifra tabular">{d.valor}</span>
          </li>
        ))}
      </ul>
    </div>
  );
}

export function Barras({ datos, formato }) {
  const maximo = Math.max(...datos.map((d) => Math.abs(d.valor)), 1);
  return (
    <div className="barras">
      {datos.map((d) => (
        <div className="barra-dato" key={d.etiqueta}>
          <span style={{ color: 'var(--piedra)' }}>{d.etiqueta}</span>
          <span className="pista">
            <span
              className="relleno"
              style={{
                width: Math.max((Math.abs(d.valor) / maximo) * 100, 2) + '%',
                background: d.color || 'var(--naranja)',
              }}
            />
          </span>
          <span className="cifra">{formato ? formato(d.valor) : d.valor}</span>
        </div>
      ))}
    </div>
  );
}

export function Columnas({ datos, formato }) {
  const maximo = Math.max(...datos.map((d) => d.valor), 1);
  return (
    <div className="columnas">
      {datos.map((d) => (
        <div className="columna" key={d.etiqueta}>
          <span className="monto">{formato ? formato(d.valor) : d.valor}</span>
          <span
            className="barra-vertical"
            style={{ height: Math.max((d.valor / maximo) * 100, 2) + '%' }}
          />
          <span className="dia">{d.etiqueta}</span>
        </div>
      ))}
    </div>
  );
}
