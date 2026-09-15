<?php

declare(strict_types=1);

namespace Kerico\Modelo;

use Kerico\Nucleo\Bd;
use Kerico\Nucleo\ErrorHttp;
use Kerico\Nucleo\Paginador;

final class Promocion
{
    public static function listar(array $filtros = []): array
    {
        $sql = 'SELECT p.*, pr.nombre AS producto_nombre, c.nombre AS categoria_nombre
                FROM promociones p
                LEFT JOIN productos pr ON pr.id = p.producto_id
                LEFT JOIN categorias c ON c.id = p.categoria_id
                WHERE p.deleted_at IS NULL';
        $parametros = [];

        if (isset($filtros['activa']) && $filtros['activa'] !== null) {
            $sql .= ' AND p.activa = ?';
            $parametros[] = (int) $filtros['activa'];
        }
        if (!empty($filtros['busqueda'])) {
            $sql .= ' AND (p.codigo LIKE ? OR p.descripcion LIKE ?)';
            $termino = '%' . $filtros['busqueda'] . '%';
            $parametros[] = $termino;
            $parametros[] = $termino;
        }

        $sql .= ' ORDER BY p.activa DESC, p.codigo ASC';

        return array_map([self::class, 'normalizar'], Bd::consultar($sql, $parametros));
    }

    private const ORDEN = [
        'codigo' => 'p.codigo',
        'valor'  => 'p.valor',
        'usos'   => 'p.usos_actuales',
        'estado' => 'p.activa',
        'inicio' => 'p.fecha_inicio',
        'fin'    => 'p.fecha_fin',
    ];

    public static function paginar(array $filtros = []): array
    {
        $desde = ' FROM promociones p
                LEFT JOIN productos pr ON pr.id = p.producto_id
                LEFT JOIN categorias c ON c.id = p.categoria_id
                WHERE p.deleted_at IS NULL';
        $parametros = [];

        if (isset($filtros['activa']) && $filtros['activa'] !== null && $filtros['activa'] !== '') {
            $desde .= ' AND p.activa = ?';
            $parametros[] = (int) $filtros['activa'];
        }
        if (!empty($filtros['busqueda'])) {
            $desde .= ' AND (p.codigo LIKE ? OR p.descripcion LIKE ?)';
            $termino = '%' . $filtros['busqueda'] . '%';
            $parametros[] = $termino;
            $parametros[] = $termino;
        }

        return Paginador::consultar(
            'SELECT p.*, pr.nombre AS producto_nombre, c.nombre AS categoria_nombre',
            $desde,
            $parametros,
            $filtros,
            self::ORDEN,
            'codigo',
            [self::class, 'normalizar']
        );
    }

    public static function eliminar(int $id): void
    {
        $promocion = self::porId($id);
        Bd::ejecutar('UPDATE promociones SET activa = 0, deleted_at = NOW() WHERE id = ?', [$id]);

        \Kerico\Nucleo\Bitacora::registrar(
            'PROMOCION_ELIMINAR',
            'Descuentos',
            'Promoción ' . $promocion['codigo'] . ' enviada a la papelera (borrado lógico)',
            'Advertencia',
            'promociones',
            $id,
            ['deleted_at' => null, 'activa' => $promocion['activa']],
            ['deleted_at' => date('Y-m-d H:i:s'), 'activa' => false]
        );
    }

    public static function restaurar(int $id): array
    {
        $fila = Bd::primero('SELECT * FROM promociones WHERE id = ? AND deleted_at IS NOT NULL', [$id]);
        if ($fila === null) {
            throw ErrorHttp::noEncontrado('La promoción no está en la papelera');
        }

        Bd::ejecutar('UPDATE promociones SET activa = 1, deleted_at = NULL WHERE id = ?', [$id]);

        \Kerico\Nucleo\Bitacora::registrar(
            'PROMOCION_RESTAURAR',
            'Descuentos',
            'Promoción ' . $fila['codigo'] . ' restaurada desde la papelera',
            'Advertencia',
            'promociones',
            $id
        );

        return self::porId($id);
    }

    public static function porId(int $id): array
    {
        $fila = Bd::primero(
            'SELECT p.*, pr.nombre AS producto_nombre, c.nombre AS categoria_nombre
             FROM promociones p
             LEFT JOIN productos pr ON pr.id = p.producto_id
             LEFT JOIN categorias c ON c.id = p.categoria_id
             WHERE p.id = ? AND p.deleted_at IS NULL',
            [$id]
        );
        if ($fila === null) {
            throw ErrorHttp::noEncontrado('La promoción no existe');
        }
        return self::normalizar($fila);
    }

    public static function porCodigo(string $codigo): ?array
    {
        $fila = Bd::primero(
            'SELECT p.*, pr.nombre AS producto_nombre, c.nombre AS categoria_nombre
             FROM promociones p
             LEFT JOIN productos pr ON pr.id = p.producto_id
             LEFT JOIN categorias c ON c.id = p.categoria_id
             WHERE UPPER(p.codigo) = UPPER(?) AND p.deleted_at IS NULL',
            [$codigo]
        );
        return $fila === null ? null : self::normalizar($fila);
    }

    public static function normalizar(array $f): array
    {
        return [
            'id'                    => (int) $f['id'],
            'codigo'                => (string) $f['codigo'],
            'descripcion'           => (string) $f['descripcion'],
            'tipo'                  => (string) $f['tipo'],
            'valor'                 => (float) $f['valor'],
            'alcance'               => (string) $f['alcance'],
            'producto_id'           => $f['producto_id'] === null ? null : (int) $f['producto_id'],
            'producto_nombre'       => (string) ($f['producto_nombre'] ?? ''),
            'categoria_id'          => $f['categoria_id'] === null ? null : (int) $f['categoria_id'],
            'categoria_nombre'      => (string) ($f['categoria_nombre'] ?? ''),
            'monto_minimo'          => (float) $f['monto_minimo'],
            'fecha_inicio'          => $f['fecha_inicio'],
            'fecha_fin'             => $f['fecha_fin'],
            'usos_maximos'          => (int) $f['usos_maximos'],
            'usos_actuales'         => (int) $f['usos_actuales'],
            'requiere_autorizacion' => (int) $f['requiere_autorizacion'] === 1,
            'activa'                => (int) $f['activa'] === 1,
        ];
    }

    public static function lineasDesdeItems(array $items): array
    {
        $lineas = [];

        foreach ($items as $item) {
            $productoId = (int) ($item['producto_id'] ?? 0);
            if ($productoId <= 0) {
                continue;
            }

            $producto = Bd::primero(
                'SELECT id, categoria_id, precio_venta FROM productos
                 WHERE id = ? AND deleted_at IS NULL AND activo = 1',
                [$productoId]
            );
            if ($producto === null) {
                continue;
            }

            $cantidad = (float) ($item['cantidad'] ?? 0);
            if ($cantidad <= 0) {
                continue;
            }

            $lineas[] = [
                'producto_id'  => (int) $producto['id'],
                'categoria_id' => (int) $producto['categoria_id'],
                'total'        => round((float) $producto['precio_venta'] * $cantidad, 2),
            ];
        }

        return $lineas;
    }

    public static function evaluar(array $promocion, array $lineas, float $bruto): array
    {
        $hoy = date('Y-m-d');

        if (!$promocion['activa']) {
            return ['valido' => false, 'motivo' => 'La promoción se encuentra inactiva', 'descuento' => 0.0];
        }
        if ($promocion['fecha_inicio'] !== null && $hoy < $promocion['fecha_inicio']) {
            return ['valido' => false, 'motivo' => 'La promoción aun no inicia', 'descuento' => 0.0];
        }
        if ($promocion['fecha_fin'] !== null && $hoy > $promocion['fecha_fin']) {
            return ['valido' => false, 'motivo' => 'La promoción ya vencio', 'descuento' => 0.0];
        }
        if ($promocion['usos_maximos'] > 0 && $promocion['usos_actuales'] >= $promocion['usos_maximos']) {
            return ['valido' => false, 'motivo' => 'La promoción alcanzo el maximo de usos', 'descuento' => 0.0];
        }
        if ($promocion['monto_minimo'] > 0 && $bruto < $promocion['monto_minimo']) {
            return [
                'valido'    => false,
                'motivo'    => 'La venta no alcanza el monto mínimo de ' . number_format($promocion['monto_minimo'], 0, ',', '.'),
                'descuento' => 0.0,
            ];
        }

        $base = 0.0;
        foreach ($lineas as $linea) {
            if ($promocion['alcance'] === 'venta') {
                $base += $linea['total'];
            } elseif ($promocion['alcance'] === 'producto' && (int) $linea['producto_id'] === (int) $promocion['producto_id']) {
                $base += $linea['total'];
            } elseif ($promocion['alcance'] === 'categoria' && (int) $linea['categoria_id'] === (int) $promocion['categoria_id']) {
                $base += $linea['total'];
            }
        }

        if ($base <= 0) {
            return ['valido' => false, 'motivo' => 'La venta no incluye productos que apliquen a la promoción', 'descuento' => 0.0];
        }

        $descuento = $promocion['tipo'] === 'porcentaje'
            ? $base * ($promocion['valor'] / 100)
            : min($promocion['valor'], $base);

        $descuento = round(min($descuento, $bruto), 2);

        return ['valido' => true, 'motivo' => '', 'descuento' => $descuento, 'base_aplicada' => round($base, 2)];
    }

    public static function crear(array $datos, int $usuarioId): int
    {
        return Bd::insertar(
            'INSERT INTO promociones
                (codigo, descripcion, tipo, valor, alcance, producto_id, categoria_id, monto_minimo,
                 fecha_inicio, fecha_fin, usos_maximos, requiere_autorizacion, activa, creado_por)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [
                $datos['codigo'],
                $datos['descripcion'],
                $datos['tipo'],
                $datos['valor'],
                $datos['alcance'],
                $datos['producto_id'],
                $datos['categoria_id'],
                $datos['monto_minimo'],
                $datos['fecha_inicio'],
                $datos['fecha_fin'],
                $datos['usos_maximos'],
                $datos['requiere_autorizacion'] ? 1 : 0,
                $datos['activa'] ? 1 : 0,
                $usuarioId,
            ]
        );
    }

    public static function actualizar(int $id, array $datos): void
    {
        Bd::ejecutar(
            'UPDATE promociones SET
                codigo = ?, descripcion = ?, tipo = ?, valor = ?, alcance = ?, producto_id = ?,
                categoria_id = ?, monto_minimo = ?, fecha_inicio = ?, fecha_fin = ?, usos_maximos = ?,
                requiere_autorizacion = ?, activa = ?
             WHERE id = ?',
            [
                $datos['codigo'],
                $datos['descripcion'],
                $datos['tipo'],
                $datos['valor'],
                $datos['alcance'],
                $datos['producto_id'],
                $datos['categoria_id'],
                $datos['monto_minimo'],
                $datos['fecha_inicio'],
                $datos['fecha_fin'],
                $datos['usos_maximos'],
                $datos['requiere_autorizacion'] ? 1 : 0,
                $datos['activa'] ? 1 : 0,
                $id,
            ]
        );
    }

    public static function alternar(int $id): array
    {
        $promocion = self::porId($id);
        Bd::ejecutar('UPDATE promociones SET activa = ? WHERE id = ?', [$promocion['activa'] ? 0 : 1, $id]);
        return self::porId($id);
    }

    public static function historial(array $filtros = []): array
    {
        $sql = 'SELECT d.*, v.folio, v.fecha AS fecha_venta,
                       CONCAT(u.nombre, " ", u.apellido) AS autorizado
                FROM descuentos_aplicados d
                JOIN ventas v ON v.id = d.venta_id
                LEFT JOIN usuarios u ON u.id = d.autorizado_por
                WHERE 1 = 1';
        $parametros = [];

        if (!empty($filtros['desde'])) {
            $sql .= ' AND d.creado_en >= ?';
            $parametros[] = $filtros['desde'] . ' 00:00:00';
        }
        if (!empty($filtros['hasta'])) {
            $sql .= ' AND d.creado_en <= ?';
            $parametros[] = $filtros['hasta'] . ' 23:59:59';
        }
        if (!empty($filtros['codigo'])) {
            $sql .= ' AND d.codigo = ?';
            $parametros[] = $filtros['codigo'];
        }

        $sql .= ' ORDER BY d.creado_en DESC LIMIT 500';

        return array_map(static fn (array $d): array => [
            'id'              => (int) $d['id'],
            'venta_id'        => (int) $d['venta_id'],
            'folio'           => (string) $d['folio'],
            'codigo'          => (string) $d['codigo'],
            'tipo'            => (string) $d['tipo'],
            'valor'           => (float) $d['valor'],
            'motivo'          => (string) $d['motivo'],
            'valor_original'  => (float) $d['valor_original'],
            'valor_descuento' => (float) $d['valor_descuento'],
            'valor_final'     => (float) $d['valor_final'],
            'autorizado_por'  => (string) ($d['autorizado'] ?? ''),
            'fecha'           => (string) $d['creado_en'],
        ], Bd::consultar($sql, $parametros));
    }
}
