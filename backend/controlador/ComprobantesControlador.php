<?php

declare(strict_types=1);

namespace Kerico\Controlador;

use Kerico\Modelo\Comprobante;
use Kerico\Nucleo\Autenticacion;
use Kerico\Nucleo\Exportador;
use Kerico\Nucleo\Paginador;
use Kerico\Nucleo\Peticion;
use Kerico\Nucleo\Respuesta;
use Kerico\Nucleo\Validador;

final class ComprobantesControlador
{
    public static function listar(Peticion $peticion): void
    {
        Autenticacion::exigir('comprobantes.ver');
        Respuesta::exito(Comprobante::paginar(Paginador::desdePeticion($peticion, [
            'busqueda' => $peticion->consulta('busqueda', ''),
            'tipo'     => $peticion->consulta('tipo', ''),
            'estado'   => $peticion->consulta('estado', ''),
            'desde'    => $peticion->consulta('desde', ''),
            'hasta'    => $peticion->consulta('hasta', ''),
        ])));
    }

    public static function ver(Peticion $peticion): void
    {
        Autenticacion::exigir('comprobantes.ver');
        Respuesta::exito(Comprobante::porId((int) $peticion->parametro('id')));
    }

    public static function emitir(Peticion $peticion): void
    {
        Autenticacion::exigir('comprobantes.emitir');

        $validador = new Validador($peticion->cuerpo());
        $ventaId = $validador->entero('venta_id', 'La venta', true, 1);
        $tipo = $validador->opcion('tipo', 'El tipo de comprobante', Comprobante::TIPOS, false, 'Tirilla POS');
        $validador->validar();

        $comprobante = Comprobante::emitir((int) $ventaId, $tipo);
        Respuesta::exito($comprobante, 'Comprobante ' . $comprobante['numero'] . ' emitido', 201);
    }

    public static function descargar(Peticion $peticion): void
    {
        Autenticacion::exigir('comprobantes.ver');

        $comprobante = Comprobante::porId((int) $peticion->parametro('id'));
        $formato = strtolower((string) $peticion->consulta('formato', 'pdf'));

        $encabezados = ['Codigo', 'Descripcion', 'Cant.', 'Precio', 'Imp. %', 'Base', 'Impuesto', 'Total'];
        $filas = array_map(static fn (array $d): array => [
            $d['sku'],
            $d['nombre'],
            rtrim(rtrim(number_format((float) $d['cantidad'], 3, '.', ''), '0'), '.'),
            number_format((float) $d['precio_unitario'], 0, ',', '.'),
            number_format((float) $d['iva_porcentaje'], 2, ',', '.'),
            number_format((float) $d['subtotal'], 0, ',', '.'),
            number_format((float) $d['impuesto'], 0, ',', '.'),
            number_format((float) $d['total'], 0, ',', '.'),
        ], $comprobante['detalle']);

        $cliente = $comprobante['cliente'];
        $emisor = $comprobante['emisor'];

        $resumen = [
            'Comprobante' => $comprobante['numero'],
            'Fecha'       => $comprobante['fecha_emision'],
            'Cliente'     => (string) ($cliente['nombre'] ?? 'Consumidor final'),
            'Documento'   => trim((string) ($cliente['tipo_documento'] ?? '') . ' ' . (string) ($cliente['documento'] ?? '')),
            'Subtotal'    => '$' . number_format($comprobante['subtotal'], 0, ',', '.'),
            'Descuento'   => '$' . number_format($comprobante['descuento'], 0, ',', '.'),
            'Impuesto'    => '$' . number_format($comprobante['impuesto'], 0, ',', '.'),
            'Total'       => '$' . number_format($comprobante['total'], 0, ',', '.'),
        ];

        if ($formato === 'pdf') {
            $subtitulo = trim((string) ($emisor['razon_social'] ?? 'Ke-Rico!') . '  NIT ' . (string) ($emisor['nit'] ?? ''));
            Respuesta::archivo(
                Exportador::pdf(
                    $comprobante['tipo'] . ' ' . $comprobante['numero'],
                    $subtitulo,
                    $encabezados,
                    $filas,
                    $resumen
                ),
                'comprobante-' . $comprobante['numero'] . '.pdf',
                'application/pdf'
            );
            return;
        }

        Exportacion::enviar(
            $formato,
            'comprobante-' . $comprobante['numero'],
            'Comprobante ' . $comprobante['numero'],
            $comprobante['tipo'],
            $encabezados,
            $filas,
            $resumen
        );
    }

    public static function exportar(Peticion $peticion): void
    {
        Autenticacion::exigir('reportes.exportar');

        $formato = (string) $peticion->consulta('formato', 'pdf');
        $desde = (string) $peticion->consulta('desde', date('Y-m-01'));
        $hasta = (string) $peticion->consulta('hasta', date('Y-m-d'));
        $comprobantes = Comprobante::listar(['desde' => $desde, 'hasta' => $hasta]);

        $encabezados = ['Numero', 'Tipo', 'Folio venta', 'Fecha', 'Cliente', 'Base', 'Impuesto', 'Total', 'Estado'];
        $filas = array_map(static fn (array $c): array => [
            $c['numero'],
            $c['tipo'],
            $c['folio'],
            $c['fecha_emision'],
            (string) ($c['cliente']['nombre'] ?? 'Consumidor final'),
            number_format($c['base_gravable'], 0, ',', '.'),
            number_format($c['impuesto'], 0, ',', '.'),
            number_format($c['total'], 0, ',', '.'),
            $c['estado'],
        ], $comprobantes);

        $total = array_sum(array_map(static fn (array $c): float => $c['estado'] === 'Emitido' ? $c['total'] : 0.0, $comprobantes));

        Exportacion::enviar(
            $formato,
            'comprobantes-' . $desde . '-a-' . $hasta,
            'Comprobantes emitidos',
            'Del ' . $desde . ' al ' . $hasta,
            $encabezados,
            $filas,
            [
                'Comprobantes' => (string) count($comprobantes),
                'Total'        => '$' . number_format($total, 0, ',', '.'),
                'Periodo'      => $desde . ' a ' . $hasta,
            ]
        );
    }
}
