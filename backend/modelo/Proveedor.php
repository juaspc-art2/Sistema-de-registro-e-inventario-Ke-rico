<?php

declare(strict_types=1);

namespace Kerico\Modelo;

use Kerico\Nucleo\Autenticacion;
use Kerico\Nucleo\Bd;
use Kerico\Nucleo\Bitacora;
use Kerico\Nucleo\ErrorHttp;
use Kerico\Nucleo\Paginador;

final class Proveedor
{
    public const ESTADOS = ['Activo', 'Inactivo', 'En revision'];

    public static function listar(array $filtros = []): array
    {
        $sql = 'SELECT p.*,
                       (SELECT COUNT(*) FROM productos pr WHERE pr.proveedor_id = p.id) AS productos,
                       (SELECT COUNT(*) FROM compras c WHERE c.proveedor_id = p.id) AS compras,
                       (SELECT COALESCE(SUM(c.total), 0) FROM compras c WHERE c.proveedor_id = p.id AND c.estado = "Recibida") AS monto_comprado,
                       (SELECT MAX(c.fecha) FROM compras c WHERE c.proveedor_id = p.id) AS ultima_compra
                FROM proveedores p WHERE p.deleted_at IS NULL';
        $parametros = [];

        if (!empty($filtros['busqueda'])) {
            $sql .= ' AND (p.nombre_empresa LIKE ? OR p.nit LIKE ? OR p.tipo_productos LIKE ?)';
            $termino = '%' . $filtros['busqueda'] . '%';
            $parametros[] = $termino;
            $parametros[] = $termino;
            $parametros[] = $termino;
        }
        if (!empty($filtros['estado'])) {
            $sql .= ' AND p.estado = ?';
            $parametros[] = $filtros['estado'];
        }

        $sql .= ' ORDER BY p.nombre_empresa';

        return array_map([self::class, 'normalizar'], Bd::consultar($sql, $parametros));
    }

    private const ORDEN = [
        'nombre'       => 'p.nombre_empresa',
        'nit'          => 'p.nit',
        'estado'       => 'p.estado',
        'calificacion' => 'p.calificacion',
        'productos'    => 'productos',
        'monto'        => 'monto_comprado',
    ];

    public static function paginar(array $filtros = []): array
    {
        $desde = ' FROM proveedores p WHERE p.deleted_at IS NULL';
        $parametros = [];

        if (!empty($filtros['busqueda'])) {
            $desde .= ' AND (p.nombre_empresa LIKE ? OR p.nit LIKE ? OR p.tipo_productos LIKE ?)';
            $termino = '%' . $filtros['busqueda'] . '%';
            $parametros[] = $termino;
            $parametros[] = $termino;
            $parametros[] = $termino;
        }
        if (!empty($filtros['estado'])) {
            $desde .= ' AND p.estado = ?';
            $parametros[] = $filtros['estado'];
        }

        return Paginador::consultar(
            'SELECT p.*,
                    (SELECT COUNT(*) FROM productos pr WHERE pr.proveedor_id = p.id AND pr.deleted_at IS NULL) AS productos,
                    (SELECT COUNT(*) FROM compras c WHERE c.proveedor_id = p.id) AS compras,
                    (SELECT COALESCE(SUM(c.total), 0) FROM compras c WHERE c.proveedor_id = p.id AND c.estado = "Recibida") AS monto_comprado,
                    (SELECT MAX(c.fecha) FROM compras c WHERE c.proveedor_id = p.id) AS ultima_compra',
            $desde,
            $parametros,
            $filtros,
            self::ORDEN,
            'nombre',
            [self::class, 'normalizar']
        );
    }

    public static function papelera(): array
    {
        return array_map(static fn (array $p): array => [
            'id'             => (int) $p['id'],
            'nombre_empresa' => (string) $p['nombre_empresa'],
            'nit'            => (string) $p['nit'],
            'deleted_at'     => (string) $p['deleted_at'],
        ], Bd::consultar(
            'SELECT id, nombre_empresa, nit, deleted_at FROM proveedores
             WHERE deleted_at IS NOT NULL ORDER BY deleted_at DESC'
        ));
    }

    public static function porId(int $id): array
    {
        $fila = Bd::primero(
            'SELECT p.*,
                    (SELECT COUNT(*) FROM productos pr WHERE pr.proveedor_id = p.id) AS productos,
                    (SELECT COUNT(*) FROM compras c WHERE c.proveedor_id = p.id) AS compras,
                    (SELECT COALESCE(SUM(c.total), 0) FROM compras c WHERE c.proveedor_id = p.id AND c.estado = "Recibida") AS monto_comprado,
                    (SELECT MAX(c.fecha) FROM compras c WHERE c.proveedor_id = p.id) AS ultima_compra
             FROM proveedores p WHERE p.id = ? AND p.deleted_at IS NULL',
            [$id]
        );
        if ($fila === null) {
            throw ErrorHttp::noEncontrado('El proveedor no existe');
        }
        $proveedor = self::normalizar($fila);
        $proveedor['catalogo'] = array_map(static fn (array $p): array => [
            'id'           => (int) $p['id'],
            'sku'          => (string) $p['sku'],
            'nombre'       => (string) $p['nombre'],
            'precio_costo' => (float) $p['precio_costo'],
            'stock_actual' => (float) $p['stock_actual'],
        ], Bd::consultar('SELECT id, sku, nombre, precio_costo, stock_actual FROM productos WHERE proveedor_id = ? AND deleted_at IS NULL ORDER BY nombre', [$id]));

        return $proveedor;
    }

    public static function normalizar(array $f): array
    {
        return [
            'id'              => (int) $f['id'],
            'nombre_empresa'  => (string) $f['nombre_empresa'],
            'nit'             => (string) $f['nit'],
            'telefono'        => (string) $f['telefono'],
            'correo'          => (string) $f['correo'],
            'direccion'       => (string) $f['direccion'],
            'contacto_nombre' => (string) $f['contacto_nombre'],
            'tipo_productos'  => (string) $f['tipo_productos'],
            'estado'          => (string) $f['estado'],
            'calificacion'    => (float) $f['calificacion'],
            'notas'           => (string) $f['notas'],
            'productos'       => (int) ($f['productos'] ?? 0),
            'compras'         => (int) ($f['compras'] ?? 0),
            'monto_comprado'  => (float) ($f['monto_comprado'] ?? 0),
            'ultima_compra'   => $f['ultima_compra'],
        ];
    }

    public static function crear(array $datos): array
    {
        self::validarNit($datos['nit'], null);

        $id = Bd::insertar(
            'INSERT INTO proveedores
                (nombre_empresa, nit, telefono, correo, direccion, contacto_nombre, tipo_productos, estado, calificacion, notas)
             VALUES (?,?,?,?,?,?,?,?,?,?)',
            [
                $datos['nombre_empresa'], $datos['nit'], $datos['telefono'], $datos['correo'],
                $datos['direccion'], $datos['contacto_nombre'], $datos['tipo_productos'],
                $datos['estado'], $datos['calificacion'], $datos['notas'],
            ]
        );

        Bitacora::registrar(
            'PROVEEDOR_CREAR',
            'Proveedores',
            'Proveedor ' . $datos['nombre_empresa'] . ' registrado con NIT ' . $datos['nit'],
            'Info',
            'proveedores',
            $id
        );

        return self::porId($id);
    }

    public static function actualizar(int $id, array $datos): array
    {
        $antes = Bd::primero('SELECT * FROM proveedores WHERE id = ?', [$id]);
        if ($antes === null) {
            throw ErrorHttp::noEncontrado('El proveedor no existe');
        }

        self::validarNit($datos['nit'], $id);

        Bd::ejecutar(
            'UPDATE proveedores SET
                nombre_empresa = ?, nit = ?, telefono = ?, correo = ?, direccion = ?,
                contacto_nombre = ?, tipo_productos = ?, estado = ?, calificacion = ?, notas = ?
             WHERE id = ?',
            [
                $datos['nombre_empresa'], $datos['nit'], $datos['telefono'], $datos['correo'],
                $datos['direccion'], $datos['contacto_nombre'], $datos['tipo_productos'],
                $datos['estado'], $datos['calificacion'], $datos['notas'], $id,
            ]
        );

        $nivel = $antes['estado'] !== $datos['estado'] ? 'Advertencia' : 'Info';
        $descripcion = $antes['estado'] !== $datos['estado']
            ? $datos['nombre_empresa'] . ' pasa a estado ' . $datos['estado']
            : 'Proveedor ' . $datos['nombre_empresa'] . ' actualizado';

        Bitacora::registrar(
            'PROVEEDOR_ACTUALIZAR',
            'Proveedores',
            $descripcion,
            $nivel,
            'proveedores',
            $id,
            ['estado' => $antes['estado']],
            ['estado' => $datos['estado']]
        );

        return self::porId($id);
    }

    public static function eliminar(int $id): void
    {
        $proveedor = Bd::primero('SELECT * FROM proveedores WHERE id = ? AND deleted_at IS NULL', [$id]);
        if ($proveedor === null) {
            throw ErrorHttp::noEncontrado('El proveedor no existe');
        }

        Bd::ejecutar("UPDATE proveedores SET estado = 'Inactivo', deleted_at = NOW() WHERE id = ?", [$id]);

        Bitacora::registrar(
            'PROVEEDOR_ELIMINAR',
            'Proveedores',
            'Proveedor ' . $proveedor['nombre_empresa'] . ' enviado a la papelera (borrado lógico)',
            'Advertencia',
            'proveedores',
            $id,
            ['deleted_at' => null, 'estado' => (string) $proveedor['estado']],
            ['deleted_at' => date('Y-m-d H:i:s'), 'estado' => 'Inactivo']
        );
    }

    public static function restaurar(int $id): array
    {
        $proveedor = Bd::primero('SELECT * FROM proveedores WHERE id = ? AND deleted_at IS NOT NULL', [$id]);
        if ($proveedor === null) {
            throw ErrorHttp::noEncontrado('El proveedor no está en la papelera');
        }

        Bd::ejecutar("UPDATE proveedores SET estado = 'Activo', deleted_at = NULL WHERE id = ?", [$id]);

        Bitacora::registrar(
            'PROVEEDOR_RESTAURAR',
            'Proveedores',
            'Proveedor ' . $proveedor['nombre_empresa'] . ' restaurado desde la papelera',
            'Advertencia',
            'proveedores',
            $id
        );

        return self::porId($id);
    }

    private static function validarNit(string $nit, ?int $excluir): void
    {
        $sql = 'SELECT id FROM proveedores WHERE nit = ?';
        $parametros = [$nit];
        if ($excluir !== null) {
            $sql .= ' AND id <> ?';
            $parametros[] = $excluir;
        }
        if (Bd::valor($sql, $parametros) !== null) {
            throw ErrorHttp::validacion('El NIT ya está registrado en otro proveedor', ['nit' => 'Debe ser único']);
        }
    }

    public static function registrarCompra(array $datos): array
    {
        $items = $datos['items'] ?? [];
        if (!is_array($items) || $items === []) {
            throw ErrorHttp::validacion('La compra debe incluir al menos un producto', ['items' => 'Lista vacia']);
        }

        $proveedorId = (int) ($datos['proveedor_id'] ?? 0);
        $usuarioId = Autenticacion::idUsuario();

        return Bd::transaccion(static function () use ($items, $proveedorId, $usuarioId, $datos): array {
            $proveedor = Bd::primero('SELECT * FROM proveedores WHERE id = ?', [$proveedorId]);
            if ($proveedor === null) {
                throw ErrorHttp::noEncontrado('El proveedor no existe');
            }

            $subtotal = 0.0;
            $lineas = [];

            foreach ($items as $indice => $item) {
                $productoId = (int) ($item['producto_id'] ?? 0);
                $cantidad = (float) ($item['cantidad'] ?? 0);
                $costo = (float) ($item['costo_unitario'] ?? 0);

                if ($cantidad <= 0) {
                    throw ErrorHttp::validacion(
                        'La cantidad debe ser mayor que cero en la línea ' . ($indice + 1),
                        ['items' => 'Cantidad inválida']
                    );
                }
                if ($costo < 0) {
                    throw ErrorHttp::validacion(
                        'El costo no puede ser negativo en la línea ' . ($indice + 1),
                        ['items' => 'Costo inválido']
                    );
                }

                $producto = Bd::primero('SELECT * FROM productos WHERE id = ?', [$productoId]);
                if ($producto === null) {
                    throw ErrorHttp::noEncontrado('El producto con identificador ' . $productoId . ' no existe');
                }

                $lineas[] = [
                    'producto_id'    => $productoId,
                    'nombre'         => (string) $producto['nombre'],
                    'cantidad'       => $cantidad,
                    'costo_unitario' => $costo,
                    'subtotal'       => round($cantidad * $costo, 2),
                ];
                $subtotal += $cantidad * $costo;
            }

            $subtotal = round($subtotal, 2);
            $impuesto = round((float) ($datos['impuesto'] ?? 0), 2);
            $total = round($subtotal + $impuesto, 2);
            $estado = in_array($datos['estado'] ?? 'Recibida', ['Recibida', 'Pendiente'], true)
                ? $datos['estado']
                : 'Recibida';

            $numeroTemporal = 'TMP-' . bin2hex(random_bytes(6));
            $compraId = Bd::insertar(
                'INSERT INTO compras (numero, proveedor_id, usuario_id, subtotal, impuesto, total, estado, observaciones)
                 VALUES (?,?,?,?,?,?,?,?)',
                [
                    $numeroTemporal, $proveedorId, $usuarioId, $subtotal, $impuesto, $total, $estado,
                    mb_substr((string) ($datos['observaciones'] ?? ''), 0, 255),
                ]
            );

            $numero = 'OC-' . str_pad((string) $compraId, 5, '0', STR_PAD_LEFT);
            Bd::ejecutar('UPDATE compras SET numero = ? WHERE id = ?', [$numero, $compraId]);

            foreach ($lineas as $linea) {
                Bd::ejecutar(
                    'INSERT INTO compra_detalle (compra_id, producto_id, cantidad, costo_unitario, subtotal)
                     VALUES (?,?,?,?,?)',
                    [$compraId, $linea['producto_id'], $linea['cantidad'], $linea['costo_unitario'], $linea['subtotal']]
                );

                if ($estado === 'Recibida') {
                    Bd::ejecutar(
                        'UPDATE productos SET precio_costo = ? WHERE id = ?',
                        [$linea['costo_unitario'], $linea['producto_id']]
                    );

                    Inventario::registrarMovimiento(
                        $linea['producto_id'],
                        'Entrada',
                        $linea['cantidad'],
                        'Compra ' . $numero,
                        'Compra',
                        $compraId,
                        'Ingreso de mercancía del proveedor ' . $proveedor['nombre_empresa'],
                        $usuarioId
                    );
                }
            }

            Bitacora::registrar(
                'COMPRA_REGISTRAR',
                'Compras',
                'Compra ' . $numero . ' ' . strtolower($estado) . ' del proveedor ' . $proveedor['nombre_empresa'],
                'Info',
                'compras',
                $compraId,
                null,
                ['total' => $total, 'lineas' => count($lineas)]
            );

            return self::compra($compraId);
        });
    }

    public static function compra(int $id): array
    {
        $compra = Bd::primero(
            'SELECT c.*, p.nombre_empresa, p.nit, CONCAT(u.nombre, " ", u.apellido) AS usuario
             FROM compras c
             JOIN proveedores p ON p.id = c.proveedor_id
             JOIN usuarios u ON u.id = c.usuario_id
             WHERE c.id = ?',
            [$id]
        );
        if ($compra === null) {
            throw ErrorHttp::noEncontrado('La compra no existe');
        }

        $detalle = Bd::consultar(
            'SELECT d.*, p.sku, p.nombre, p.unidad_medida
             FROM compra_detalle d JOIN productos p ON p.id = d.producto_id
             WHERE d.compra_id = ?',
            [$id]
        );

        return [
            'id'            => (int) $compra['id'],
            'numero'        => (string) $compra['numero'],
            'proveedor_id'  => (int) $compra['proveedor_id'],
            'proveedor'     => (string) $compra['nombre_empresa'],
            'nit'           => (string) $compra['nit'],
            'usuario'       => (string) $compra['usuario'],
            'fecha'         => (string) $compra['fecha'],
            'subtotal'      => (float) $compra['subtotal'],
            'impuesto'      => (float) $compra['impuesto'],
            'total'         => (float) $compra['total'],
            'estado'        => (string) $compra['estado'],
            'observaciones' => (string) $compra['observaciones'],
            'items'         => array_map(static fn (array $d): array => [
                'producto_id'    => (int) $d['producto_id'],
                'sku'            => (string) $d['sku'],
                'nombre'         => (string) $d['nombre'],
                'unidad_medida'  => (string) $d['unidad_medida'],
                'cantidad'       => (float) $d['cantidad'],
                'costo_unitario' => (float) $d['costo_unitario'],
                'subtotal'       => (float) $d['subtotal'],
            ], $detalle),
        ];
    }

    public static function compras(array $filtros = []): array
    {
        $sql = 'SELECT c.*, p.nombre_empresa, CONCAT(u.nombre, " ", u.apellido) AS usuario,
                       (SELECT COUNT(*) FROM compra_detalle d WHERE d.compra_id = c.id) AS lineas
                FROM compras c
                JOIN proveedores p ON p.id = c.proveedor_id
                JOIN usuarios u ON u.id = c.usuario_id
                WHERE 1 = 1';
        $parametros = [];

        if (!empty($filtros['proveedor_id'])) {
            $sql .= ' AND c.proveedor_id = ?';
            $parametros[] = (int) $filtros['proveedor_id'];
        }
        if (!empty($filtros['estado'])) {
            $sql .= ' AND c.estado = ?';
            $parametros[] = $filtros['estado'];
        }
        if (!empty($filtros['desde'])) {
            $sql .= ' AND c.fecha >= ?';
            $parametros[] = $filtros['desde'] . ' 00:00:00';
        }
        if (!empty($filtros['hasta'])) {
            $sql .= ' AND c.fecha <= ?';
            $parametros[] = $filtros['hasta'] . ' 23:59:59';
        }

        $sql .= ' ORDER BY c.fecha DESC LIMIT 300';

        return array_map(static fn (array $c): array => [
            'id'        => (int) $c['id'],
            'numero'    => (string) $c['numero'],
            'proveedor' => (string) $c['nombre_empresa'],
            'usuario'   => (string) $c['usuario'],
            'fecha'     => (string) $c['fecha'],
            'lineas'    => (int) $c['lineas'],
            'subtotal'  => (float) $c['subtotal'],
            'impuesto'  => (float) $c['impuesto'],
            'total'     => (float) $c['total'],
            'estado'    => (string) $c['estado'],
        ], Bd::consultar($sql, $parametros));
    }

    public static function recibirCompra(int $id): array
    {
        $usuarioId = Autenticacion::idUsuario();

        return Bd::transaccion(static function () use ($id, $usuarioId): array {
            $compra = Bd::primero('SELECT * FROM compras WHERE id = ? FOR UPDATE', [$id]);
            if ($compra === null) {
                throw ErrorHttp::noEncontrado('La compra no existe');
            }
            if ($compra['estado'] !== 'Pendiente') {
                throw ErrorHttp::conflicto('Solo se pueden recibir compras en estado pendiente');
            }

            $detalle = Bd::consultar('SELECT * FROM compra_detalle WHERE compra_id = ?', [$id]);
            foreach ($detalle as $linea) {
                Bd::ejecutar(
                    'UPDATE productos SET precio_costo = ? WHERE id = ?',
                    [(float) $linea['costo_unitario'], (int) $linea['producto_id']]
                );
                Inventario::registrarMovimiento(
                    (int) $linea['producto_id'],
                    'Entrada',
                    (float) $linea['cantidad'],
                    'Compra ' . $compra['numero'],
                    'Compra',
                    $id,
                    'Recepción de mercancía pendiente',
                    $usuarioId
                );
            }

            Bd::ejecutar("UPDATE compras SET estado = 'Recibida' WHERE id = ?", [$id]);

            Bitacora::registrar(
                'COMPRA_RECIBIR',
                'Compras',
                'Compra ' . $compra['numero'] . ' recibida e ingresada al inventario',
                'Info',
                'compras',
                $id
            );

            return self::compra($id);
        });
    }
}
