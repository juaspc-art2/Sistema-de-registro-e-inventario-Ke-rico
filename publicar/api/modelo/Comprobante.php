<?php

declare(strict_types=1);

namespace Kerico\Modelo;

use Kerico\Nucleo\Ajustes;
use Kerico\Nucleo\Bd;
use Kerico\Nucleo\Bitacora;
use Kerico\Nucleo\ErrorHttp;
use Kerico\Nucleo\Paginador;

final class Comprobante
{
    public const TIPOS = ['Tirilla POS', 'Factura de venta', 'Nota credito', 'Nota debito'];

    public static function emitir(int $ventaId, string $tipo = 'Tirilla POS'): array
    {
        if (!in_array($tipo, self::TIPOS, true)) {
            throw ErrorHttp::validacion('Tipo de comprobante no válido', ['tipo' => 'Valor no permitido']);
        }

        $venta = Bd::primero('SELECT * FROM ventas WHERE id = ?', [$ventaId]);
        if ($venta === null) {
            throw ErrorHttp::noEncontrado('La venta no existe');
        }
        if ($venta['estado'] === 'Anulada') {
            throw ErrorHttp::conflicto('No se puede emitir un comprobante de una venta anulada');
        }

        $cliente = $venta['cliente_id'] === null
            ? null
            : Bd::primero('SELECT * FROM clientes WHERE id = ?', [(int) $venta['cliente_id']]);

        $detalle = Bd::consultar(
            'SELECT sku, nombre_producto, cantidad, precio_unitario, descuento_unitario,
                    iva_porcentaje, subtotal, impuesto, total
             FROM venta_detalle WHERE venta_id = ?',
            [$ventaId]
        );

        $consecutivo = Ajustes::siguienteConsecutivo();
        $prefijo = (string) Ajustes::obtener('comprobante_prefijo', 'KR');
        $numero = $prefijo . '-' . str_pad((string) $consecutivo, 8, '0', STR_PAD_LEFT);

        $id = Bd::insertar(
            'INSERT INTO comprobantes
                (venta_id, numero, prefijo, consecutivo, tipo, resolucion_dian, emisor, cliente, detalle,
                 subtotal, descuento, base_gravable, impuesto, total)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [
                $ventaId,
                $numero,
                $prefijo,
                $consecutivo,
                $tipo,
                (string) Ajustes::obtener('resolucion_dian', ''),
                json_encode(Ajustes::emisor(), JSON_UNESCAPED_UNICODE),
                json_encode([
                    'tipo_documento' => $cliente['tipo_documento'] ?? 'NA',
                    'documento'      => $cliente['documento'] ?? '',
                    'nombre'         => $cliente['nombre'] ?? 'Consumidor final',
                    'telefono'       => $cliente['telefono'] ?? '',
                    'correo'         => $cliente['correo'] ?? '',
                    'direccion'      => $cliente['direccion'] ?? '',
                ], JSON_UNESCAPED_UNICODE),
                json_encode(array_map(static fn (array $d): array => [
                    'sku'             => $d['sku'],
                    'nombre'          => $d['nombre_producto'],
                    'cantidad'        => (float) $d['cantidad'],
                    'precio_unitario' => (float) $d['precio_unitario'],
                    'iva_porcentaje'  => (float) $d['iva_porcentaje'],
                    'subtotal'        => (float) $d['subtotal'],
                    'impuesto'        => (float) $d['impuesto'],
                    'total'           => (float) $d['total'],
                ], $detalle), JSON_UNESCAPED_UNICODE),
                (float) $venta['subtotal'],
                (float) $venta['descuento_total'],
                (float) $venta['base_gravable'],
                (float) $venta['impuesto_total'],
                (float) $venta['total'],
            ]
        );

        Bitacora::registrar(
            'COMPROBANTE_EMITIR',
            'Comprobantes',
            'Comprobante ' . $numero . ' emitido para la venta ' . $venta['folio'],
            'Info',
            'comprobantes',
            $id
        );

        return self::porId($id);
    }

    public static function porId(int $id): array
    {
        $fila = Bd::primero(
            'SELECT c.*, v.folio, v.metodo_pago, v.fecha AS fecha_venta,
                    CONCAT(u.nombre, " ", u.apellido) AS cajero
             FROM comprobantes c
             JOIN ventas v ON v.id = c.venta_id
             JOIN usuarios u ON u.id = v.usuario_id
             WHERE c.id = ?',
            [$id]
        );
        if ($fila === null) {
            throw ErrorHttp::noEncontrado('El comprobante no existe');
        }
        return self::normalizar($fila);
    }

    private const ORDEN = [
        'fecha'  => 'c.fecha_emision',
        'numero' => 'c.consecutivo',
        'tipo'   => 'c.tipo',
        'total'  => 'c.total',
        'estado' => 'c.estado',
        'folio'  => 'v.folio',
    ];

    private static function filtrar(array $filtros): array
    {
        $sql = ' FROM comprobantes c
                JOIN ventas v ON v.id = c.venta_id
                JOIN usuarios u ON u.id = v.usuario_id
                WHERE 1 = 1';
        $parametros = [];

        if (!empty($filtros['busqueda'])) {
            $sql .= ' AND (c.numero LIKE ? OR v.folio LIKE ? OR c.cliente LIKE ?)';
            $termino = '%' . $filtros['busqueda'] . '%';
            $parametros[] = $termino;
            $parametros[] = $termino;
            $parametros[] = $termino;
        }
        if (!empty($filtros['tipo'])) {
            $sql .= ' AND c.tipo = ?';
            $parametros[] = $filtros['tipo'];
        }
        if (!empty($filtros['estado'])) {
            $sql .= ' AND c.estado = ?';
            $parametros[] = $filtros['estado'];
        }
        if (!empty($filtros['desde'])) {
            $sql .= ' AND c.fecha_emision >= ?';
            $parametros[] = $filtros['desde'] . ' 00:00:00';
        }
        if (!empty($filtros['hasta'])) {
            $sql .= ' AND c.fecha_emision <= ?';
            $parametros[] = $filtros['hasta'] . ' 23:59:59';
        }

        return [$sql, $parametros];
    }

    public static function paginar(array $filtros = []): array
    {
        [$desde, $parametros] = self::filtrar($filtros);
        return Paginador::consultar(
            'SELECT c.*, v.folio, v.metodo_pago, v.fecha AS fecha_venta,
                    CONCAT(u.nombre, " ", u.apellido) AS cajero',
            $desde,
            $parametros,
            $filtros,
            self::ORDEN,
            'fecha',
            [self::class, 'normalizar']
        );
    }

    public static function listar(array $filtros = []): array
    {
        $sql = 'SELECT c.*, v.folio, v.metodo_pago, v.fecha AS fecha_venta,
                       CONCAT(u.nombre, " ", u.apellido) AS cajero
                FROM comprobantes c
                JOIN ventas v ON v.id = c.venta_id
                JOIN usuarios u ON u.id = v.usuario_id
                WHERE 1 = 1';
        $parametros = [];

        if (!empty($filtros['busqueda'])) {
            $sql .= ' AND (c.numero LIKE ? OR v.folio LIKE ? OR c.cliente LIKE ?)';
            $termino = '%' . $filtros['busqueda'] . '%';
            $parametros[] = $termino;
            $parametros[] = $termino;
            $parametros[] = $termino;
        }
        if (!empty($filtros['tipo'])) {
            $sql .= ' AND c.tipo = ?';
            $parametros[] = $filtros['tipo'];
        }
        if (!empty($filtros['estado'])) {
            $sql .= ' AND c.estado = ?';
            $parametros[] = $filtros['estado'];
        }
        if (!empty($filtros['desde'])) {
            $sql .= ' AND c.fecha_emision >= ?';
            $parametros[] = $filtros['desde'] . ' 00:00:00';
        }
        if (!empty($filtros['hasta'])) {
            $sql .= ' AND c.fecha_emision <= ?';
            $parametros[] = $filtros['hasta'] . ' 23:59:59';
        }

        $sql .= ' ORDER BY c.fecha_emision DESC, c.id DESC LIMIT 500';

        return array_map([self::class, 'normalizar'], Bd::consultar($sql, $parametros));
    }

    public static function normalizar(array $f): array
    {
        return [
            'id'              => (int) $f['id'],
            'venta_id'        => (int) $f['venta_id'],
            'folio'           => (string) $f['folio'],
            'numero'          => (string) $f['numero'],
            'prefijo'         => (string) $f['prefijo'],
            'consecutivo'     => (int) $f['consecutivo'],
            'tipo'            => (string) $f['tipo'],
            'resolucion_dian' => (string) $f['resolucion_dian'],
            'fecha_emision'   => (string) $f['fecha_emision'],
            'emisor'          => json_decode((string) $f['emisor'], true) ?: [],
            'cliente'         => json_decode((string) $f['cliente'], true) ?: [],
            'detalle'         => json_decode((string) $f['detalle'], true) ?: [],
            'subtotal'        => (float) $f['subtotal'],
            'descuento'       => (float) $f['descuento'],
            'base_gravable'   => (float) $f['base_gravable'],
            'impuesto'        => (float) $f['impuesto'],
            'total'           => (float) $f['total'],
            'estado'          => (string) $f['estado'],
            'metodo_pago'     => (string) ($f['metodo_pago'] ?? ''),
            'cajero'          => (string) ($f['cajero'] ?? ''),
        ];
    }

    public static function anularPorVenta(int $ventaId): void
    {
        Bd::ejecutar("UPDATE comprobantes SET estado = 'Anulado' WHERE venta_id = ?", [$ventaId]);
    }
}
