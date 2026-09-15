<?php

declare(strict_types=1);

namespace Kerico\Modelo;

use Kerico\Nucleo\Autenticacion;
use Kerico\Nucleo\Bd;
use Kerico\Nucleo\Bitacora;
use Kerico\Nucleo\ErrorHttp;

final class Catalogo
{
    public static function categorias(): array
    {
        return array_map(static fn (array $c): array => [
            'id'          => (int) $c['id'],
            'nombre'      => (string) $c['nombre'],
            'descripcion' => (string) $c['descripcion'],
            'activo'      => (int) $c['activo'] === 1,
            'productos'   => (int) $c['productos'],
        ], Bd::consultar(
            'SELECT c.*, (SELECT COUNT(*) FROM productos p WHERE p.categoria_id = c.id AND p.deleted_at IS NULL) AS productos
             FROM categorias c WHERE c.deleted_at IS NULL ORDER BY c.nombre'
        ));
    }

    public static function crearProducto(array $datos): array
    {
        self::validarSkuUnico($datos['sku'], null);

        $id = Bd::insertar(
            'INSERT INTO productos
                (sku, nombre, descripcion, categoria_id, proveedor_id, unidad_medida, precio_costo,
                 precio_venta, iva_porcentaje, stock_actual, punto_reorden, stock_maximo, es_insumo, imagen, activo)
             VALUES (?,?,?,?,?,?,?,?,?,0,?,?,?,?,?)',
            [
                $datos['sku'], $datos['nombre'], $datos['descripcion'], $datos['categoria_id'],
                $datos['proveedor_id'], $datos['unidad_medida'], $datos['precio_costo'],
                $datos['precio_venta'], $datos['iva_porcentaje'], $datos['punto_reorden'],
                $datos['stock_maximo'], $datos['es_insumo'] ? 1 : 0, $datos['imagen'],
                $datos['activo'] ? 1 : 0,
            ]
        );

        $stockInicial = (float) ($datos['stock_inicial'] ?? 0);
        if ($stockInicial > 0) {
            Inventario::registrarMovimiento(
                $id,
                'Entrada',
                $stockInicial,
                'Alta de producto',
                'Inicial',
                null,
                'Existencias iniciales registradas al crear el producto'
            );
        } else {
            Inventario::evaluarAlerta($id, 0.0, (float) $datos['punto_reorden'], (string) $datos['nombre'], (string) $datos['unidad_medida']);
        }

        Bitacora::registrar(
            'PRODUCTO_CREAR',
            'Inventario',
            'Producto ' . $datos['nombre'] . ' (' . $datos['sku'] . ') creado',
            'Info',
            'productos',
            $id,
            null,
            $datos
        );

        return Inventario::producto($id);
    }

    public static function actualizarProducto(int $id, array $datos): array
    {
        $antes = Bd::primero('SELECT * FROM productos WHERE id = ?', [$id]);
        if ($antes === null) {
            throw ErrorHttp::noEncontrado('El producto no existe');
        }

        self::validarSkuUnico($datos['sku'], $id);

        $cambiaPrecio = (float) $antes['precio_venta'] !== (float) $datos['precio_venta']
            || (float) $antes['precio_costo'] !== (float) $datos['precio_costo'];

        if ($cambiaPrecio) {
            Autenticacion::exigir('productos.precio');
        }

        Bd::ejecutar(
            'UPDATE productos SET
                sku = ?, nombre = ?, descripcion = ?, categoria_id = ?, proveedor_id = ?, unidad_medida = ?,
                precio_costo = ?, precio_venta = ?, iva_porcentaje = ?, punto_reorden = ?, stock_maximo = ?,
                es_insumo = ?, imagen = ?, activo = ?
             WHERE id = ?',
            [
                $datos['sku'], $datos['nombre'], $datos['descripcion'], $datos['categoria_id'],
                $datos['proveedor_id'], $datos['unidad_medida'], $datos['precio_costo'],
                $datos['precio_venta'], $datos['iva_porcentaje'], $datos['punto_reorden'],
                $datos['stock_maximo'], $datos['es_insumo'] ? 1 : 0, $datos['imagen'],
                $datos['activo'] ? 1 : 0, $id,
            ]
        );

        Inventario::evaluarAlerta(
            $id,
            (float) $antes['stock_actual'],
            (float) $datos['punto_reorden'],
            (string) $datos['nombre'],
            (string) $datos['unidad_medida']
        );

        if ($cambiaPrecio) {
            Bitacora::registrar(
                'PRECIO_MODIFICAR',
                'Inventario',
                'Precios de ' . $datos['nombre'] . ' actualizados: venta '
                . number_format((float) $antes['precio_venta'], 0, ',', '.') . ' a '
                . number_format((float) $datos['precio_venta'], 0, ',', '.'),
                'Advertencia',
                'productos',
                $id,
                ['precio_venta' => (float) $antes['precio_venta'], 'precio_costo' => (float) $antes['precio_costo']],
                ['precio_venta' => (float) $datos['precio_venta'], 'precio_costo' => (float) $datos['precio_costo']]
            );
        } else {
            Bitacora::registrar(
                'PRODUCTO_ACTUALIZAR',
                'Inventario',
                'Producto ' . $datos['nombre'] . ' actualizado',
                'Info',
                'productos',
                $id
            );
        }

        return Inventario::producto($id);
    }

    public static function eliminarProducto(int $id): void
    {
        $producto = Bd::primero('SELECT * FROM productos WHERE id = ? AND deleted_at IS NULL', [$id]);
        if ($producto === null) {
            throw ErrorHttp::noEncontrado('El producto no existe');
        }

        Bd::ejecutar('UPDATE productos SET activo = 0, deleted_at = NOW() WHERE id = ?', [$id]);
        Bd::ejecutar(
            "UPDATE alertas_stock SET estado = 'Resuelta', resuelta_en = NOW()
             WHERE producto_id = ? AND estado <> 'Resuelta'",
            [$id]
        );

        Bitacora::registrar(
            'PRODUCTO_ELIMINAR',
            'Inventario',
            'Producto ' . $producto['nombre'] . ' enviado a la papelera (borrado lógico)',
            'Advertencia',
            'productos',
            $id,
            ['deleted_at' => null, 'activo' => (int) $producto['activo']],
            ['deleted_at' => date('Y-m-d H:i:s'), 'activo' => 0]
        );
    }

    public static function restaurarProducto(int $id): array
    {
        $producto = Bd::primero('SELECT * FROM productos WHERE id = ? AND deleted_at IS NOT NULL', [$id]);
        if ($producto === null) {
            throw ErrorHttp::noEncontrado('El producto no está en la papelera');
        }

        Bd::ejecutar('UPDATE productos SET activo = 1, deleted_at = NULL WHERE id = ?', [$id]);

        Inventario::evaluarAlerta(
            $id,
            (float) $producto['stock_actual'],
            (float) $producto['punto_reorden'],
            (string) $producto['nombre'],
            (string) $producto['unidad_medida']
        );

        Bitacora::registrar(
            'PRODUCTO_RESTAURAR',
            'Inventario',
            'Producto ' . $producto['nombre'] . ' restaurado desde la papelera',
            'Advertencia',
            'productos',
            $id
        );

        return Inventario::producto($id);
    }

    public static function papelera(): array
    {
        return array_map(static fn (array $p): array => [
            'id'         => (int) $p['id'],
            'sku'        => (string) $p['sku'],
            'nombre'     => (string) $p['nombre'],
            'categoria'  => (string) $p['categoria'],
            'deleted_at' => (string) $p['deleted_at'],
        ], Bd::consultar(
            'SELECT p.id, p.sku, p.nombre, p.deleted_at, c.nombre AS categoria
             FROM productos p JOIN categorias c ON c.id = p.categoria_id
             WHERE p.deleted_at IS NOT NULL
             ORDER BY p.deleted_at DESC'
        ));
    }

    public static function definirPuntoReorden(int $id, float $puntoReorden): array
    {
        $producto = Bd::primero('SELECT * FROM productos WHERE id = ?', [$id]);
        if ($producto === null) {
            throw ErrorHttp::noEncontrado('El producto no existe');
        }
        if ($puntoReorden < 0) {
            throw ErrorHttp::validacion('El punto de reorden no puede ser negativo', ['punto_reorden' => 'Valor inválido']);
        }

        Bd::ejecutar('UPDATE productos SET punto_reorden = ? WHERE id = ?', [$puntoReorden, $id]);

        Inventario::evaluarAlerta(
            $id,
            (float) $producto['stock_actual'],
            $puntoReorden,
            (string) $producto['nombre'],
            (string) $producto['unidad_medida']
        );

        Bitacora::registrar(
            'REORDEN_CONFIGURAR',
            'Alertas',
            'Punto de reorden de ' . $producto['nombre'] . ' definido en ' . $puntoReorden,
            'Info',
            'productos',
            $id,
            ['punto_reorden' => (float) $producto['punto_reorden']],
            ['punto_reorden' => $puntoReorden]
        );

        return Inventario::producto($id);
    }

    private static function validarSkuUnico(string $sku, ?int $excluir): void
    {
        $sql = 'SELECT id FROM productos WHERE sku = ?';
        $parametros = [$sku];
        if ($excluir !== null) {
            $sql .= ' AND id <> ?';
            $parametros[] = $excluir;
        }
        if (Bd::valor($sql, $parametros) !== null) {
            throw ErrorHttp::validacion('El SKU ya está registrado en otro producto', ['sku' => 'Debe ser único']);
        }
    }

    public static function clientes(string $busqueda = ''): array
    {
        $sql = 'SELECT * FROM clientes WHERE deleted_at IS NULL';
        $parametros = [];
        if ($busqueda !== '') {
            $sql .= ' AND (nombre LIKE ? OR documento LIKE ?)';
            $parametros[] = '%' . $busqueda . '%';
            $parametros[] = '%' . $busqueda . '%';
        }
        $sql .= ' ORDER BY nombre LIMIT 200';

        return array_map(static fn (array $c): array => [
            'id'             => (int) $c['id'],
            'tipo_documento' => (string) $c['tipo_documento'],
            'documento'      => (string) $c['documento'],
            'nombre'         => (string) $c['nombre'],
            'telefono'       => (string) $c['telefono'],
            'correo'         => (string) $c['correo'],
            'direccion'      => (string) $c['direccion'],
        ], Bd::consultar($sql, $parametros));
    }

    public static function crearCliente(array $datos): array
    {
        $id = Bd::insertar(
            'INSERT INTO clientes (tipo_documento, documento, nombre, telefono, correo, direccion)
             VALUES (?,?,?,?,?,?)',
            [
                $datos['tipo_documento'], $datos['documento'], $datos['nombre'],
                $datos['telefono'], $datos['correo'], $datos['direccion'],
            ]
        );

        Bitacora::registrar('CLIENTE_CREAR', 'Ventas', 'Cliente ' . $datos['nombre'] . ' registrado', 'Info', 'clientes', $id);

        $fila = Bd::primero('SELECT * FROM clientes WHERE id = ?', [$id]);
        return [
            'id'             => (int) $fila['id'],
            'tipo_documento' => (string) $fila['tipo_documento'],
            'documento'      => (string) $fila['documento'],
            'nombre'         => (string) $fila['nombre'],
            'telefono'       => (string) $fila['telefono'],
            'correo'         => (string) $fila['correo'],
            'direccion'      => (string) $fila['direccion'],
        ];
    }
}
