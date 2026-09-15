<?php

declare(strict_types=1);

namespace Kerico\Nucleo;

final class Ajustes
{
    private static array $cache = [];

    private static function cargar(): array
    {
        if (self::$cache === []) {
            foreach (Bd::consultar('SELECT clave, valor, tipo, descripcion FROM configuracion') as $fila) {
                self::$cache[$fila['clave']] = $fila;
            }
        }
        return self::$cache;
    }

    public static function obtener(string $clave, $porDefecto = null)
    {
        $todos = self::cargar();
        if (!isset($todos[$clave])) {
            return $porDefecto;
        }
        $fila = $todos[$clave];
        return match ($fila['tipo']) {
            'entero'   => (int) $fila['valor'],
            'decimal'  => (float) $fila['valor'],
            'booleano' => in_array(strtolower((string) $fila['valor']), ['1', 'true', 'si'], true),
            'json'     => json_decode((string) $fila['valor'], true),
            default    => $fila['valor'],
        };
    }

    public static function definir(string $clave, string $valor): void
    {
        Bd::ejecutar(
            'INSERT INTO configuracion (clave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)',
            [$clave, $valor]
        );
        self::$cache = [];
    }

    public static function todos(): array
    {
        $salida = [];
        foreach (self::cargar() as $clave => $fila) {
            $salida[] = [
                'clave'       => $clave,
                'valor'       => $fila['valor'],
                'tipo'        => $fila['tipo'],
                'descripcion' => (string) ($fila['descripcion'] ?? ''),
            ];
        }
        return $salida;
    }

    public static function emisor(): array
    {
        return [
            'nombre'       => (string) self::obtener('empresa_nombre', 'Ke-Rico!'),
            'razon_social' => (string) self::obtener('empresa_razon_social', 'Ke-Rico! S.A.S.'),
            'nit'          => (string) self::obtener('empresa_nit', ''),
            'direccion'    => (string) self::obtener('empresa_direccion', ''),
            'telefono'     => (string) self::obtener('empresa_telefono', ''),
            'correo'       => (string) self::obtener('empresa_correo', ''),
            'ciudad'       => (string) self::obtener('empresa_ciudad', ''),
        ];
    }

    public static function siguienteConsecutivo(): int
    {
        return Bd::transaccion(static function () {
            $fila = Bd::primero("SELECT valor FROM configuracion WHERE clave = 'comprobante_consecutivo' FOR UPDATE");
            $actual = $fila === null ? 1 : (int) $fila['valor'];
            Bd::ejecutar(
                "UPDATE configuracion SET valor = ? WHERE clave = 'comprobante_consecutivo'",
                [(string) ($actual + 1)]
            );
            self::$cache = [];
            return $actual;
        });
    }
}
