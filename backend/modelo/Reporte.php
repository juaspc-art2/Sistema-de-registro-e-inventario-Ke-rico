<?php

declare(strict_types=1);

namespace Kerico\Modelo;

use Kerico\Nucleo\Bd;

final class Reporte
{
    public static function rango(array $filtros): array
    {
        $desde = $filtros['desde'] ?? date('Y-m-01');
        $hasta = $filtros['hasta'] ?? date('Y-m-d');
        return [$desde . ' 00:00:00', $hasta . ' 23:59:59', $desde, $hasta];
    }

    public static function rentabilidad(array $filtros = []): array
    {
        [$desde, $hasta, $d, $h] = self::rango($filtros);

        $totales = Bd::primero(
            "SELECT
                COALESCE(SUM(v.total), 0) AS ingresos_brutos,
                COALESCE(SUM(v.base_gravable), 0) AS base_gravable,
                COALESCE(SUM(v.impuesto_total), 0) AS impuestos,
                COALESCE(SUM(v.descuento_total), 0) AS descuentos,
                COALESCE(SUM(v.costo_total), 0) AS costo_ventas,
                COUNT(*) AS transacciones
             FROM ventas v
             WHERE v.estado = 'Completada' AND v.fecha BETWEEN ? AND ?",
            [$desde, $hasta]
        );

        $ingresos = (float) $totales['ingresos_brutos'];
        $base = (float) $totales['base_gravable'];
        $costo = (float) $totales['costo_ventas'];
        $utilidad = round($base - $costo, 2);
        $margen = $base > 0 ? round(($utilidad / $base) * 100, 2) : 0.0;
        $transacciones = (int) $totales['transacciones'];

        $porProducto = Bd::consultar(
            "SELECT d.producto_id, d.sku, d.nombre_producto AS nombre, c.nombre AS categoria,
                    SUM(d.cantidad) AS unidades,
                    SUM(d.subtotal) AS ingresos,
                    SUM(d.costo_unitario * d.cantidad) AS costo,
                    SUM(d.subtotal) - SUM(d.costo_unitario * d.cantidad) AS utilidad
             FROM venta_detalle d
             JOIN ventas v ON v.id = d.venta_id
             JOIN productos p ON p.id = d.producto_id
             JOIN categorias c ON c.id = p.categoria_id
             WHERE v.estado = 'Completada' AND v.fecha BETWEEN ? AND ?
             GROUP BY d.producto_id, d.sku, d.nombre_producto, c.nombre
             ORDER BY utilidad DESC",
            [$desde, $hasta]
        );

        $porCategoria = Bd::consultar(
            "SELECT c.nombre AS categoria,
                    SUM(d.cantidad) AS unidades,
                    SUM(d.subtotal) AS ingresos,
                    SUM(d.costo_unitario * d.cantidad) AS costo,
                    SUM(d.subtotal) - SUM(d.costo_unitario * d.cantidad) AS utilidad
             FROM venta_detalle d
             JOIN ventas v ON v.id = d.venta_id
             JOIN productos p ON p.id = d.producto_id
             JOIN categorias c ON c.id = p.categoria_id
             WHERE v.estado = 'Completada' AND v.fecha BETWEEN ? AND ?
             GROUP BY c.nombre
             ORDER BY utilidad DESC",
            [$desde, $hasta]
        );

        $porDia = Bd::consultar(
            "SELECT DATE(v.fecha) AS dia,
                    COUNT(*) AS transacciones,
                    SUM(v.total) AS total,
                    SUM(v.base_gravable) AS base,
                    SUM(v.costo_total) AS costo,
                    SUM(v.base_gravable) - SUM(v.costo_total) AS utilidad
             FROM ventas v
             WHERE v.estado = 'Completada' AND v.fecha BETWEEN ? AND ?
             GROUP BY DATE(v.fecha)
             ORDER BY dia ASC",
            [$desde, $hasta]
        );

        $compras = (float) Bd::valor(
            "SELECT COALESCE(SUM(total), 0) FROM compras WHERE estado = 'Recibida' AND fecha BETWEEN ? AND ?",
            [$desde, $hasta]
        );

        return [
            'desde'  => $d,
            'hasta'  => $h,
            'totales' => [
                'ingresos_brutos' => $ingresos,
                'base_gravable'   => $base,
                'impuestos'       => (float) $totales['impuestos'],
                'descuentos'      => (float) $totales['descuentos'],
                'costo_ventas'    => $costo,
                'utilidad_bruta'  => $utilidad,
                'margen'          => $margen,
                'transacciones'   => $transacciones,
                'ticket_promedio' => $transacciones > 0 ? round($ingresos / $transacciones, 2) : 0.0,
                'compras'         => $compras,
            ],
            'productos' => array_map(static fn (array $p): array => [
                'producto_id' => (int) $p['producto_id'],
                'sku'         => (string) $p['sku'],
                'nombre'      => (string) $p['nombre'],
                'categoria'   => (string) $p['categoria'],
                'unidades'    => (float) $p['unidades'],
                'ingresos'    => round((float) $p['ingresos'], 2),
                'costo'       => round((float) $p['costo'], 2),
                'utilidad'    => round((float) $p['utilidad'], 2),
                'margen'      => (float) $p['ingresos'] > 0
                    ? round(((float) $p['utilidad'] / (float) $p['ingresos']) * 100, 2)
                    : 0.0,
            ], $porProducto),
            'categorias' => array_map(static fn (array $c): array => [
                'categoria' => (string) $c['categoria'],
                'unidades'  => (float) $c['unidades'],
                'ingresos'  => round((float) $c['ingresos'], 2),
                'costo'     => round((float) $c['costo'], 2),
                'utilidad'  => round((float) $c['utilidad'], 2),
                'margen'    => (float) $c['ingresos'] > 0
                    ? round(((float) $c['utilidad'] / (float) $c['ingresos']) * 100, 2)
                    : 0.0,
            ], $porCategoria),
            'dias' => array_map(static fn (array $d2): array => [
                'dia'           => (string) $d2['dia'],
                'transacciones' => (int) $d2['transacciones'],
                'total'         => round((float) $d2['total'], 2),
                'base'          => round((float) $d2['base'], 2),
                'costo'         => round((float) $d2['costo'], 2),
                'utilidad'      => round((float) $d2['utilidad'], 2),
            ], $porDia),
        ];
    }

    public static function panel(): array
    {
        $hoy = date('Y-m-d');
        $ayer = date('Y-m-d', strtotime('-1 day'));

        $ventasHoy = Venta::resumenDia($hoy);
        $ventasAyer = Venta::resumenDia($ayer);

        $variacion = $ventasAyer['total'] > 0
            ? round((($ventasHoy['total'] - $ventasAyer['total']) / $ventasAyer['total']) * 100, 1)
            : 0.0;

        $mes = self::rentabilidad(['desde' => date('Y-m-01'), 'hasta' => $hoy]);

        $topProductos = Bd::consultar(
            "SELECT d.nombre_producto AS nombre, SUM(d.cantidad) AS unidades, SUM(d.total) AS total
             FROM venta_detalle d
             JOIN ventas v ON v.id = d.venta_id
             WHERE v.estado = 'Completada' AND v.fecha >= ?
             GROUP BY d.nombre_producto
             ORDER BY unidades DESC
             LIMIT 5",
            [date('Y-m-01')]
        );

        $ultimaSemana = Bd::consultar(
            "SELECT DATE(v.fecha) AS dia, SUM(v.total) AS total, COUNT(*) AS transacciones
             FROM ventas v
             WHERE v.estado = 'Completada' AND v.fecha >= ?
             GROUP BY DATE(v.fecha)
             ORDER BY dia ASC",
            [date('Y-m-d', strtotime('-6 days')) . ' 00:00:00']
        );

        return [
            'ventas_hoy'       => $ventasHoy,
            'variacion_ayer'   => $variacion,
            'alertas'          => Alerta::resumen(),
            'movimientos_hoy'  => Inventario::resumenMovimientos($hoy),
            'mes'              => $mes['totales'],
            'top_productos'    => array_map(static fn (array $p): array => [
                'nombre'   => (string) $p['nombre'],
                'unidades' => (float) $p['unidades'],
                'total'    => round((float) $p['total'], 2),
            ], $topProductos),
            'semana'           => array_map(static fn (array $d): array => [
                'dia'           => (string) $d['dia'],
                'total'         => round((float) $d['total'], 2),
                'transacciones' => (int) $d['transacciones'],
            ], $ultimaSemana),
            'ultimas_ventas'   => array_slice(Venta::listar([]), 0, 8),
        ];
    }

    public static function inventarioValorizado(): array
    {
        return array_map(static fn (array $p): array => [
            'sku'              => (string) $p['sku'],
            'nombre'           => (string) $p['nombre'],
            'categoria'        => (string) $p['categoria'],
            'proveedor'        => (string) ($p['proveedor'] ?? ''),
            'unidad_medida'    => (string) $p['unidad_medida'],
            'stock_actual'     => (float) $p['stock_actual'],
            'punto_reorden'    => (float) $p['punto_reorden'],
            'precio_costo'     => (float) $p['precio_costo'],
            'precio_venta'     => (float) $p['precio_venta'],
            'valor_inventario' => (float) $p['valor_inventario'],
            'estado_stock'     => (string) $p['estado_stock'],
        ], Bd::consultar('SELECT * FROM v_estado_inventario WHERE activo = 1 ORDER BY categoria, nombre'));
    }
}
