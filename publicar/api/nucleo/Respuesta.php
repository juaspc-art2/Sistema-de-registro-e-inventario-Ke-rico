<?php

declare(strict_types=1);

namespace Kerico\Nucleo;

final class Respuesta
{
    // Archivos que un usuario puede enviar por correo en una hora. El correo sale por la cuenta del negocio,
    // que tiene un cupo diario: sin tope, una sola persona podía agotarlo y dejar al sistema sin avisos
    private const CORREOS_POR_HORA = 30;

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
            $recientes = (int) Bd::valor(
                "SELECT COUNT(*) FROM bitacora WHERE accion = 'ARCHIVO_ENVIAR' AND usuario_id = ? AND creado_en >= ?",
                [$usuario['id'] ?? 0, date('Y-m-d H:i:s', time() - 3600)]
            );
            if ($recientes >= self::CORREOS_POR_HORA) {
                throw new ErrorHttp(
                    'Ya envió ' . self::CORREOS_POR_HORA . ' archivos por correo en la última hora. Espere un momento o descargue el archivo.',
                    429
                );
            }
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

    // Igual que archivo(), para uno que ya está en disco: se entrega por partes, sin cargarlo entero en memoria
    public static function archivoEnDisco(string $ruta, string $nombre, string $tipo, string $descripcion = ''): void
    {
        if (self::$correoDestino !== '') {
            Correo::exigirTamano((int) filesize($ruta));
            self::archivo((string) file_get_contents($ruta), $nombre, $tipo, $descripcion);
            return;
        }

        $nombre = preg_replace('/[^A-Za-z0-9._-]+/', '_', $nombre) ?? 'archivo';
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        http_response_code(200);
        header('Content-Type: ' . $tipo);
        header('Content-Disposition: attachment; filename="' . $nombre . '"');
        header('Content-Length: ' . filesize($ruta));
        header('X-Nombre-Archivo: ' . $nombre);
        readfile($ruta);
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
