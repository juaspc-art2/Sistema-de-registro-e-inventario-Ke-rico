import { useState } from 'react';
import { descargar, enviarArchivo } from '../api.js';
import { useSesion } from '../contexto/Sesion.jsx';
import { Boton, Entrada, Modal, Seleccion } from '../ui/Componentes.jsx';

const NOMBRES = { pdf: 'PDF', csv: 'CSV', excel: 'Excel', sql: 'SQL' };
const EXTENSIONES = { excel: 'xlsx' };

// Botones de descarga de un mismo contenido en uno o varios formatos, y su envío por correo.
// ruta(formato) devuelve la dirección de la API; el servidor decide el nombre final del archivo.
export default function Exportar({ ruta, formatos, nombre = 'archivo', etiqueta, principal, pequeno, correoInicial }) {
  const { avisar, correoActivo, usuario } = useSesion();
  const [abierto, setAbierto] = useState(false);
  const [formato, setFormato] = useState(principal || formatos[0]);
  const [correo, setCorreo] = useState('');
  const [enviando, setEnviando] = useState(false);

  const abrir = () => {
    setCorreo(correoInicial !== undefined ? correoInicial : usuario.correo);
    setAbierto(true);
  };

  const enviar = async () => {
    setEnviando(true);
    try {
      const r = await enviarArchivo(ruta(formato), correo.trim());
      avisar('Archivo ' + r.archivo + ' enviado a ' + r.correo);
      setAbierto(false);
    } catch (e) {
      avisar(e.message, 'error');
    } finally {
      setEnviando(false);
    }
  };

  return (
    <>
      {formatos.map((f) => (
        <Boton
          key={f}
          pequeno={pequeno}
          variante={f === principal ? 'primario' : 'normal'}
          onClick={() => descargar(ruta(f), nombre + '.' + (EXTENSIONES[f] || f))}
        >
          {formatos.length === 1 && etiqueta ? etiqueta : NOMBRES[f]}
        </Boton>
      ))}

      {correoActivo ? (
        <Boton pequeno={pequeno} onClick={abrir} title="Enviar por correo">
          <span aria-hidden="true">✉</span> Correo
        </Boton>
      ) : null}

      {abierto ? (
        <Modal
          titulo="Enviar por correo"
          angosto
          onCerrar={() => setAbierto(false)}
          pie={
            <>
              <Boton onClick={() => setAbierto(false)}>Cancelar</Boton>
              <Boton variante="primario" onClick={enviar} disabled={enviando || !correo.trim()}>
                {enviando ? 'Enviando...' : 'Enviar'}
              </Boton>
            </>
          }
        >
          {formatos.length > 1 ? (
            <Seleccion
              etiqueta="Formato"
              value={formato}
              onChange={(e) => setFormato(e.target.value)}
              opciones={formatos.map((f) => ({ valor: f, texto: NOMBRES[f] }))}
            />
          ) : null}
          <Entrada
            etiqueta="Correo de destino"
            type="email"
            value={correo}
            onChange={(e) => setCorreo(e.target.value)}
            onKeyDown={(e) => { if (e.key === 'Enter' && correo.trim() && !enviando) enviar(); }}
            placeholder="nombre@correo.com"
            autoFocus
          />
        </Modal>
      ) : null}
    </>
  );
}
