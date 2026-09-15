<?php

declare(strict_types=1);

namespace Kerico\Modelo;

use Kerico\Nucleo\Ajustes;
use Kerico\Nucleo\Autenticacion;
use Kerico\Nucleo\Bd;
use Kerico\Nucleo\Bitacora;
use Kerico\Nucleo\ErrorHttp;
use Kerico\Nucleo\Paginador;

final class Inventario
{
    public const TIPOS = ['Entrada', 'Salida', 'Ajuste', 'Merma', 'Devolucion'];

    public static function producto(int $id): array
    {
        $fila = Bd::primero('SELECT * FROM v_estado_inventario WHERE id = ?', [$id]);
        if ($fila === null) {
            throw ErrorHttp::noEncontrado('El producto solicitado no existe');
        }
        return self::normalizar($fila);
    }

    private const ORDEN = [
        'nombre'        => 'nombre',
        'sku'           => 'sku',
        'categoria'     => 'categoria',
        'stock'         => 'stock_actual',
        'precio_venta'  => 'precio_venta',
        'precio_costo'  => 'precio_costo',
        'valor'         => 'valor_inventario',
        'estado'        => "FIELD(estado_stock,'Agotado','Critico','Bajo','Disponible')",
    ];

    private static function filtrar(array $filtros): array
    {
        $sql = ' FROM v_estado_inventario WHERE 1 = 1';
        $parametros = [];

        if (!empty($filtros['busqueda'])) {
            $sql .= ' AND (nombre LIKE ? OR sku LIKE ? OR categoria LIKE ?)';
            $termino = '%' . $filtros['busqueda'] . '%';
            $parametros[] = $termino;
            $parametros[] = $termino;
            $parametros[] = $termino;
        }
        if (!empty($filtros['categoria_id'])) {
            $sql .= ' AND categoria = (SELECT nombre FROM categorias WHERE id = ?)';
            $parametros[] = (int) $filtros['categoria_id'];
        }
        if (!empty($filtros['estado'])) {
            $sql .= ' AND estado_stock = ?';
            $parametros[] = $filtros['estado'];
        }
        if (!empty($filtros['solo_venta'])) {
            $sql .= ' AND es_insumo = 0 AND activo = 1';
        }
        if (!empty($filtros['solo_insumo'])) {
            $sql .= ' AND es_insumo = 1';
        }
        if (isset($filtros['activo']) && $filtros['activo'] !== '') {
            $sql .= ' AND activo = ?';
            $parametros[] = (int) $filtros['activo'];
        }

        return [$sql, $parametros];
    }

    public static function listar(array $filtros = []): array
    {
        [$desde, $parametros] = self::filtrar($filtros);
        $sql = 'SELECT *' . $desde . ' ORDER BY nombre ASC';
        return array_map([self::class, 'normalizar'], Bd::consultar($sql, $parametros));
    }

    public static function paginar(array $filtros = []): array
    {
        [$desde, $parametros] = self::filtrar($filtros);
        return Paginador::consultar(
            'SELECT *',
            $desde,
            $parametros,
            $filtros,
            self::ORDEN,
            'nombre',
            [self::class, 'normalizar']
        );
    }

    public static function normalizar(array $fila): array
    {
        return [
            'id'               => (int) $fila['id'],
            'sku'              => (string) $fila['sku'],
            'nombre'           => (string) $fila['nombre'],
            'categoria'        => (string) $fila['categoria'],
            'proveedor'        => $fila['proveedor'] === null ? '' : (string) $fila['proveedor'],
            'unidad_medida'    => (string) $fila['unidad_medida'],
            'precio_costo'     => (float) $fila['precio_costo'],
            'precio_venta'     => (float) $fila['precio_venta'],
            'iva_porcentaje'   => (float) $fila['iva_porcentaje'],
            'stock_actual'     => (float) $fila['stock_actual'],
            'punto_reorden'    => (float) $fila['punto_reorden'],
            'stock_maximo'     => (float) $fila['stock_maximo'],
            'es_insumo'        => (int) $fila['es_insumo'] === 1,
            'imagen'           => (string) $fila['imagen'],
            'activo'           => (int) $fila['activo'] === 1,
            'valor_inventario' => (float) $fila['valor_inventario'],
            'estado_stock'     => (string) $fila['estado_stock'],
        ];
    }

    public static function estadoStock(float $stock, float $reorden): string
    {
        if ($stock <= 0) {
            return 'Agotado';
        }
        if ($reorden <= 0) {
            return 'Disponible';
        }
        $factor = (float) Ajustes::obtener('alerta_factor_critico', 0.5);
        if ($stock <= $reorden * $factor) {
            return 'Critico';
        }
        if ($stock <= $reorden) {
            return 'Bajo';
        }
        return 'Disponible';
    }

    public static function registrarMovimiento(
        int $productoId,
        string $tipo,
        float $cantidad,
        string $motivo,
        string $referenciaTipo = 'Ajuste',
        ?int $referenciaId = null,
        string $observaciones = '',
        ?int $usuarioId = null
    ): array {
        if (!in_array($tipo, self::TIPOS, true)) {
            throw ErrorHttp::validacion('El tipo de movimiento no es válido', ['tipo' => 'Valor no permitido']);
        }
        if ($cantidad <= 0) {
            throw ErrorHttp::validacion('La cantidad debe ser mayor que cero', ['cantidad' => 'Debe ser mayor que cero']);
        }

        $usuarioId = $usuarioId ?? Autenticacion::idUsuario();

        $producto = Bd::primero('SELECT * FROM productos WHERE id = ? FOR UPDATE', [$productoId]);
        if ($producto === null) {
            throw ErrorHttp::noEncontrado('El producto no existe');
        }

        $anterior = (float) $producto['stock_actual'];
        $suma = in_array($tipo, ['Entrada', 'Devolucion'], true);
        $nuevo = $suma ? $anterior + $cantidad : $anterior - $cantidad;

        if ($nuevo < 0) {
            throw ErrorHttp::validacion(
                'No hay existencias suficientes de ' . $producto['nombre'],
                ['cantidad' => 'Disponible: ' . rtrim(rtrim(number_format($anterior, 3, '.', ''), '0'), '.')]
            );
        }

        Bd::ejecutar('UPDATE productos SET stock_actual = ? WHERE id = ?', [$nuevo, $productoId]);

        $movimientoId = Bd::insertar(
            'INSERT INTO movimientos_inventario
                (producto_id, tipo, motivo, cantidad, stock_anterior, stock_nuevo, costo_unitario,
                 referencia_tipo, referencia_id, usuario_id, observaciones)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)',
            [
                $productoId,
                $tipo,
                mb_substr($motivo, 0, 120),
                $cantidad,
                $anterior,
                $nuevo,
                (float) $producto['precio_costo'],
                $referenciaTipo,
                $referenciaId,
                $usuarioId,
                mb_substr($observaciones, 0, 255),
            ]
        );

        self::evaluarAlerta($productoId, $nuevo, (float) $producto['punto_reorden'], (string) $producto['nombre'], (string) $producto['unidad_medida']);

        return [
            'id'             => $movimientoId,
            'producto_id'    => $productoId,
            'stock_anterior' => $anterior,
            'stock_nuevo'    => $nuevo,
        ];
    }

    public static function evaluarAlerta(int $productoId, float $stock, float $reorden, string $nombre, string $unidad): void
    {
        $estado = self::estadoStock($stock, $reorden);

        if ($estado === 'Disponible') {
            Bd::ejecutar(
                "UPDATE alertas_stock SET estado = 'Resuelta', resuelta_en = NOW()
                 WHERE producto_id = ? AND estado <> 'Resuelta'",
                [$productoId]
            );
            return;
        }

        $existente = Bd::primero(
            "SELECT id, tipo FROM alertas_stock
             WHERE producto_id = ? AND estado <> 'Resuelta'
             ORDER BY id DESC LIMIT 1",
            [$productoId]
        );

        $cantidad = rtrim(rtrim(number_format($stock, 3, '.', ''), '0'), '.');
        $limite = rtrim(rtrim(number_format($reorden, 3, '.', ''), '0'), '.');
        $mensaje = $estado === 'Agotado'
            ? $nombre . ' se quedó sin existencias. Reponer de inmediato.'
            : $nombre . ' está en ' . $cantidad . ' ' . $unidad . ' frente a un punto de reorden de ' . $limite . ' ' . $unidad . '.';

        if ($existente !== null && $existente['tipo'] === $estado) {
            Bd::ejecutar(
                'UPDATE alertas_stock SET stock_en_alerta = ?, punto_reorden = ?, mensaje = ? WHERE id = ?',
                [$stock, $reorden, $mensaje, $existente['id']]
            );
            return;
        }

        if ($existente !== null) {
            Bd::ejecutar(
                "UPDATE alertas_stock SET estado = 'Resuelta', resuelta_en = NOW() WHERE id = ?",
                [$existente['id']]
            );
        }

        Bd::insertar(
            'INSERT INTO alertas_stock (producto_id, tipo, stock_en_alerta, punto_reorden, mensaje)
             VALUES (?,?,?,?,?)',
            [$productoId, $estado, $stock, $reorden, $mensaje]
        );

        Bitacora::registrar(
            'ALERTA_STOCK',
            'Alertas',
            $mensaje,
            $estado === 'Agotado' ? 'Error' : 'Advertencia',
            'productos',
            $productoId
        );
    }

    public static function recalcularAlertas(): int
    {
        $productos = Bd::consultar('SELECT id, nombre, stock_actual, punto_reorden, unidad_medida FROM productos WHERE activo = 1 AND deleted_at IS NULL');
        foreach ($productos as $p) {
            self::evaluarAlerta(
                (int) $p['id'],
                (float) $p['stock_actual'],
                (float) $p['punto_reorden'],
                (string) $p['nombre'],
                (string) $p['unidad_medida']
            );
        }
        return count($productos);
    }

    private const ORDEN_MOVIMIENTOS = [
        'fecha'    => 'm.creado_en',
        'producto' => 'p.nombre',
        'sku'      => 'p.sku',
        'tipo'     => 'm.tipo',
        'cantidad' => 'm.cantidad',
        'usuario'  => 'u.nombre',
    ];

    private static function filtrarMovimientos(array $filtros): array
    {
        $sql = ' FROM movimientos_inventario m
                JOIN productos p ON p.id = m.producto_id
                JOIN usuarios u ON u.id = m.usuario_id
                WHERE 1 = 1';
        $parametros = [];

        if (!empty($filtros['busqueda'])) {
            $sql .= ' AND (p.nombre LIKE ? OR p.sku LIKE ? OR m.motivo LIKE ?)';
            $termino = '%' . $filtros['busqueda'] . '%';
            $parametros[] = $termino;
            $parametros[] = $termino;
            $parametros[] = $termino;
        }
        if (!empty($filtros['tipo'])) {
            $sql .= ' AND m.tipo = ?';
            $parametros[] = $filtros['tipo'];
        }
        if (!empty($filtros['producto_id'])) {
            $sql .= ' AND m.producto_id = ?';
            $parametros[] = (int) $filtros['producto_id'];
        }
        if (!empty($filtros['desde'])) {
            $sql .= ' AND m.creado_en >= ?';
            $parametros[] = $filtros['desde'] . ' 00:00:00';
        }
        if (!empty($filtros['hasta'])) {
            $sql .= ' AND m.creado_en <= ?';
            $parametros[] = $filtros['hasta'] . ' 23:59:59';
        }

        return [$sql, $parametros];
    }

    public static function paginarMovimientos(array $filtros = []): array
    {
        [$desde, $parametros] = self::filtrarMovimientos($filtros);
        return Paginador::consultar(
            'SELECT m.*, p.sku, p.nombre AS producto, p.unidad_medida,
                    CONCAT(u.nombre, " ", u.apellido) AS usuario',
            $desde,
            $parametros,
            $filtros,
            self::ORDEN_MOVIMIENTOS,
            'fecha',
            [self::class, 'normalizarMovimiento']
        );
    }

    public static function movimientos(array $filtros = []): array
    {
        $sql = 'SELECT m.*, p.sku, p.nombre AS producto, p.unidad_medida,
                       CONCAT(u.nombre, " ", u.apellido) AS usuario
                FROM movimientos_inventario m
                JOIN productos p ON p.id = m.producto_id
                JOIN usuarios u ON u.id = m.usuario_id
                WHERE 1 = 1';
        $parametros = [];

        if (!empty($filtros['busqueda'])) {
            $sql .= ' AND (p.nombre LIKE ? OR p.sku LIKE ? OR m.motivo LIKE ?)';
            $termino = '%' . $filtros['busqueda'] . '%';
            $parametros[] = $termino;
            $parametros[] = $termino;
            $parametros[] = $termino;
        }
        if (!empty($filtros['tipo'])) {
            $sql .= ' AND m.tipo = ?';
            $parametros[] = $filtros['tipo'];
        }
        if (!empty($filtros['producto_id'])) {
            $sql .= ' AND m.producto_id = ?';
            $parametros[] = (int) $filtros['producto_id'];
        }
        if (!empty($filtros['desde'])) {
            $sql .= ' AND m.creado_en >= ?';
            $parametros[] = $filtros['desde'] . ' 00:00:00';
        }
        if (!empty($filtros['hasta'])) {
            $sql .= ' AND m.creado_en <= ?';
            $parametros[] = $filtros['hasta'] . ' 23:59:59';
        }

        $sql .= ' ORDER BY m.creado_en DESC, m.id DESC';
        $limite = (int) ($filtros['limite'] ?? 300);
        $sql .= ' LIMIT ' . max(1, min($limite, 2000));

        return array_map([self::class, 'normalizarMovimiento'], Bd::consultar($sql, $parametros));
    }

    public static function normalizarMovimiento(array $m): array
    {
        return [
            'id'             => (int) $m['id'],
            'producto_id'    => (int) $m['producto_id'],
            'sku'            => (string) $m['sku'],
            'producto'       => (string) $m['producto'],
            'unidad_medida'  => (string) $m['unidad_medida'],
            'tipo'           => (string) $m['tipo'],
            'motivo'         => (string) $m['motivo'],
            'cantidad'       => (float) $m['cantidad'],
            'stock_anterior' => (float) $m['stock_anterior'],
            'stock_nuevo'    => (float) $m['stock_nuevo'],
            'costo_unitario' => (float) $m['costo_unitario'],
            'referencia'     => (string) $m['referencia_tipo'],
            'referencia_id'  => $m['referencia_id'] === null ? null : (int) $m['referencia_id'],
            'usuario'        => (string) $m['usuario'],
            'observaciones'  => (string) $m['observaciones'],
            'fecha'          => (string) $m['creado_en'],
        ];
    }

    public static function resumenMovimientos(string $dia): array
    {
        $fila = Bd::primero(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN tipo IN ('Entrada','Devolucion') THEN 1 ELSE 0 END) AS entradas,
                SUM(CASE WHEN tipo = 'Salida' THEN 1 ELSE 0 END) AS salidas,
                SUM(CASE WHEN tipo IN ('Ajuste','Merma') THEN 1 ELSE 0 END) AS ajustes
             FROM movimientos_inventario
             WHERE DATE(creado_en) = ?",
            [$dia]
        );

        return [
            'total'    => (int) ($fila['total'] ?? 0),
            'entradas' => (int) ($fila['entradas'] ?? 0),
            'salidas'  => (int) ($fila['salidas'] ?? 0),
            'ajustes'  => (int) ($fila['ajustes'] ?? 0),
        ];
    }
}
