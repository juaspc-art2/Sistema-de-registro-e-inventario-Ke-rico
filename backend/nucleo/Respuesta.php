<?php

declare(strict_types=1);

namespace Kerico\Nucleo;

final class Respuesta
{
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

    public static function archivo(string $contenido, string $nombre, string $tipo): void
    {
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
}
