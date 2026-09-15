import { useCallback, useEffect, useState } from 'react';
import { api, fechaHora } from '../api.js';
import { useSesion } from '../contexto/Sesion.jsx';
import { useListado } from '../ganchos/useListado.js';
import {
  Aviso, Boton, Cargando, EncabezadoOrden, Entrada, EtiquetaEstado, Metrica, Modal,
  Paginacion, Pestanas, Seleccion, Tabla, Tarjeta,
} from '../ui/Componentes.jsx';

const DOCUMENTOS = ['CC', 'TI', 'CE', 'PA', 'NIT'];

const VACIO = {
  usuario: '', nombre: '', apellido: '', tipo_documento: 'CC', documento: '',
  fecha_nac: '', correo: '', telefono: '', cargo: '', rol_id: '', activo: true, password: '',
};

function FormularioUsuario({ usuario, roles, onCerrar, onGuardado, avisar }) {
  const [datos, setDatos] = useState(usuario ? {
    usuario: usuario.usuario,
    nombre: usuario.nombre,
    apellido: usuario.apellido,
    tipo_documento: usuario.tipo_documento,
    documento: usuario.documento,
    fecha_nac: usuario.fecha_nac || '',
    correo: usuario.correo,
    telefono: usuario.telefono,
    cargo: usuario.cargo,
    rol_id: String(usuario.rol_id),
    activo: usuario.activo,
    password: '',
  } : { ...VACIO });
  const [errores, setErrores] = useState({});
  const [error, setError] = useState('');
  const [guardando, setGuardando] = useState(false);

  const poner = (clave, valor) => setDatos((d) => ({ ...d, [clave]: valor }));

  const validar = () => {
    const e = {};
    if (!datos.usuario.trim()) e.usuario = 'El usuario es obligatorio';
    else if (datos.usuario.trim().length < 3) e.usuario = 'Use al menos 3 caracteres';
    if (!datos.nombre.trim()) e.nombre = 'El nombre es obligatorio';
    if (!datos.apellido.trim()) e.apellido = 'El apellido es obligatorio';
    if (!datos.documento.trim()) e.documento = 'El documento es obligatorio';
    else if (datos.documento.trim().length < 5) e.documento = 'El documento es demasiado corto';
    if (!datos.correo.trim()) e.correo = 'El correo es obligatorio';
    else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(datos.correo)) e.correo = 'El correo no es válido';
    if (!datos.rol_id) e.rol_id = 'Seleccione el rol';
    if (!usuario && !datos.password) e.password = 'Asigne una contraseña';
    if (datos.password && datos.password.length < 3) e.password = 'Use al menos 3 caracteres';
    setErrores(e);
    return Object.keys(e).length === 0;
  };

  const guardar = async () => {
    setError('');
    if (!validar()) return;
    setGuardando(true);
    try {
      const cuerpo = { ...datos, rol_id: Number(datos.rol_id) };
      if (usuario && !datos.password) delete cuerpo.password;
      if (usuario) await api.put('/sistema/usuarios/' + usuario.id, cuerpo);
      else await api.post('/sistema/usuarios', cuerpo);
      avisar(usuario ? 'Usuario actualizado' : 'Usuario creado');
      onGuardado();
      onCerrar();
    } catch (e) {
      setError(e.message);
      if (e.errores) setErrores(e.errores);
    } finally {
      setGuardando(false);
    }
  };

  return (
    <Modal
      titulo={usuario ? 'Editar ' + usuario.usuario : 'Nuevo usuario'}
      ancho
      onCerrar={onCerrar}
      pie={
        <>
          <Boton onClick={onCerrar}>Cancelar</Boton>
          <Boton variante="primario" onClick={guardar} disabled={guardando}>
            {guardando ? 'Guardando...' : 'Guardar'}
          </Boton>
        </>
      }
    >
      {error ? <Aviso tipo="error">{error}</Aviso> : null}

      <div className="rejilla-formulario">
        <Entrada etiqueta="Usuario" value={datos.usuario}
          onChange={(e) => poner('usuario', e.target.value)} error={errores.usuario} />
        <Entrada etiqueta="Nombre" value={datos.nombre}
          onChange={(e) => poner('nombre', e.target.value)} error={errores.nombre} />
        <Entrada etiqueta="Apellido" value={datos.apellido}
          onChange={(e) => poner('apellido', e.target.value)} error={errores.apellido} />
        <Seleccion etiqueta="Tipo de documento" value={datos.tipo_documento}
          onChange={(e) => poner('tipo_documento', e.target.value)}
          opciones={DOCUMENTOS.map((d) => ({ valor: d, texto: d }))} />
        <Entrada etiqueta="Documento" value={datos.documento}
          onChange={(e) => poner('documento', e.target.value)} error={errores.documento} />
        <Entrada etiqueta="Fecha de nacimiento" type="date" value={datos.fecha_nac}
          onChange={(e) => poner('fecha_nac', e.target.value)} />
        <Entrada etiqueta="Correo" type="email" value={datos.correo}
          onChange={(e) => poner('correo', e.target.value)} error={errores.correo} />
        <Entrada etiqueta="Telefono" value={datos.telefono}
          onChange={(e) => poner('telefono', e.target.value)} />
        <Entrada etiqueta="Cargo" value={datos.cargo}
          onChange={(e) => poner('cargo', e.target.value)} />
        <Seleccion etiqueta="Rol" value={datos.rol_id}
          onChange={(e) => poner('rol_id', e.target.value)} error={errores.rol_id}
          vacio="Seleccione" opciones={roles.map((r) => ({ valor: r.id, texto: r.nombre }))} />
        <Entrada etiqueta={usuario ? 'Nueva contraseña (opcional)' : 'Contraseña'} type="password"
          value={datos.password} onChange={(e) => poner('password', e.target.value)}
          error={errores.password} autoComplete="new-password" />
      </div>

      <label style={{ display: 'flex', gap: 8, alignItems: 'center', fontSize: 14 }}>
        <input type="checkbox" checked={datos.activo} onChange={(e) => poner('activo', e.target.checked)} />
        Cuenta activa
      </label>
    </Modal>
  );
}

function Usuarios() {
  const { avisar, usuario: propio } = useSesion();
  const lista = useListado('/sistema/usuarios', { busqueda: '', rol_id: '', activo: '' },
    { ordenInicial: 'nombre', direccionInicial: 'ASC', extraer: (r) => r.usuarios });
  const [editando, setEditando] = useState(undefined);
  const [borrar, setBorrar] = useState(null);
  const [papelera, setPapelera] = useState(null);

  const roles = lista.respuesta ? lista.respuesta.roles : [];

  const desbloquear = async (u) => {
    try {
      await api.post('/sistema/usuarios/' + u.id + '/desbloquear', {});
      avisar('Cuenta de ' + u.usuario + ' desbloqueada');
      lista.recargar();
    } catch (e) {
      avisar(e.message, 'error');
    }
  };

  const eliminar = async () => {
    try {
      await api.del('/sistema/usuarios/' + borrar.id);
      avisar('Usuario enviado a la papelera');
      setBorrar(null);
      lista.recargar();
    } catch (e) {
      avisar(e.message, 'error');
      setBorrar(null);
    }
  };

  const abrirPapelera = async () => {
    try {
      setPapelera(await api.get('/sistema/usuarios/papelera'));
    } catch (e) {
      avisar(e.message, 'error');
    }
  };

  const restaurar = async (id) => {
    try {
      await api.post('/sistema/usuarios/' + id + '/restaurar', {});
      avisar('Usuario restaurado');
      setPapelera((p) => p.filter((x) => x.id !== id));
      lista.recargar();
    } catch (e) {
      avisar(e.message, 'error');
    }
  };

  return (
    <>
      <div className="rejilla-metricas">
        <Metrica etiqueta="Usuarios" valor={String(lista.total)} />
        <Metrica etiqueta="Activos" valor={String(lista.items.filter((u) => u.activo).length)} tono="exito" />
        <Metrica etiqueta="Bloqueados" valor={String(lista.items.filter((u) => u.bloqueado).length)} tono="error" />
        <Metrica etiqueta="Roles definidos" valor={String(roles.length)} tono="info" />
      </div>

      <div className="filtros">
        <Entrada etiqueta="Buscar" placeholder="Nombre, usuario, correo o documento"
          value={lista.filtros.busqueda}
          onChange={(e) => lista.cambiarFiltro('busqueda', e.target.value)} />
        <Seleccion etiqueta="Rol" value={lista.filtros.rol_id}
          onChange={(e) => lista.cambiarFiltro('rol_id', e.target.value)} vacio="Todos"
          opciones={roles.map((r) => ({ valor: r.id, texto: r.nombre }))} />
        <Seleccion etiqueta="Estado" value={lista.filtros.activo}
          onChange={(e) => lista.cambiarFiltro('activo', e.target.value)} vacio="Todos"
          opciones={[{ valor: '1', texto: 'Activos' }, { valor: '0', texto: 'Inactivos' }]} />
        <Boton onClick={abrirPapelera}>Papelera</Boton>
        <Boton variante="primario" onClick={() => setEditando(null)}>+ Nuevo usuario</Boton>
      </div>

      <Tarjeta plana>
        {lista.error ? <div style={{ padding: 16 }}><Aviso tipo="error">{lista.error}</Aviso></div> : null}
        {lista.cargando ? <Cargando /> : (
          <>
            <div className="tabla-envoltura">
              <table className="tabla">
                <thead>
                  <tr>
                    <EncabezadoOrden texto="Nombre" campo="nombre" orden={lista.orden} direccion={lista.direccion} onOrdenar={lista.ordenar} />
                    <EncabezadoOrden texto="Usuario" campo="usuario" orden={lista.orden} direccion={lista.direccion} onOrdenar={lista.ordenar} />
                    <EncabezadoOrden texto="Correo" campo="correo" orden={lista.orden} direccion={lista.direccion} onOrdenar={lista.ordenar} />
                    <th>Documento</th>
                    <EncabezadoOrden texto="Rol" campo="rol" orden={lista.orden} direccion={lista.direccion} onOrdenar={lista.ordenar} />
                    <EncabezadoOrden texto="Último acceso" campo="acceso" orden={lista.orden} direccion={lista.direccion} onOrdenar={lista.ordenar} />
                    <th className="derecha">Sesiones</th>
                    <EncabezadoOrden texto="Estado" campo="estado" orden={lista.orden} direccion={lista.direccion} onOrdenar={lista.ordenar} />
                    <th />
                  </tr>
                </thead>
                <tbody>
                  {lista.items.length === 0 ? (
                    <tr><td colSpan={9}><div className="vacio">No hay usuarios con los filtros aplicados.</div></td></tr>
                  ) : lista.items.map((u) => (
                    <tr key={u.id}>
                      <td className="principal">
                        {u.nombre} {u.apellido}
                        {u.id === propio.id ? <span className="tenue"> (usted)</span> : null}
                      </td>
                      <td className="tabular">{u.usuario}</td>
                      <td className="tenue">{u.correo}</td>
                      <td className="tenue tabular">{u.tipo_documento} {u.documento}</td>
                      <td>{u.rol}</td>
                      <td className="tenue tabular">{u.ultimo_acceso ? fechaHora(u.ultimo_acceso) : 'Nunca'}</td>
                      <td className="derecha tabular">{u.sesiones_activas}</td>
                      <td>
                        {u.bloqueado
                          ? <EtiquetaEstado valor="Error" texto="Bloqueado" />
                          : <EtiquetaEstado valor={u.activo ? 'Activo' : 'Inactivo'} />}
                      </td>
                      <td style={{ textAlign: 'right', whiteSpace: 'nowrap' }}>
                        <Boton pequeno onClick={() => setEditando(u)}>Editar</Boton>
                        {u.bloqueado ? <Boton pequeno onClick={() => desbloquear(u)}>Desbloquear</Boton> : null}
                        {u.id !== propio.id ? (
                          <Boton pequeno variante="peligro" onClick={() => setBorrar(u)}>Eliminar</Boton>
                        ) : null}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
            <Paginacion pagina={lista.pagina} paginas={lista.paginas} total={lista.total}
              porPagina={lista.porPagina} onPagina={lista.setPagina} onPorPagina={lista.setPorPagina} />
          </>
        )}
      </Tarjeta>

      <Tarjeta titulo="Roles y permisos" sub="Definidos en la base de datos" plana>
        <Tabla
          columnas={[{ texto: 'Rol' }, { texto: 'Descripción' }, { texto: 'Usuarios', derecha: true }, { texto: 'Permisos', derecha: true }]}
          filas={roles}
          clave={(r) => r.id}
          render={(r) => (
            <>
              <td className="principal">{r.nombre}</td>
              <td className="tenue">{r.descripcion}</td>
              <td className="derecha tabular">{r.usuarios}</td>
              <td className="derecha tabular">{r.permisos}</td>
            </>
          )}
        />
      </Tarjeta>

      {editando !== undefined ? (
        <FormularioUsuario usuario={editando} roles={roles} avisar={avisar}
          onCerrar={() => setEditando(undefined)} onGuardado={lista.recargar} />
      ) : null}

      {borrar ? (
        <Modal titulo={'Eliminar ' + borrar.usuario} angosto onCerrar={() => setBorrar(null)}
          pie={<><Boton onClick={() => setBorrar(null)}>Cancelar</Boton><Boton variante="peligro" onClick={eliminar}>Eliminar</Boton></>}>
          <Aviso tipo="aviso-amarillo">
            La cuenta pasa a la papelera con borrado lógico y sus sesiones se cierran de
            inmediato. El historial de ventas y acciones del usuario se conserva.
          </Aviso>
        </Modal>
      ) : null}

      {papelera ? (
        <Modal titulo="Papelera de usuarios" onCerrar={() => setPapelera(null)}>
          <Tabla
            columnas={[{ texto: 'Usuario' }, { texto: 'Nombre' }, { texto: 'Correo' }, { texto: 'Eliminado' }, { texto: '' }]}
            filas={papelera}
            clave={(u) => u.id}
            vacio="La papelera está vacía."
            render={(u) => (
              <>
                <td className="tabular principal">{u.usuario}</td>
                <td>{u.nombre}</td>
                <td className="tenue">{u.correo}</td>
                <td className="tenue">{fechaHora(u.deleted_at)}</td>
                <td style={{ textAlign: 'right' }}>
                  <Boton pequeno onClick={() => restaurar(u.id)}>Restaurar</Boton>
                </td>
              </>
            )}
          />
        </Modal>
      ) : null}
    </>
  );
}

function Sesiones() {
  const [sesiones, setSesiones] = useState(null);
  const [error, setError] = useState('');

  const cargar = useCallback(() => {
    api.get('/sistema/sesiones').then(setSesiones).catch((e) => setError(e.message));
  }, []);

  useEffect(() => { cargar(); }, [cargar]);

  return (
    <>
      <div className="filtros">
        <Boton onClick={cargar}>Actualizar</Boton>
      </div>
      <Tarjeta plana>
        {error ? <div style={{ padding: 16 }}><Aviso tipo="error">{error}</Aviso></div> : null}
        {sesiones === null ? <Cargando /> : (
          <Tabla
            columnas={[
              { texto: 'Sesión' }, { texto: 'Usuario' }, { texto: 'Rol' }, { texto: 'IP' },
              { texto: 'Inicio' }, { texto: 'Última actividad' }, { texto: 'Expira' },
            ]}
            filas={sesiones}
            clave={(s) => s.id}
            vacio="No hay sesiones abiertas."
            render={(s) => (
              <>
                <td className="tabular tenue">{s.id}</td>
                <td className="principal">{s.nombre}</td>
                <td className="tenue">{s.rol}</td>
                <td className="tabular tenue">{s.ip}</td>
                <td className="tenue tabular">{fechaHora(s.creada_en)}</td>
                <td className="tenue tabular">{fechaHora(s.ultima_actividad)}</td>
                <td className="tabular">{fechaHora(s.expira_en)}</td>
              </>
            )}
          />
        )}
      </Tarjeta>
      <Aviso tipo="info">
        Las sesiones se cierran solas tras 15 minutos sin actividad. Al desactivar o
        eliminar una cuenta, sus sesiones se cierran de inmediato.
      </Aviso>
    </>
  );
}

function Respaldos() {
  const { avisar } = useSesion();
  const [datos, setDatos] = useState(null);
  const [generando, setGenerando] = useState(false);
  const [error, setError] = useState('');

  const cargar = useCallback(() => {
    api.get('/sistema/respaldos').then(setDatos).catch((e) => setError(e.message));
  }, []);

  useEffect(() => { cargar(); }, [cargar]);

  const generar = async () => {
    setGenerando(true);
    try {
      const r = await api.post('/sistema/respaldos', { tipo: 'Manual' });
      avisar('Respaldo ' + r.nombre_archivo + ' generado');
      cargar();
    } catch (e) {
      avisar(e.message, 'error');
    } finally {
      setGenerando(false);
    }
  };

  const tamano = (bytes) => {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / 1024 / 1024).toFixed(2) + ' MB';
  };

  return (
    <>
      <div className="filtros">
        <Boton variante="primario" onClick={generar} disabled={generando}>
          {generando ? 'Generando...' : 'Generar respaldo ahora'}
        </Boton>
        <Boton onClick={cargar}>Actualizar</Boton>
      </div>

      {error ? <Aviso tipo="error">{error}</Aviso> : null}

      {datos ? (
        <Aviso tipo={datos.pendiente ? 'aviso-amarillo' : 'exito'}>
          {datos.pendiente
            ? 'Han pasado más de 24 horas desde el último respaldo completado.'
            : 'El respaldo automático de las últimas 24 horas está al día.'}
        </Aviso>
      ) : null}

      <Tarjeta plana>
        {datos === null ? <Cargando /> : (
          <Tabla
            columnas={[
              { texto: 'Archivo' }, { texto: 'Tipo' }, { texto: 'Tamano', derecha: true },
              { texto: 'Inicio' }, { texto: 'Fin' }, { texto: 'Estado' }, { texto: '' },
            ]}
            filas={datos.respaldos}
            clave={(r) => r.id}
            vacio="Todavía no se ha generado ningún respaldo."
            render={(r) => (
              <>
                <td className="principal tabular">{r.nombre_archivo}</td>
                <td className="tenue">{r.tipo}</td>
                <td className="derecha tabular">{tamano(r.tamano_bytes)}</td>
                <td className="tenue tabular">{fechaHora(r.iniciado_en)}</td>
                <td className="tenue tabular">{r.finalizado_en ? fechaHora(r.finalizado_en) : '—'}</td>
                <td>
                  <EtiquetaEstado
                    valor={r.estado === 'Completado' ? 'Completada' : r.estado === 'Fallido' ? 'Error' : 'Pendiente'}
                    texto={r.estado}
                  />
                </td>
                <td style={{ textAlign: 'right' }}>
                  {r.estado === 'Completado' ? (
                    <Boton pequeno onClick={() => window.open(
                      (window.KERICO_API || '/api') + '/sistema/respaldos/' + r.id + '/descargar', '_blank'
                    )}>Descargar</Boton>
                  ) : null}
                </td>
              </>
            )}
          />
        )}
      </Tarjeta>

      <Aviso tipo="info">
        El respaldo genera un archivo SQL completo con todas las tablas. Para automatizarlo
        cada 24 horas, programe la tarea <strong>backend/tareas/respaldo.php</strong> en el
        Programador de tareas de Windows.
      </Aviso>
    </>
  );
}

function Configuracion() {
  const { avisar } = useSesion();
  const [valores, setValores] = useState(null);
  const [editados, setEditados] = useState({});
  const [guardando, setGuardando] = useState(false);
  const [error, setError] = useState('');

  useEffect(() => {
    api.get('/sistema/configuracion').then(setValores).catch((e) => setError(e.message));
  }, []);

  const guardar = async () => {
    setGuardando(true);
    try {
      const actualizados = await api.put('/sistema/configuracion', editados);
      setValores(actualizados);
      setEditados({});
      avisar('Configuración actualizada');
    } catch (e) {
      avisar(e.message, 'error');
    } finally {
      setGuardando(false);
    }
  };

  if (error) return <Aviso tipo="error">{error}</Aviso>;
  if (!valores) return <Cargando />;

  const grupos = {
    'Datos de la empresa': valores.filter((v) => v.clave.startsWith('empresa_')),
    'Facturacion': valores.filter((v) => v.clave.startsWith('comprobante_') || v.clave === 'resolucion_dian' || v.clave.startsWith('impuesto_')),
    'Seguridad y sesiones': valores.filter((v) => v.clave.startsWith('sesion_') || v.clave.startsWith('intentos_') || v.clave.startsWith('bloqueo_')),
    'Inventario y respaldos': valores.filter((v) => v.clave.startsWith('alerta_') || v.clave.startsWith('respaldo_')),
    'Moneda': valores.filter((v) => v.clave.startsWith('moneda_')),
  };

  return (
    <>
      <div className="filtros">
        <Boton variante="primario" onClick={guardar} disabled={guardando || Object.keys(editados).length === 0}>
          {guardando ? 'Guardando...' : 'Guardar cambios'}
        </Boton>
        {Object.keys(editados).length > 0 ? (
          <Boton onClick={() => setEditados({})}>Descartar</Boton>
        ) : null}
      </div>

      {Object.entries(grupos).map(([titulo, campos]) => (
        campos.length === 0 ? null : (
          <Tarjeta key={titulo} titulo={titulo}>
            <div className="rejilla-formulario">
              {campos.map((c) => (
                <Entrada
                  key={c.clave}
                  etiqueta={c.clave.replace(/_/g, ' ')}
                  value={editados[c.clave] !== undefined ? editados[c.clave] : c.valor}
                  onChange={(e) => setEditados((v) => ({ ...v, [c.clave]: e.target.value }))}
                  type={c.tipo === 'entero' || c.tipo === 'decimal' ? 'number' : 'text'}
                  ancho={c.valor.length > 60}
                />
              ))}
            </div>
          </Tarjeta>
        )
      ))}
    </>
  );
}

export default function Administracion() {
  const [pestana, setPestana] = useState('usuarios');

  return (
    <>
      <div className="encabezado">
        <div>
          <h1>Administración</h1>
          <p>Usuarios, sesiones, respaldos y parámetros del sistema.</p>
        </div>
      </div>

      <div className="panel">
        <Pestanas
          opciones={[
            { id: 'usuarios', texto: 'Usuarios y roles' },
            { id: 'sesiones', texto: 'Sesiones activas' },
            { id: 'respaldos', texto: 'Respaldos' },
            { id: 'configuracion', texto: 'Configuración' },
          ]}
          activa={pestana}
          onCambiar={setPestana}
        />

        {pestana === 'usuarios' ? <Usuarios /> : null}
        {pestana === 'sesiones' ? <Sesiones /> : null}
        {pestana === 'respaldos' ? <Respaldos /> : null}
        {pestana === 'configuracion' ? <Configuracion /> : null}
      </div>
    </>
  );
}
