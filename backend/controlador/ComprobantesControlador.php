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

        // Si el comprobante sale por correo lo lee el cliente: se le escribe a él, no como a un reporte interno
        $nombreCliente = trim((string) ($comprobante['cliente']['nombre'] ?? ''));
        Respuesta::redactarCorreo([
            $nombreCliente === '' || $nombreCliente === 'Consumidor final' ? 'Hola,' : 'Hola ' . $nombreCliente . ',',
            $comprobante['estado'] === 'Anulado'
                ? 'Adjunto encontrará el comprobante de su compra, que fue anulado.'
                : 'Gracias por su compra. Adjunto encontrará su comprobante.',
            ['datos' => [
                'Comprobante' => $comprobante['tipo'] . ' N.º ' . $comprobante['numero'],
                'Fecha'       => date('d/m/Y h:i a', (int) strtotime($comprobante['fecha_emision'])),
                'Total'       => '$' . number_format((float) $comprobante['total'], 0, ',', '.'),
            ]],
            'Si tiene alguna pregunta sobre su compra, con gusto le atendemos.',
        ]);

        if ($formato === 'pdf') {
            Respuesta::archivo(
                (new TirillaPdf($comprobante))->generar(),
                'comprobante-' . $comprobante['numero'] . '.pdf',
                'application/pdf',
                $comprobante['tipo'] . ' N.º ' . $comprobante['numero']
            );
            return;
        }

        $emisor = $comprobante['emisor'] ?? [];
        $cliente = $comprobante['cliente'] ?? [];

        $filas = [
            [(string) ($emisor['nombre'] ?? 'Ke-Rico!'), '', '', ''],
            [(string) ($emisor['razon_social'] ?? ''), '', '', ''],
            [trim((string) ($emisor['nit'] ?? '')) === '' ? '' : 'NIT ' . $emisor['nit'], '', '', ''],
            [(string) ($emisor['direccion'] ?? ''), '', '', ''],
            [(string) ($emisor['telefono'] ?? ''), '', '', ''],
            ['', '', '', ''],
            [(string) $comprobante['tipo'], 'N.º ' . (string) $comprobante['numero'], '', ''],
            ['Fecha', Exportador::fechaLegible((string) $comprobante['fecha_emision']), '', ''],
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
                Exportador::cantidad($d['cantidad']),
                Exportador::dinero($d['precio_unitario']),
                Exportador::dinero($d['total']),
            ];
        }

        $filas[] = ['', '', '', ''];
        $filas[] = ['Subtotal', '', '', Exportador::dinero($comprobante['subtotal'])];
        if ((float) $comprobante['descuento'] > 0) {
            $filas[] = ['Descuento', '', '', Exportador::dinero(-(float) $comprobante['descuento'])];
        }
        $filas[] = ['Base gravable', '', '', Exportador::dinero($comprobante['base_gravable'])];
        $filas[] = ['Impuesto', '', '', Exportador::dinero($comprobante['impuesto'])];
        $filas[] = ['TOTAL', '', '', Exportador::dinero($comprobante['total'])];

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
        $comprobantes = Comprobante::listar([
            'desde'    => $desde,
            'hasta'    => $hasta,
            'busqueda' => $peticion->consulta('busqueda', ''),
            'tipo'     => $peticion->consulta('tipo', ''),
            'estado'   => $peticion->consulta('estado', ''),
        ]);

        $encabezados = ['Número', 'Tipo', 'Folio venta', 'Fecha', 'Cliente', 'Base', 'Impuesto', 'Total', 'Estado'];
        $filas = array_map(static fn (array $c): array => [
            $c['numero'],
            $c['tipo'],
            $c['folio'],
            $c['fecha_emision'],
            (string) ($c['cliente']['nombre'] ?? 'Consumidor final'),
            Exportador::dinero($c['base_gravable']),
            Exportador::dinero($c['impuesto']),
            Exportador::dinero($c['total']),
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
