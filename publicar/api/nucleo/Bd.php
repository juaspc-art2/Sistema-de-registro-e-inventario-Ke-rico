<?php

declare(strict_types=1);

namespace Kerico\Nucleo;

use PDO;
use PDOException;
use RuntimeException;

final class Bd
{
    private static ?PDO $conexion = null;
    private static array $config = [];

    public static function configurar(array $config): void
    {
        self::$config = $config;
    }

    public static function conexion(): PDO
    {
        if (self::$conexion instanceof PDO) {
            return self::$conexion;
        }

        $c = self::$config;
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $c['host'],
            $c['puerto'],
            $c['nombre'],
            $c['charset']
        );

        try {
            self::$conexion = new PDO($dsn, $c['usuario'], $c['password'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_STRINGIFY_FETCHES  => false,
            ]);
        } catch (PDOException $e) {
            throw new RuntimeException('No fue posible conectar con la base de datos: ' . $e->getMessage(), 500, $e);
        }

        return self::$conexion;
    }

    public static function consultar(string $sql, array $parametros = []): array
    {
        $sentencia = self::conexion()->prepare($sql);
        $sentencia->execute($parametros);
        return $sentencia->fetchAll();
    }

    public static function primero(string $sql, array $parametros = []): ?array
    {
        $sentencia = self::conexion()->prepare($sql);
        $sentencia->execute($parametros);
        $fila = $sentencia->fetch();
        return $fila === false ? null : $fila;
    }

    public static function valor(string $sql, array $parametros = [])
    {
        $sentencia = self::conexion()->prepare($sql);
        $sentencia->execute($parametros);
        $valor = $sentencia->fetchColumn();
        return $valor === false ? null : $valor;
    }

    public static function ejecutar(string $sql, array $parametros = []): int
    {
        $sentencia = self::conexion()->prepare($sql);
        $sentencia->execute($parametros);
        return $sentencia->rowCount();
    }

    public static function insertar(string $sql, array $parametros = []): int
    {
        self::ejecutar($sql, $parametros);
        return (int) self::conexion()->lastInsertId();
    }

    public static function transaccion(callable $operacion)
    {
        $pdo = self::conexion();
        if ($pdo->inTransaction()) {
            return $operacion($pdo);
        }

        $pdo->beginTransaction();
        try {
            $resultado = $operacion($pdo);
            $pdo->commit();
            return $resultado;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
