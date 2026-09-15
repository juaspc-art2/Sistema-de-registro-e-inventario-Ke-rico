<?php

declare(strict_types=1);

namespace Kerico\Modelo;

use Kerico\Nucleo\Autenticacion;
use Kerico\Nucleo\Bd;
use Kerico\Nucleo\Bitacora;
use Kerico\Nucleo\ErrorHttp;

final class Alerta
{
    public static function listar(array $filtros = []): array
    {
        $sql = 'SELECT a.*, p.sku, p.nombre AS producto, p.unidad_medida, p.stock_actual,
                       c.nombre AS categoria, pr.nombre_empresa AS proveedor,
                       CONCAT(u.nombre, " ", u.apellido) AS visto_por
                FROM alertas_stock a
                JOIN productos p ON p.id = a.producto_id
                JOIN categorias c ON c.id = p.categoria_id
                LEFT JOIN proveedores pr ON pr.id = p.proveedor_id
                LEFT JOIN usuarios u ON u.id = a.vista_por
                WHERE 1 = 1';
        $parametros = [];

        if (!empty($filtros['estado'])) {
            $sql .= ' AND a.estado = ?';
            $parametros[] = $filtros['estado'];
        }
        if (!empty($filtros['tipo'])) {
            $sql .= ' AND a.tipo = ?';
            $parametros[] = $filtros['tipo'];
        }
        if (isset($filtros['pendientes']) && $filtros['pendientes']) {
            $sql .= " AND a.estado <> 'Resuelta'";
        }

        $sql .= " ORDER BY FIELD(a.tipo, 'Agotado', 'Critico', 'Bajo'), a.generada_en DESC LIMIT 300";

        return array_map(static fn (array $a): array => [
            'id'              => (int) $a['id'],
            'producto_id'     => (int) $a['producto_id'],
            'sku'             => (string) $a['sku'],
            'producto'        => (string) $a['producto'],
            'categoria'       => (string) $a['categoria'],
            'proveedor'       => (string) ($a['proveedor'] ?? ''),
            'unidad_medida'   => (string) $a['unidad_medida'],
            'tipo'            => (string) $a['tipo'],
            'stock_en_alerta' => (float) $a['stock_en_alerta'],
            'stock_actual'    => (float) $a['stock_actual'],
            'punto_reorden'   => (float) $a['punto_reorden'],
            'mensaje'         => (string) $a['mensaje'],
            'estado'          => (string) $a['estado'],
            'generada_en'     => (string) $a['generada_en'],
            'vista_en'        => $a['vista_en'],
            'visto_por'       => (string) ($a['visto_por'] ?? ''),
        ], Bd::consultar($sql, $parametros));
    }

    public static function resumen(): array
    {
        $filas = Bd::consultar(
            "SELECT estado_stock, COUNT(*) AS n FROM v_estado_inventario WHERE activo = 1 GROUP BY estado_stock"
        );

        $resumen = ['Agotado' => 0, 'Critico' => 0, 'Bajo' => 0, 'Disponible' => 0];
        foreach ($filas as $fila) {
            $resumen[$fila['estado_stock']] = (int) $fila['n'];
        }

        $resumen['pendientes'] = (int) Bd::valor("SELECT COUNT(*) FROM alertas_stock WHERE estado = 'Pendiente'");
        $resumen['valor_inventario'] = (float) Bd::valor('SELECT COALESCE(SUM(valor_inventario), 0) FROM v_estado_inventario WHERE activo = 1');

        return $resumen;
    }

    public static function marcarVista(int $id): array
    {
        $alerta = Bd::primero('SELECT * FROM alertas_stock WHERE id = ?', [$id]);
        if ($alerta === null) {
            throw ErrorHttp::noEncontrado('La alerta no existe');
        }
        if ($alerta['estado'] === 'Pendiente') {
            Bd::ejecutar(
                "UPDATE alertas_stock SET estado = 'Vista', vista_en = NOW(), vista_por = ? WHERE id = ?",
                [Autenticacion::idUsuario(), $id]
            );
        }
        $filas = self::listar([]);
        foreach ($filas as $fila) {
            if ($fila['id'] === $id) {
                return $fila;
            }
        }
        return [];
    }

    public static function marcarTodasVistas(): int
    {
        $filas = Bd::ejecutar(
            "UPDATE alertas_stock SET estado = 'Vista', vista_en = NOW(), vista_por = ?
             WHERE estado = 'Pendiente'",
            [Autenticacion::idUsuario()]
        );

        if ($filas > 0) {
            Bitacora::registrar(
                'ALERTA_REVISAR',
                'Alertas',
                $filas . ' alerta(s) de stock marcadas como vistas',
                'Info',
                'alertas_stock',
                ''
            );
        }

        return $filas;
    }
}
