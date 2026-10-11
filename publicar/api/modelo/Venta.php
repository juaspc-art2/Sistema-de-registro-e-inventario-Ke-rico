<?php

declare(strict_types=1);

namespace Kerico\Modelo;

use Kerico\Nucleo\Autenticacion;
use Kerico\Nucleo\Bd;
use Kerico\Nucleo\Bitacora;
use Kerico\Nucleo\ErrorHttp;
use Kerico\Nucleo\Exportador;
use Kerico\Nucleo\Paginador;

final class Venta
{
    public const METODOS = ['Efectivo', 'Tarjeta', 'Transferencia', 'Mixto'];

    public static function registrar(array $datos): array
    {
        $items = $datos['items'] ?? [];
        if (!is_array($items) || $items === []) {
            throw ErrorHttp::validacion('La venta debe incluir al menos un producto', ['items' => 'Lista vacia']);
        }

        $metodo = $datos['metodo_pago'] ?? 'Efectivo';
        if (!in_array($metodo, self::METODOS, true)) {
            throw ErrorHttp::validacion('Método de pago no válido', ['metodo_pago' => 'Valor no permitido']);
        }

        $usuarioId = Autenticacion::idUsuario();

        return Bd::transaccion(static function () use ($items, $datos, $metodo, $usuarioId): array {
            $lineas = [];
            $bruto = 0.0;
            $costoTotal = 0.0;
            $agrupado = [];

            foreach ($items as $indice => $item) {
                $productoId = (int) ($item['producto_id'] ?? 0);
                $cantidad = (float) ($item['cantidad'] ?? 0);

                if ($productoId <= 0) {
                    throw ErrorHttp::validacion('Producto no válido en la línea ' . ($indice + 1), ['items' => 'Producto inválido']);
                }
                if ($cantidad <= 0) {
                    throw ErrorHttp::validacion(
                        'La cantidad debe ser mayor que cero en la línea ' . ($indice + 1),
                        ['items' => 'Cantidad inválida']
                    );
                }

                $agrupado[$productoId] = ($agrupado[$productoId] ?? 0.0) + $cantidad;
            }

            foreach ($agrupado as $productoId => $cantidad) {
                $producto = Bd::primero(
                    'SELECT p.*, c.id AS cat_id FROM productos p JOIN categorias c ON c.id = p.categoria_id
                     WHERE p.id = ? FOR UPDATE',
                    [$productoId]
                );

                if ($producto === null) {
                    throw ErrorHttp::noEncontrado('El producto con identificador ' . $productoId . ' no existe');
                }
                if ((int) $producto['activo'] !== 1) {
                    throw ErrorHttp::conflicto('El producto ' . $producto['nombre'] . ' está inactivo');
                }
                if ((int) $producto['es_insumo'] === 1) {
                    throw ErrorHttp::conflicto('El insumo ' . $producto['nombre'] . ' no se puede vender directamente');
                }

                $stock = (float) $producto['stock_actual'];
                if ($cantidad > $stock) {
                    throw ErrorHttp::conflicto(
                        'Existencias insuficientes de ' . $producto['nombre']
                        . '. Disponible: ' . rtrim(rtrim(number_format($stock, 3, '.', ''), '0'), '.')
                    );
                }

                $precio = (float) $producto['precio_venta'];
                $iva = (float) $producto['iva_porcentaje'];
                $totalLinea = round($precio * $cantidad, 2);
                $base = round($totalLinea / (1 + $iva / 100), 2);
                $impuesto = round($totalLinea - $base, 2);

                $lineas[] = [
                    'producto_id'        => (int) $producto['id'],
                    'categoria_id'       => (int) $producto['cat_id'],
                    'sku'                => (string) $producto['sku'],
                    'nombre'             => (string) $producto['nombre'],
                    'cantidad'           => $cantidad,
                    'precio_unitario'    => $precio,
                    'costo_unitario'     => (float) $producto['precio_costo'],
                    'iva_porcentaje'     => $iva,
                    'subtotal'           => $base,
                    'impuesto'           => $impuesto,
                    'total'              => $totalLinea,
                ];

                $bruto += $totalLinea;
                $costoTotal += (float) $producto['precio_costo'] * $cantidad;
            }

            $bruto = round($bruto, 2);
            $costoTotal = round($costoTotal, 2);

            $descuento = 0.0;
            $promocion = null;
            $motivoDescuento = '';
            $autorizadoPor = null;

            $codigo = trim((string) ($datos['codigo_promocion'] ?? ''));
            if ($codigo !== '') {
                Autenticacion::exigir('descuentos.aplicar');
                $promocion = Promocion::porCodigo($codigo);
                if ($promocion === null) {
                    throw ErrorHttp::validacion('El código de promoción no existe', ['codigo_promocion' => 'Código inválido']);
                }
                $evaluacion = Promocion::evaluar($promocion, $lineas, $bruto);
                if (!$evaluacion['valido']) {
                    throw ErrorHttp::conflicto($evaluacion['motivo']);
                }
                if ($promocion['requiere_autorizacion'] && !Autenticacion::tienePermiso('descuentos.gestionar')) {
                    throw ErrorHttp::sinPermiso('La promoción ' . $promocion['codigo'] . ' requiere autorización de un supervisor');
                }
                $descuento = $evaluacion['descuento'];
                $motivoDescuento = $promocion['descripcion'];
                $autorizadoPor = $promocion['requiere_autorizacion'] ? $usuarioId : null;
            }

            $manual = $datos['descuento_manual'] ?? null;
            if (is_array($manual) && (float) ($manual['valor'] ?? 0) > 0) {
                Autenticacion::exigir('descuentos.gestionar');
                $valorManual = round((float) $manual['valor'], 2);
                $motivoManual = trim((string) ($manual['motivo'] ?? ''));
                if ($motivoManual === '') {
                    throw ErrorHttp::validacion(
                        'Debe indicar el motivo del descuento manual',
                        ['descuento_manual' => 'Motivo obligatorio']
                    );
                }
                if ($valorManual > $bruto - $descuento) {
                    throw ErrorHttp::validacion(
                        'El descuento manual supera el valor de la venta',
                        ['descuento_manual' => 'Valor excesivo']
                    );
                }
                $descuento = round($descuento + $valorManual, 2);
                $motivoDescuento = $motivoDescuento === '' ? $motivoManual : $motivoDescuento . ' | ' . $motivoManual;
                $autorizadoPor = $usuarioId;
            }

            $total = round($bruto - $descuento, 2);
            if ($total < 0) {
                throw ErrorHttp::validacion('El total de la venta no puede ser negativo', ['total' => 'Valor inválido']);
            }

            $factor = $bruto > 0 ? $total / $bruto : 0.0;
            $baseGravable = 0.0;
            foreach ($lineas as $linea) {
                $baseGravable += $linea['subtotal'] * $factor;
            }
            $baseGravable = round($baseGravable, 2);
            $impuestoTotal = round($total - $baseGravable, 2);

            $recibido = round((float) ($datos['monto_recibido'] ?? $total), 2);
            if ($metodo === 'Efectivo' && $recibido < $total) {
                throw ErrorHttp::validacion(
                    'El monto recibido es menor que el total de la venta',
                    ['monto_recibido' => 'Pago insuficiente']
                );
            }
            if ($metodo !== 'Efectivo') {
                $recibido = $total;
            }
            $cambio = round($recibido - $total, 2);

            $clienteId = isset($datos['cliente_id']) && (int) $datos['cliente_id'] > 0
                ? (int) $datos['cliente_id']
                : null;
            if ($clienteId !== null) {
                $existe = Bd::valor('SELECT id FROM clientes WHERE id = ?', [$clienteId]);
                if ($existe === null) {
                    throw ErrorHttp::noEncontrado('El cliente seleccionado no existe');
                }
            }

            $folioTemporal = 'TMP-' . bin2hex(random_bytes(6));

            $ventaId = Bd::insertar(
                'INSERT INTO ventas
                    (folio, usuario_id, cliente_id, subtotal, descuento_total, base_gravable, impuesto_total,
                     total, costo_total, metodo_pago, monto_recibido, cambio, estado)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
                [
                    $folioTemporal, $usuarioId, $clienteId, $bruto, $descuento, $baseGravable,
                    $impuestoTotal, $total, $costoTotal, $metodo, $recibido, $cambio, 'Completada',
                ]
            );

            $folio = 'V-' . str_pad((string) $ventaId, 6, '0', STR_PAD_LEFT);
            Bd::ejecutar('UPDATE ventas SET folio = ? WHERE id = ?', [$folio, $ventaId]);

            foreach ($lineas as $linea) {
                Bd::ejecutar(
                    'INSERT INTO venta_detalle
                        (venta_id, producto_id, sku, nombre_producto, cantidad, precio_unitario, costo_unitario,
                         iva_porcentaje, subtotal, impuesto, total)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?)',
                    [
                        $ventaId, $linea['producto_id'], $linea['sku'], $linea['nombre'], $linea['cantidad'],
                        $linea['precio_unitario'], $linea['costo_unitario'],
                        $linea['iva_porcentaje'], $linea['subtotal'], $linea['impuesto'], $linea['total'],
                    ]
                );

                Inventario::registrarMovimiento(
                    $linea['producto_id'],
                    'Salida',
                    $linea['cantidad'],
                    'Venta ' . $folio,
                    'Venta',
                    $ventaId,
                    'Salida automática por venta',
                    $usuarioId
                );
            }

            if ($descuento > 0) {
                Bd::ejecutar(
                    'INSERT INTO descuentos_aplicados
                        (venta_id, promocion_id, codigo, tipo, valor, motivo, valor_original, valor_descuento,
                         valor_final, autorizado_por)
                     VALUES (?,?,?,?,?,?,?,?,?,?)',
                    [
                        $ventaId,
                        $promocion['id'] ?? null,
                        $promocion['codigo'] ?? 'MANUAL',
                        $promocion['tipo'] ?? 'manual',
                        $promocion['valor'] ?? $descuento,
                        $motivoDescuento,
                        $bruto,
                        $descuento,
                        $total,
                        $autorizadoPor,
                    ]
                );

                if ($promocion !== null) {
                    // La condición va en el UPDATE: dos cajas a la vez no pueden gastar el mismo último uso
                    $reservado = Bd::ejecutar(
                        'UPDATE promociones SET usos_actuales = usos_actuales + 1
                         WHERE id = ? AND (usos_maximos = 0 OR usos_actuales < usos_maximos)',
                        [$promocion['id']]
                    );
                    if ($reservado === 0) {
                        throw ErrorHttp::conflicto('La promoción alcanzo el maximo de usos');
                    }
                }

                Bitacora::registrar(
                    'DESCUENTO_APLICAR',
                    'Descuentos',
                    'Descuento de ' . number_format($descuento, 0, ',', '.') . ' aplicado en la venta ' . $folio,
                    'Info',
                    'ventas',
                    $ventaId
                );
            }

            $tipoComprobante = (string) ($datos['tipo_comprobante'] ?? 'Tirilla POS');
            $comprobante = Comprobante::emitir($ventaId, $tipoComprobante);

            Bitacora::registrar(
                'VENTA_REGISTRAR',
                'Ventas',
                'Venta ' . $folio . ' registrada por ' . number_format($total, 0, ',', '.'),
                'Info',
                'ventas',
                $ventaId,
                null,
                ['total' => $total, 'items' => count($lineas)]
            );

            return [
                'venta'       => self::porId($ventaId),
                'comprobante' => $comprobante,
            ];
        });
    }

    public static function porId(int $id): array
    {
        $venta = Bd::primero(
            'SELECT v.*, CONCAT(u.nombre, " ", u.apellido) AS cajero, c.nombre AS cliente
             FROM ventas v
             JOIN usuarios u ON u.id = v.usuario_id
             LEFT JOIN clientes c ON c.id = v.cliente_id
             WHERE v.id = ?',
            [$id]
        );
        if ($venta === null) {
            throw ErrorHttp::noEncontrado('La venta no existe');
        }

        $detalle = Bd::consultar('SELECT * FROM venta_detalle WHERE venta_id = ? ORDER BY id', [$id]);
        $comprobantes = Bd::consultar('SELECT id, numero, tipo, estado FROM comprobantes WHERE venta_id = ?', [$id]);
        $descuentos = Bd::consultar('SELECT codigo, tipo, valor_descuento, motivo FROM descuentos_aplicados WHERE venta_id = ?', [$id]);

        // Costo y utilidad son datos de rentabilidad: solo para quien puede ver esos reportes
        $rentabilidad = Autenticacion::tienePermiso('reportes.ver') ? [
            'costo_total' => (float) $venta['costo_total'],
            'utilidad'    => round((float) $venta['total'] - (float) $venta['impuesto_total'] - (float) $venta['costo_total'], 2),
        ] : [];

        return $rentabilidad + [
            'id'              => (int) $venta['id'],
            'folio'           => (string) $venta['folio'],
            'fecha'           => (string) $venta['fecha'],
            'cajero'          => (string) $venta['cajero'],
            'cliente'         => (string) ($venta['cliente'] ?? 'Consumidor final'),
            'cliente_id'      => $venta['cliente_id'] === null ? null : (int) $venta['cliente_id'],
            'subtotal'        => (float) $venta['subtotal'],
            'descuento_total' => (float) $venta['descuento_total'],
            'base_gravable'   => (float) $venta['base_gravable'],
            'impuesto_total'  => (float) $venta['impuesto_total'],
            'total'           => (float) $venta['total'],
            'metodo_pago'     => (string) $venta['metodo_pago'],
            'monto_recibido'  => (float) $venta['monto_recibido'],
            'cambio'          => (float) $venta['cambio'],
            'estado'          => (string) $venta['estado'],
            'motivo_anulacion' => (string) $venta['motivo_anulacion'],
            'items'           => array_map(static fn (array $d): array => [
                'producto_id'        => (int) $d['producto_id'],
                'sku'                => (string) $d['sku'],
                'nombre'             => (string) $d['nombre_producto'],
                'cantidad'           => (float) $d['cantidad'],
                'precio_unitario'    => (float) $d['precio_unitario'],
                'descuento_unitario' => (float) $d['descuento_unitario'],
                'iva_porcentaje'     => (float) $d['iva_porcentaje'],
                'subtotal'           => (float) $d['subtotal'],
                'impuesto'           => (float) $d['impuesto'],
                'total'              => (float) $d['total'],
            ], $detalle),
            'comprobantes'    => array_map(static fn (array $c): array => [
                'id'     => (int) $c['id'],
                'numero' => (string) $c['numero'],
                'tipo'   => (string) $c['tipo'],
                'estado' => (string) $c['estado'],
            ], $comprobantes),
            'descuentos'      => array_map(static fn (array $d): array => [
                'codigo' => (string) $d['codigo'],
                'tipo'   => (string) $d['tipo'],
                'valor'  => (float) $d['valor_descuento'],
                'motivo' => (string) $d['motivo'],
            ], $descuentos),
        ];
    }

    private const ORDEN = [
        'fecha'   => 'v.fecha',
        'folio'   => 'v.folio',
        'total'   => 'v.total',
        'cajero'  => 'u.nombre',
        'cliente' => 'c.nombre',
        'estado'  => 'v.estado',
        'pago'    => 'v.metodo_pago',
    ];

    private static function filtrar(array $filtros): array
    {
        $sql = ' FROM ventas v
                JOIN usuarios u ON u.id = v.usuario_id
                LEFT JOIN clientes c ON c.id = v.cliente_id
                WHERE 1 = 1';
        $parametros = [];

        if (!empty($filtros['busqueda'])) {
            $sql .= ' AND (v.folio LIKE ? OR c.nombre LIKE ?)';
            $termino = '%' . $filtros['busqueda'] . '%';
            $parametros[] = $termino;
            $parametros[] = $termino;
        }
        if (!empty($filtros['estado'])) {
            $sql .= ' AND v.estado = ?';
            $parametros[] = $filtros['estado'];
        }
        if (!empty($filtros['desde'])) {
            $sql .= ' AND v.fecha >= ?';
            $parametros[] = $filtros['desde'] . ' 00:00:00';
        }
        if (!empty($filtros['hasta'])) {
            $sql .= ' AND v.fecha <= ?';
            $parametros[] = $filtros['hasta'] . ' 23:59:59';
        }
        if (!empty($filtros['usuario_id'])) {
            $sql .= ' AND v.usuario_id = ?';
            $parametros[] = (int) $filtros['usuario_id'];
        }
        if (!empty($filtros['metodo_pago'])) {
            $sql .= ' AND v.metodo_pago = ?';
            $parametros[] = $filtros['metodo_pago'];
        }

        return [$sql, $parametros];
    }

    private const COLUMNAS = 'SELECT v.*, CONCAT(u.nombre, " ", u.apellido) AS cajero, c.nombre AS cliente,
                    (SELECT COUNT(*) FROM venta_detalle d WHERE d.venta_id = v.id) AS lineas';

    public static function paginar(array $filtros = []): array
    {
        [$desde, $parametros] = self::filtrar($filtros);
        return Paginador::consultar(
            self::COLUMNAS,
            $desde,
            $parametros,
            $filtros,
            self::ORDEN,
            'fecha',
            [self::class, 'normalizarLista']
        );
    }

    public static function normalizarLista(array $v): array
    {
        return [
            'id'              => (int) $v['id'],
            'folio'           => (string) $v['folio'],
            'fecha'           => (string) $v['fecha'],
            'cajero'          => (string) $v['cajero'],
            'cliente'         => (string) ($v['cliente'] ?? 'Consumidor final'),
            'lineas'          => (int) $v['lineas'],
            'subtotal'        => (float) $v['subtotal'],
            'descuento_total' => (float) $v['descuento_total'],
            'impuesto_total'  => (float) $v['impuesto_total'],
            'total'           => (float) $v['total'],
            'metodo_pago'     => (string) $v['metodo_pago'],
            'estado'          => (string) $v['estado'],
        ];
    }

    public static function listar(array $filtros = [], int $limite = Exportador::MAXIMO_FILAS + 1): array
    {
        [$desde, $parametros] = self::filtrar($filtros);
        $sql = self::COLUMNAS . $desde . ' ORDER BY v.fecha DESC, v.id DESC LIMIT ' . (int) $limite;

        return array_map([self::class, 'normalizarLista'], Bd::consultar($sql, $parametros));
    }

    public static function anular(int $id, string $motivo): array
    {
        if (trim($motivo) === '') {
            throw ErrorHttp::validacion('Debe indicar el motivo de la anulación', ['motivo' => 'Obligatorio']);
        }

        $usuarioId = Autenticacion::idUsuario();

        return Bd::transaccion(static function () use ($id, $motivo, $usuarioId): array {
            $venta = Bd::primero('SELECT * FROM ventas WHERE id = ? FOR UPDATE', [$id]);
            if ($venta === null) {
                throw ErrorHttp::noEncontrado('La venta no existe');
            }
            if ($venta['estado'] === 'Anulada') {
                throw ErrorHttp::conflicto('La venta ya fue anulada');
            }

            $detalle = Bd::consultar('SELECT * FROM venta_detalle WHERE venta_id = ?', [$id]);
            foreach ($detalle as $linea) {
                Inventario::registrarMovimiento(
                    (int) $linea['producto_id'],
                    'Devolucion',
                    (float) $linea['cantidad'],
                    'Anulación de la venta ' . $venta['folio'],
                    'Anulacion',
                    $id,
                    $motivo,
                    $usuarioId
                );
            }

            Bd::ejecutar(
                "UPDATE ventas SET estado = 'Anulada', anulada_por = ?, anulada_en = NOW(), motivo_anulacion = ?
                 WHERE id = ?",
                [$usuarioId, mb_substr($motivo, 0, 255), $id]
            );

            Comprobante::anularPorVenta($id);

            $promociones = Bd::consultar('SELECT promocion_id FROM descuentos_aplicados WHERE venta_id = ? AND promocion_id IS NOT NULL', [$id]);
            foreach ($promociones as $p) {
                Bd::ejecutar(
                    'UPDATE promociones SET usos_actuales = GREATEST(usos_actuales - 1, 0) WHERE id = ?',
                    [(int) $p['promocion_id']]
                );
            }

            Bitacora::registrar(
                'VENTA_ANULAR',
                'Ventas',
                'Venta ' . $venta['folio'] . ' anulada. Motivo: ' . $motivo,
                'Advertencia',
                'ventas',
                $id
            );

            return self::porId($id);
        });
    }

    public static function resumenDia(string $dia): array
    {
        $fila = Bd::primero(
            "SELECT
                COUNT(*) AS transacciones,
                COALESCE(SUM(total), 0) AS total,
                COALESCE(SUM(descuento_total), 0) AS descuentos,
                COALESCE(SUM(impuesto_total), 0) AS impuestos,
                COALESCE(SUM(costo_total), 0) AS costo
             FROM ventas
             WHERE estado = 'Completada' AND DATE(fecha) = ?",
            [$dia]
        );

        $unidades = Bd::valor(
            "SELECT COALESCE(SUM(d.cantidad), 0) FROM venta_detalle d
             JOIN ventas v ON v.id = d.venta_id
             WHERE v.estado = 'Completada' AND DATE(v.fecha) = ?",
            [$dia]
        );

        $total = (float) ($fila['total'] ?? 0);
        $impuestos = (float) ($fila['impuestos'] ?? 0);
        $costo = (float) ($fila['costo'] ?? 0);

        $rentabilidad = Autenticacion::tienePermiso('reportes.ver') ? [
            'costo'    => $costo,
            'utilidad' => round($total - $impuestos - $costo, 2),
        ] : [];

        return $rentabilidad + [
            'dia'           => $dia,
            'transacciones' => (int) ($fila['transacciones'] ?? 0),
            'total'         => $total,
            'descuentos'    => (float) ($fila['descuentos'] ?? 0),
            'impuestos'     => $impuestos,
            'unidades'      => (float) ($unidades ?? 0),
        ];
    }
}
