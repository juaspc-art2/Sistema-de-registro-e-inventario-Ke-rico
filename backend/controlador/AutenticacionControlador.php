<?php

declare(strict_types=1);

namespace Kerico\Controlador;

use Kerico\Modelo\Usuario;
use Kerico\Nucleo\Ajustes;
use Kerico\Nucleo\Autenticacion;
use Kerico\Nucleo\Peticion;
use Kerico\Nucleo\Respuesta;
use Kerico\Nucleo\Validador;

final class AutenticacionControlador
{
    public static function ingresar(Peticion $peticion): void
    {
        $validador = new Validador($peticion->cuerpo());
        $usuario = $validador->texto('usuario', 'El usuario', true, 120);
        $password = $validador->texto('password', 'La contraseña', true, 120);
        $validador->validar();

        $sesion = Autenticacion::iniciarSesion($usuario, $password, $peticion->ip(), $peticion->agente());
        Respuesta::exito($sesion, 'Bienvenido a Ke-Rico!');
    }

    public static function salir(Peticion $peticion): void
    {
        $token = Autenticacion::tokenActual();
        if ($token !== null) {
            Autenticacion::cerrarSesion($token, 'Manual');
        }
        Respuesta::exito(null, 'Sesión cerrada correctamente');
    }

    public static function sesion(Peticion $peticion): void
    {
        Respuesta::exito([
            'usuario'        => Autenticacion::usuarioActual(),
            'csrf_token'     => Autenticacion::csrfActual(),
            'permisos'       => Autenticacion::permisos(),
            'minutos_sesion' => (int) Ajustes::obtener('sesion_minutos_inactividad', 15),
            'empresa'        => Ajustes::emisor(),
        ]);
    }

    public static function cambiarPassword(Peticion $peticion): void
    {
        $validador = new Validador($peticion->cuerpo());
        $actual = $validador->texto('password_actual', 'La contraseña actual', true, 120);
        $nueva = $validador->texto('password_nueva', 'La nueva contraseña', true, 120, 3);
        $validador->validar();

        Usuario::cambiarPassword(Autenticacion::idUsuario(), $actual, $nueva);
        Respuesta::exito(null, 'Contraseña actualizada correctamente');
    }

    public static function recuperar(Peticion $peticion): void
    {
        $validador = new Validador($peticion->cuerpo());
        $correo = $validador->correo('correo', 'El correo', true);
        $validador->validar();

        $resultado = Usuario::solicitarRecuperacion($correo);
        Respuesta::exito(
            $resultado,
            'Si el correo está registrado se generó un enlace de recuperación válido por una hora'
        );
    }

    public static function restablecer(Peticion $peticion): void
    {
        $validador = new Validador($peticion->cuerpo());
        $token = $validador->texto('token', 'El token', true, 64);
        $password = $validador->texto('password', 'La nueva contraseña', true, 120, 3);
        $validador->validar();

        Usuario::restablecerConToken($token, $password);
        Respuesta::exito(null, 'Contraseña restablecida. Ya puede iniciar sesión.');
    }
}
