<?php

declare(strict_types=1);

namespace Kerico\Nucleo;

final class Respuesta
{
    private static string $correoDestino = '';
    private static array $cuerpoCorreo = [];

    public static function json($datos, int $codigo = 200, array $cabeceras = []): void
    {
        http_response_code($codigo);
        header('Content-Type: application/json; charset=utf-8');
        foreach ($cabeceras as $nombre => $valor) {
            header($nombre . ': ' . $valor);
        }
        echo json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public static function exito($datos = null, string $mensaje = '', int $codigo = 200): void
    {
        $cuerpo = ['ok' => true];
        if ($mensaje !== '') {
            $cuerpo['mensaje'] = $mensaje;
        }
        if ($datos !== null) {
            $cuerpo['datos'] = $datos;
        }
        self::json($cuerpo, $codigo);
    }

    public static function error(string $mensaje, int $codigo = 400, array $detalles = []): void
    {
        $cuerpo = ['ok' => false, 'mensaje' => $mensaje];
        if ($detalles !== []) {
            $cuerpo['errores'] = $detalles;
        }
        self::json($cuerpo, $codigo);
    }

    // Con la cabecera X-Enviar-A, todo lo que se puede descargar se envía a ese correo
    public static function entregarPorCorreo(string $correo): void
    {
        self::$correoDestino = trim($correo);
    }

    // Texto propio para el correo que acompaña al archivo (bloques de Correo::enviar), cuando el genérico no sirve
    public static function redactarCorreo(array $cuerpo): void
    {
        self::$cuerpoCorreo = $cuerpo;
    }

    // $descripcion nombra el contenido en el correo; en la descarga no se usa
    public static function archivo(string $contenido, string $nombre, string $tipo, string $descripcion = ''): void
    {
        $nombre = preg_replace('/[^A-Za-z0-9._-]+/', '_', $nombre) ?? 'archivo';

        if (self::$correoDestino !== '') {
            $usuario = Autenticacion::usuarioActual() ?? [];
            Correo::enviar(
                self::$correoDestino,
                $descripcion !== '' ? $descripcion : $nombre,
                self::$cuerpoCorreo ?: [
                    'Hola,',
                    'Adjunto encontrará el archivo que solicitó.',
                    ['datos' => array_filter(['Contenido' => $descripcion, 'Archivo' => $nombre])],
                ],
                ['nombre' => $nombre, 'tipo' => $tipo, 'contenido' => $contenido],
                [
                    'nombre' => trim(($usuario['nombre'] ?? '') . ' ' . ($usuario['apellido'] ?? '')),
                    'cargo'  => $usuario['cargo'] ?? '',
                ]
            );
            Bitacora::registrar(
                'ARCHIVO_ENVIAR',
                'Sistema',
                'Archivo ' . $nombre . ' enviado por correo a ' . self::$correoDestino,
                'Info',
                'correo',
                ''
            );
            self::exito(
                ['archivo' => $nombre, 'correo' => self::$correoDestino],
                'Archivo ' . $nombre . ' enviado a ' . self::$correoDestino
            );
            return;
        }

        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        http_response_code(200);
        header('Content-Type: ' . $tipo);
        header('Content-Disposition: attachment; filename="' . $nombre . '"');
        header('Content-Length: ' . strlen($contenido));
        header('X-Nombre-Archivo: ' . $nombre);
        echo $contenido;
    }

    // Entrega ya la respuesta y deja vivo el proceso para lo que el cliente no debe esperar
    public static function cerrar(): void
    {
        ignore_user_abort(true);
        if (ob_get_level() > 0) {
            header('Content-Length: ' . ob_get_length());
            header('Connection: close');
            while (ob_get_level() > 0) {
                ob_end_flush();
            }
        }
        flush();
    }
}
