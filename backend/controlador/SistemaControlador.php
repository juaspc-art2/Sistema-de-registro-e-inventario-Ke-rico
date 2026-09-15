<?php

declare(strict_types=1);

namespace Kerico\Controlador;

use Kerico\Modelo\Sistema;
use Kerico\Modelo\Usuario;
use Kerico\Nucleo\Ajustes;
use Kerico\Nucleo\Autenticacion;
use Kerico\Nucleo\Paginador;
use Kerico\Nucleo\Peticion;
use Kerico\Nucleo\Respuesta;
use Kerico\Nucleo\Validador;

final class SistemaControlador
{
    public static function bitacora(Peticion $peticion): void
    {
        Autenticacion::exigir('bitacora.ver');
        Respuesta::exito([
            'resumen'   => Sistema::resumenBitacora(),
            'registros' => Sistema::paginarBitacora(Paginador::desdePeticion($peticion, [
                'busqueda'   => $peticion->consulta('busqueda', ''),
                'nivel'      => $peticion->consulta('nivel', ''),
                'modulo'     => $peticion->consulta('modulo', ''),
                'usuario_id' => $peticion->consulta('usuario_id', ''),
                'desde'      => $peticion->consulta('desde', ''),
                'hasta'      => $peticion->consulta('hasta', ''),
            ])),
        ]);
    }

    public static function exportarBitacora(Peticion $peticion): void
    {
        Autenticacion::exigir('bitacora.ver');

        $formato = (string) $peticion->consulta('formato', 'pdf');
        $desde = (string) $peticion->consulta('desde', date('Y-m-01'));
        $hasta = (string) $peticion->consulta('hasta', date('Y-m-d'));

        $registros = Sistema::bitacora([
            'desde'  => $desde,
            'hasta'  => $hasta,
            'nivel'  => $peticion->consulta('nivel', ''),
            'modulo' => $peticion->consulta('modulo', ''),
            'limite' => 2000,
        ]);

        $encabezados = ['Fecha', 'Usuario', 'Modulo', 'Accion', 'Nivel', 'Descripcion', 'IP'];
        $filas = array_map(static fn (array $r): array => [
            $r['fecha'],
            $r['usuario'],
            $r['modulo'],
            $r['accion'],
            $r['nivel'],
            $r['descripcion'],
            $r['ip'],
        ], $registros);

        Exportacion::enviar(
            $formato,
            'bitacora-' . $desde . '-a-' . $hasta,
            'Registro de actividad',
            'Del ' . $desde . ' al ' . $hasta,
            $encabezados,
            $filas,
            ['Eventos' => (string) count($registros), 'Periodo' => $desde . ' a ' . $hasta]
        );
    }

    public static function usuarios(Peticion $peticion): void
    {
        Autenticacion::exigir('usuarios.gestionar');
        Respuesta::exito([
            'usuarios' => Usuario::paginar(Paginador::desdePeticion($peticion, [
                'busqueda' => $peticion->consulta('busqueda', ''),
                'rol_id'   => $peticion->consulta('rol_id', ''),
                'activo'   => $peticion->consulta('activo', ''),
            ])),
            'roles'    => Usuario::roles(),
        ]);
    }

    private static function datosUsuario(Peticion $peticion, bool $creando): array
    {
        $validador = new Validador($peticion->cuerpo());
        $datos = [
            'usuario'        => $validador->texto('usuario', 'El usuario', true, 40, 3),
            'nombre'         => $validador->texto('nombre', 'El nombre', true, 80, 2),
            'apellido'       => $validador->texto('apellido', 'El apellido', true, 80, 2),
            'tipo_documento' => $validador->opcion('tipo_documento', 'El tipo de documento', ['CC', 'TI', 'CE', 'PA', 'NIT'], false, 'CC'),
            'documento'      => $validador->texto('documento', 'El documento', true, 30, 5),
            'fecha_nac'      => $validador->fecha('fecha_nac', 'La fecha de nacimiento', false),
            'correo'         => $validador->correo('correo', 'El correo', true),
            'telefono'       => $validador->texto('telefono', 'El telefono', false, 30),
            'cargo'          => $validador->texto('cargo', 'El cargo', false, 80),
            'rol_id'         => $validador->entero('rol_id', 'El rol', true, 1),
            'activo'         => $validador->booleano('activo', true),
        ];

        $password = $validador->texto('password', 'La contraseña', $creando, 120, $creando ? 3 : 0);
        if ($password !== '' && strlen($password) < 3) {
            $validador->agregarError('password', 'La contraseña debe tener al menos 3 caracteres');
        }
        $datos['password'] = $password;

        $validador->validar();
        return $datos;
    }

    public static function crearUsuario(Peticion $peticion): void
    {
        Autenticacion::exigir('usuarios.gestionar');
        Respuesta::exito(Usuario::crear(self::datosUsuario($peticion, true)), 'Usuario creado correctamente', 201);
    }

    public static function actualizarUsuario(Peticion $peticion): void
    {
        Autenticacion::exigir('usuarios.gestionar');
        Respuesta::exito(
            Usuario::actualizar((int) $peticion->parametro('id'), self::datosUsuario($peticion, false)),
            'Usuario actualizado correctamente'
        );
    }

    public static function desbloquearUsuario(Peticion $peticion): void
    {
        Autenticacion::exigir('usuarios.gestionar');
        Respuesta::exito(Usuario::desbloquear((int) $peticion->parametro('id')), 'Cuenta desbloqueada');
    }

    public static function sesiones(Peticion $peticion): void
    {
        Autenticacion::exigir('usuarios.gestionar');
        Autenticacion::purgarSesiones();
        Respuesta::exito(Usuario::sesionesActivas());
    }

    public static function estado(Peticion $peticion): void
    {
        Respuesta::exito(Sistema::estado());
    }

    public static function configuracion(Peticion $peticion): void
    {
        Autenticacion::exigir('configuracion.editar');
        Respuesta::exito(Ajustes::todos());
    }

    public static function guardarConfiguracion(Peticion $peticion): void
    {
        Autenticacion::exigir('configuracion.editar');
        Respuesta::exito(Sistema::actualizarConfiguracion($peticion->cuerpo()), 'Configuración actualizada');
    }

    public static function respaldos(Peticion $peticion): void
    {
        Autenticacion::exigir('respaldos.gestionar');
        Respuesta::exito([
            'pendiente' => Sistema::respaldoPendiente(),
            'respaldos' => Sistema::respaldos(),
        ]);
    }

    public static function generarRespaldo(Peticion $peticion): void
    {
        Autenticacion::exigir('respaldos.gestionar');
        $tipo = $peticion->entrada('tipo') === 'Automatico' ? 'Automatico' : 'Manual';
        Respuesta::exito(Sistema::generarRespaldo($tipo), 'Respaldo generado correctamente', 201);
    }

    public static function descargarRespaldo(Peticion $peticion): void
    {
        Autenticacion::exigir('respaldos.gestionar');
        $archivo = Sistema::descargarRespaldo((int) $peticion->parametro('id'));
        Respuesta::archivo($archivo['contenido'], $archivo['nombre'], 'application/sql');
    }

    public static function eliminarUsuario(Peticion $peticion): void
    {
        Autenticacion::exigir('usuarios.gestionar');
        Usuario::eliminar((int) $peticion->parametro('id'));
        Respuesta::exito(null, 'Usuario enviado a la papelera');
    }

    public static function restaurarUsuario(Peticion $peticion): void
    {
        Autenticacion::exigir('usuarios.gestionar');
        Respuesta::exito(
            Usuario::restaurar((int) $peticion->parametro('id')),
            'Usuario restaurado desde la papelera'
        );
    }

    public static function papeleraUsuarios(Peticion $peticion): void
    {
        Autenticacion::exigir('usuarios.gestionar');
        Respuesta::exito(Usuario::papelera());
    }

}
