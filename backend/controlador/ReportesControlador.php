<?php

declare(strict_types=1);

namespace Kerico\Controlador;

use Kerico\Modelo\Reporte;
use Kerico\Nucleo\Autenticacion;
use Kerico\Nucleo\Bitacora;
use Kerico\Nucleo\Peticion;
use Kerico\Nucleo\Respuesta;

final class ReportesControlador
{
    public static function panel(Peticion $peticion): void
    {
        Respuesta::exito(Reporte::panel());
    }

    public static function rentabilidad(Peticion $peticion): void
    {
        Autenticacion::exigir('reportes.ver');

        $datos = Reporte::rentabilidad([
            'desde' => $peticion->consulta('desde', date('Y-m-01')),
            'hasta' => $peticion->consulta('hasta', date('Y-m-d')),
        ]);

        Bitacora::registrar(
            'REPORTE_GENERAR',
            'Reportes',
            'Reporte de rentabilidad generado del ' . $datos['desde'] . ' al ' . $datos['hasta'],
            'Info',
            'reportes',
            ''
        );

        Respuesta::exito($datos);
    }

    public static function exportarRentabilidad(Peticion $peticion): void
    {
        Autenticacion::exigir('reportes.exportar');

        $formato = (string) $peticion->consulta('formato', 'pdf');
        $desde = (string) $peticion->consulta('desde', date('Y-m-01'));
        $hasta = (string) $peticion->consulta('hasta', date('Y-m-d'));
        $datos = Reporte::rentabilidad(['desde' => $desde, 'hasta' => $hasta]);

        $encabezados = ['SKU', 'Producto', 'Categoria', 'Unidades', 'Ingresos', 'Costo', 'Utilidad', 'Margen %'];
        $filas = array_map(static fn (array $p): array => [
            $p['sku'],
            $p['nombre'],
            $p['categoria'],
            rtrim(rtrim(number_format($p['unidades'], 3, '.', ''), '0'), '.'),
            number_format($p['ingresos'], 0, ',', '.'),
            number_format($p['costo'], 0, ',', '.'),
            number_format($p['utilidad'], 0, ',', '.'),
            number_format($p['margen'], 2, ',', '.'),
        ], $datos['productos']);

        $t = $datos['totales'];

        Bitacora::registrar(
            'REPORTE_EXPORTAR',
            'Reportes',
            'Reporte de rentabilidad exportado en formato ' . strtoupper($formato),
            'Info',
            'reportes',
            ''
        );

        Exportacion::enviar(
            $formato,
            'rentabilidad-' . $desde . '-a-' . $hasta,
            'Análisis de rentabilidad',
            'Del ' . $desde . ' al ' . $hasta,
            $encabezados,
            $filas,
            [
                'Ingresos'      => '$' . number_format($t['ingresos_brutos'], 0, ',', '.'),
                'Costo ventas'  => '$' . number_format($t['costo_ventas'], 0, ',', '.'),
                'Utilidad'      => '$' . number_format($t['utilidad_bruta'], 0, ',', '.'),
                'Margen'        => number_format($t['margen'], 2, ',', '.') . ' %',
                'Transacciones' => (string) $t['transacciones'],
                'Ticket prom.'  => '$' . number_format($t['ticket_promedio'], 0, ',', '.'),
            ]
        );
    }

    public static function inventarioValorizado(Peticion $peticion): void
    {
        Autenticacion::exigir('reportes.ver');
        Respuesta::exito(Reporte::inventarioValorizado());
    }
}
