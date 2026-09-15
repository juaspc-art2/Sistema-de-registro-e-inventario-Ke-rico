<?php

declare(strict_types=1);

namespace Kerico\Nucleo;

final class Bitacora
{
    private static string $ip = '';
    private static string $agente = '';

    public static function contexto(string $ip, string $agente): void
    {
        self::$ip = $ip;
        self::$agente = $agente;
    }

    public static function registrar(
        string $accion,
        string $modulo,
        string $descripcion,
        string $nivel = 'Info',
        string $entidad = '',
        $entidadId = '',
        ?array $antes = null,
        ?array $despues = null,
        ?array $usuario = null
    ): void {
        $usuario = $usuario ?? Autenticacion::usuarioActual();
        $nombre = $usuario === null
            ? 'Sistema'
            : trim(($usuario['nombre'] ?? '') . ' ' . ($usuario['apellido'] ?? ''));

        Bd::ejecutar(
            'INSERT INTO bitacora
                (usuario_id, usuario_nombre, accion, modulo, descripcion, nivel, entidad, entidad_id,
                 datos_antes, datos_despues, ip, user_agent)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
            [
                $usuario['id'] ?? null,
                $nombre === '' ? 'Sistema' : $nombre,
                $accion,
                $modulo,
                mb_substr($descripcion, 0, 255),
                $nivel,
                mb_substr($entidad, 0, 40),
                mb_substr((string) $entidadId, 0, 40),
                $antes === null ? null : json_encode($antes, JSON_UNESCAPED_UNICODE),
                $despues === null ? null : json_encode($despues, JSON_UNESCAPED_UNICODE),
                self::$ip,
                self::$agente,
            ]
        );
    }
}
