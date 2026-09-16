<?php

declare(strict_types=1);

namespace Kerico\Controlador;

use Kerico\Modelo\Comprobante;
use Kerico\Nucleo\Autenticacion;
use Kerico\Nucleo\Exportador;
use Kerico\Nucleo\Paginador;
use Kerico\Nucleo\Peticion;
use Kerico\Nucleo\Respuesta;
use Kerico\Nucleo\TirillaPdf;
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

        if ($formato === 'pdf') {
            Respuesta::archivo(
                (new TirillaPdf($comprobante))->generar(),
                'comprobante-' . $comprobante['numero'] . '.pdf',
                'application/pdf'
            );
            return;
        }

        $emisor = $comprobante['emisor'] ?? [];
        $cliente = $comprobante['cliente'] ?? [];
        $peso = static fn ($valor): string => '$' . number_format((float) $valor, 0, ',', '.');

        $filas = [
            [(string) ($emisor['nombre'] ?? 'Ke-Rico!'), '', '', ''],
            [(string) ($emisor['razon_social'] ?? ''), '', '', ''],
            ['NIT ' . (string) ($emisor['nit'] ?? ''), '', '', ''],
            [(string) ($emisor['direccion'] ?? ''), '', '', ''],
            [(string) ($emisor['telefono'] ?? ''), '', '', ''],
            ['', '', '', ''],
            [(string) $comprobante['tipo'], 'N.º ' . (string) $comprobante['numero'], '', ''],
            ['Fecha', (string) $comprobante['fecha_emision'], '', ''],
            ['Venta', (string) ($comprobante['folio'] ?? ''), '', ''],
            ['Cajero', (string) ($comprobante['cajero'] ?? ''), '', ''],
            ['Cliente', (string) ($cliente['nombre'] ?? 'Consumidor final'), '', ''],
        ];

        if (trim((string) ($cliente['documento'] ?? '')) !== '') {
            $filas[] = [
                'Documento',
                trim((string) ($cliente['tipo_documento'] ?? '') . ' ' . (string) $cliente['documento']),
                '',
                '',
            ];
        }

        $filas[] = ['', '', '', ''];
        $filas[] = ['Producto', 'Cantidad', 'Precio unitario', 'Total'];

        foreach ($comprobante['detalle'] as $d) {
            $filas[] = [
                (string) $d['nombre'],
                rtrim(rtrim(number_format((float) $d['cantidad'], 3, '.', ''), '0'), '.'),
                $peso($d['precio_unitario']),
                $peso($d['total']),
            ];
        }

        $filas[] = ['', '', '', ''];
        $filas[] = ['Subtotal', '', '', $peso($comprobante['subtotal'])];
        if ((float) $comprobante['descuento'] > 0) {
            $filas[] = ['Descuento', '', '', '(' . $peso($comprobante['descuento']) . ')'];
        }
        $filas[] = ['Base gravable', '', '', $peso($comprobante['base_gravable'])];
        $filas[] = ['Impuesto', '', '', $peso($comprobante['impuesto'])];
        $filas[] = ['TOTAL', '', '', $peso($comprobante['total'])];

        if (($comprobante['estado'] ?? '') === 'Anulado') {
            $filas[] = ['', '', '', ''];
            $filas[] = ['COMPROBANTE ANULADO', '', '', ''];
        }

        $filas[] = ['', '', '', ''];
        $filas[] = [(string) ($comprobante['resolucion_dian'] ?? ''), '', '', ''];
        $filas[] = ['Gracias por su compra', '', '', ''];

        Exportacion::enviar(
            $formato,
            'comprobante-' . $comprobante['numero'],
            (string) $comprobante['tipo'] . ' ' . (string) $comprobante['numero'],
            (string) ($emisor['razon_social'] ?? ''),
            ['Concepto', 'Detalle', 'Precio unitario', 'Valor'],
            $filas
        );
    }

    public static function exportar(Peticion $peticion): void
    {
        Autenticacion::exigir('reportes.exportar');

        $formato = (string) $peticion->consulta('formato', 'pdf');
        $desde = (string) $peticion->consulta('desde', date('Y-m-01'));
        $hasta = (string) $peticion->consulta('hasta', date('Y-m-d'));
        $comprobantes = Comprobante::listar(['desde' => $desde, 'hasta' => $hasta]);

        $encabezados = ['Número', 'Tipo', 'Folio venta', 'Fecha', 'Cliente', 'Base', 'Impuesto', 'Total', 'Estado'];
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
