import { useState } from 'react';
import { api } from '../api.js';
import { useSesion } from '../contexto/Sesion.jsx';
import { Aviso, Boton, Entrada, Isotipo, Modal } from '../ui/Componentes.jsx';

const CUENTAS = [
  ['Administrador', 'admin', '123'],
  ['Gerente', 'gerente', '123'],
  ['Cajero', 'cajero', '123'],
  ['Inventario', 'inventario', '123'],
];

export default function Acceso() {
  const { ingresar, mensajeSalida, limpiarMensajeSalida } = useSesion();
  const [usuario, setUsuario] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');
  const [errores, setErrores] = useState({});
  const [enviando, setEnviando] = useState(false);
  const [recuperando, setRecuperando] = useState(false);
  const [correo, setCorreo] = useState('');
  const [avisoRecuperacion, setAvisoRecuperacion] = useState('');

  const validar = () => {
    const nuevos = {};
    if (!usuario.trim()) nuevos.usuario = 'Escriba su usuario o correo';
    if (!password) nuevos.password = 'Escriba su contraseña';
    else if (password.length < 3) nuevos.password = 'La contraseña es demasiado corta';
    setErrores(nuevos);
    return Object.keys(nuevos).length === 0;
  };

  const enviar = async (e) => {
    e.preventDefault();
    setError('');
    limpiarMensajeSalida();
    if (!validar()) return;

    setEnviando(true);
    try {
      await ingresar(usuario.trim(), password);
    } catch (err) {
      setError(err.message);
      if (err.errores) setErrores(err.errores);
    } finally {
      setEnviando(false);
    }
  };

  const recuperar = async (e) => {
    e.preventDefault();
    setAvisoRecuperacion('');
    try {
      const datos = await api.publico.post('/auth/recuperar', { correo });
      setAvisoRecuperacion(
        datos && datos.token
          ? 'Enlace generado. Token de recuperación: ' + datos.token
          : 'Si el correo está registrado recibirá un enlace de recuperación.'
      );
    } catch (err) {
      setAvisoRecuperacion(err.message);
    }
  };

  const usarCuenta = (u, p) => {
    setUsuario(u);
    setPassword(p);
    setErrores({});
  };

  return (
    <div className="acceso">
      <section className="acceso-arte">
        <div className="trama" aria-hidden="true" />
        <div style={{ color: '#fff' }}>
          <Isotipo tamano={64} mono />
        </div>
        <div>
          <h2>Sistema de Registro e Inventario</h2>
          <p>
            Controle las ventas, las existencias, los comprobantes y la rentabilidad del
            negocio desde un solo lugar.
          </p>
        </div>
        <div style={{ fontSize: 13, opacity: 0.9 }}>
          Cra. 59 # 132A - 7, Suba, Bogota D.C.
        </div>
      </section>

      <section className="acceso-formulario">
        <form className="acceso-caja" onSubmit={enviar} noValidate>
          <div>
            <h1>Iniciar sesión</h1>
            <p className="sub">Ingrese con la cuenta que le asignó el administrador.</p>
          </div>

          {mensajeSalida ? <Aviso tipo="info">{mensajeSalida}</Aviso> : null}
          {error ? <Aviso tipo="error">{error}</Aviso> : null}

          <Entrada
            etiqueta="Usuario o correo"
            value={usuario}
            onChange={(e) => setUsuario(e.target.value)}
            error={errores.usuario}
            autoComplete="username"
            required
          />

          <Entrada
            etiqueta="Contrasena"
            type="password"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            error={errores.password}
            autoComplete="current-password"
            required
          />

          <Boton variante="primario" type="submit" disabled={enviando} style={{ width: '100%' }}>
            {enviando ? 'Verificando...' : 'Entrar'}
          </Boton>

          <Boton variante="sutil" onClick={() => setRecuperando(true)}>
            Olvidé mi contraseña
          </Boton>

          <div className="credenciales">
            <strong>Cuentas de demostración</strong>
            <table>
              <tbody>
                {CUENTAS.map(([rol, u, p]) => (
                  <tr key={u}>
                    <td>{rol}</td>
                    <td>
                      <button
                        type="button"
                        onClick={() => usarCuenta(u, p)}
                        style={{
                          border: 'none', background: 'transparent', cursor: 'pointer',
                          color: 'var(--naranja)', font: 'inherit', padding: 0,
                        }}
                      >
                        {u} / {p}
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </form>
      </section>

      {recuperando ? (
        <Modal
          titulo="Recuperar contraseña"
          angosto
          onCerrar={() => { setRecuperando(false); setAvisoRecuperacion(''); }}
          pie={
            <>
              <Boton onClick={() => { setRecuperando(false); setAvisoRecuperacion(''); }}>Cerrar</Boton>
              <Boton variante="primario" onClick={recuperar}>Generar enlace</Boton>
            </>
          }
        >
          <p style={{ margin: 0, fontSize: 14, color: 'var(--piedra)' }}>
            Escriba el correo de su cuenta y el sistema generará un enlace de recuperación
            válido por una hora.
          </p>
          <Entrada
            etiqueta="Correo"
            type="email"
            value={correo}
            onChange={(e) => setCorreo(e.target.value)}
          />
          {avisoRecuperacion ? <Aviso tipo="info">{avisoRecuperacion}</Aviso> : null}
        </Modal>
      ) : null}
    </div>
  );
}
