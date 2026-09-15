<?php

declare(strict_types=1);

namespace Kerico\Controlador;

use Kerico\Modelo\Alerta;
use Kerico\Modelo\Catalogo;
use Kerico\Modelo\Inventario;
use Kerico\Nucleo\Autenticacion;
use Kerico\Nucleo\Paginador;
use Kerico\Nucleo\Peticion;
use Kerico\Nucleo\Respuesta;
use Kerico\Nucleo\Validador;

final class InventarioControlador
{
    public static function productos(Peticion $peticion): void
    {
        Autenticacion::exigir('inventario.ver');
        Respuesta::exito(Inventario::paginar(Paginador::desdePeticion($peticion, [
            'busqueda'     => $peticion->consulta('busqueda', ''),
            'categoria_id' => $peticion->consulta('categoria_id', ''),
            'estado'       => $peticion->consulta('estado', ''),
            'activo'       => $peticion->consulta('activo', ''),
        ])));
    }

    public static function producto(Peticion $peticion): void
    {
        Autenticacion::exigir('inventario.ver');
        Respuesta::exito(Inventario::producto((int) $peticion->parametro('id')));
    }

    public static function categorias(Peticion $peticion): void
    {
        Autenticacion::exigir('inventario.ver');
        Respuesta::exito(Catalogo::categorias());
    }

    private static function datosProducto(Peticion $peticion, bool $creando): array
    {
        $validador = new Validador($peticion->cuerpo());
        $datos = [
            'sku'            => strtoupper($validador->texto('sku', 'El SKU', true, 30)),
            'nombre'         => $validador->texto('nombre', 'El nombre', true, 120),
            'descripcion'    => $validador->texto('descripcion', 'La descripcion', false, 255),
            'categoria_id'   => $validador->entero('categoria_id', 'La categoria', true, 1),
            'proveedor_id'   => $validador->entero('proveedor_id', 'El proveedor', false, 1),
            'unidad_medida'  => $validador->texto('unidad_medida', 'La unidad de medida', false, 20) ?: 'unidad',
            'precio_costo'   => $validador->decimal('precio_costo', 'El precio de costo', true, 0),
            'precio_venta'   => $validador->decimal('precio_venta', 'El precio de venta', true, 0),
            'iva_porcentaje' => $validador->decimal('iva_porcentaje', 'El impuesto', false, 0, 100) ?? 0.0,
            'punto_reorden'  => $validador->decimal('punto_reorden', 'El punto de reorden', false, 0) ?? 0.0,
            'stock_maximo'   => $validador->decimal('stock_maximo', 'El stock maximo', false, 0) ?? 0.0,
            'es_insumo'      => $validador->booleano('es_insumo', false),
            'imagen'         => $validador->texto('imagen', 'La imagen', false, 180),
            'activo'         => $validador->booleano('activo', true),
        ];

        if ($creando) {
            $datos['stock_inicial'] = $validador->decimal('stock_inicial', 'El stock inicial', false, 0) ?? 0.0;
        }

        if (!$datos['es_insumo'] && ($datos['precio_venta'] ?? 0) <= 0) {
            $validador->agregarError('precio_venta', 'Un producto de venta necesita un precio mayor que cero');
        }

        $validador->validar();
        return $datos;
    }

    public static function crearProducto(Peticion $peticion): void
    {
        Autenticacion::exigir('productos.gestionar');
        $datos = self::datosProducto($peticion, true);
        Respuesta::exito(Catalogo::crearProducto($datos), 'Producto creado correctamente', 201);
    }

    public static function actualizarProducto(Peticion $peticion): void
    {
        Autenticacion::exigir('productos.gestionar');
        $datos = self::datosProducto($peticion, false);
        Respuesta::exito(
            Catalogo::actualizarProducto((int) $peticion->parametro('id'), $datos),
            'Producto actualizado correctamente'
        );
    }

    public static function eliminarProducto(Peticion $peticion): void
    {
        Autenticacion::exigir('productos.gestionar');
        Catalogo::eliminarProducto((int) $peticion->parametro('id'));
        Respuesta::exito(null, 'Producto eliminado');
    }

    public static function movimientos(Peticion $peticion): void
    {
        Autenticacion::exigir('inventario.ver');
        Respuesta::exito(Inventario::paginarMovimientos(Paginador::desdePeticion($peticion, [
            'busqueda'    => $peticion->consulta('busqueda', ''),
            'tipo'        => $peticion->consulta('tipo', ''),
            'producto_id' => $peticion->consulta('producto_id', ''),
            'desde'       => $peticion->consulta('desde', ''),
            'hasta'       => $peticion->consulta('hasta', ''),
        ])));
    }

    public static function resumenMovimientos(Peticion $peticion): void
    {
        Autenticacion::exigir('inventario.ver');
        Respuesta::exito(Inventario::resumenMovimientos((string) $peticion->consulta('dia', date('Y-m-d'))));
    }

    public static function registrarMovimiento(Peticion $peticion): void
    {
        Autenticacion::exigir('inventario.ajustar');

        $validador = new Validador($peticion->cuerpo());
        $productoId = $validador->entero('producto_id', 'El producto', true, 1);
        $tipo = $validador->opcion('tipo', 'El tipo de movimiento', Inventario::TIPOS, true);
        $cantidad = $validador->decimal('cantidad', 'La cantidad', true, 0.001);
        $motivo = $validador->texto('motivo', 'El motivo', true, 120, 3);
        $observaciones = $validador->texto('observaciones', 'Las observaciones', false, 255);
        $validador->validar();

        $resultado = Inventario::registrarMovimiento(
            (int) $productoId,
            $tipo,
            (float) $cantidad,
            $motivo,
            'Ajuste',
            null,
            $observaciones
        );

        \Kerico\Nucleo\Bitacora::registrar(
            'INVENTARIO_' . strtoupper($tipo),
            'Inventario',
            $tipo . ' de ' . $cantidad . ' unidad(es). Motivo: ' . $motivo,
            $tipo === 'Merma' ? 'Advertencia' : 'Info',
            'productos',
            $productoId
        );

        Respuesta::exito([
            'movimiento' => $resultado,
            'producto'   => Inventario::producto((int) $productoId),
        ], 'Movimiento registrado correctamente', 201);
    }

    public static function alertas(Peticion $peticion): void
    {
        Autenticacion::exigir('alertas.ver');
        Respuesta::exito([
            'resumen'  => Alerta::resumen(),
            'alertas'  => Alerta::listar([
                'estado' => $peticion->consulta('estado', ''),
                'tipo'   => $peticion->consulta('tipo', ''),
            ]),
        ]);
    }

    public static function marcarAlerta(Peticion $peticion): void
    {
        Autenticacion::exigir('alertas.ver');
        Respuesta::exito(Alerta::marcarVista((int) $peticion->parametro('id')), 'Alerta marcada como vista');
    }

    public static function marcarTodasAlertas(Peticion $peticion): void
    {
        Autenticacion::exigir('alertas.ver');
        $total = Alerta::marcarTodasVistas();
        Respuesta::exito(['actualizadas' => $total], $total . ' alerta(s) marcadas como vistas');
    }

    public static function definirReorden(Peticion $peticion): void
    {
        Autenticacion::exigir('alertas.configurar');

        $validador = new Validador($peticion->cuerpo());
        $punto = $validador->decimal('punto_reorden', 'El punto de reorden', true, 0);
        $validador->validar();

        Respuesta::exito(
            Catalogo::definirPuntoReorden((int) $peticion->parametro('id'), (float) $punto),
            'Punto de reorden actualizado'
        );
    }

    public static function recalcularAlertas(Peticion $peticion): void
    {
        Autenticacion::exigir('alertas.configurar');
        $total = Inventario::recalcularAlertas();
        Respuesta::exito(['productos_evaluados' => $total], 'Alertas recalculadas');
    }

    public static function exportarInventario(Peticion $peticion): void
    {
        Autenticacion::exigir('reportes.exportar');

        $formato = (string) $peticion->consulta('formato', 'pdf');
        $productos = Inventario::listar([]);

        $encabezados = ['SKU', 'Producto', 'Categoria', 'Proveedor', 'Unidad', 'Stock', 'Reorden', 'Costo', 'Venta', 'Valor', 'Estado'];
        $filas = array_map(static fn (array $p): array => [
            $p['sku'],
            $p['nombre'],
            $p['categoria'],
            $p['proveedor'],
            $p['unidad_medida'],
            rtrim(rtrim(number_format($p['stock_actual'], 3, '.', ''), '0'), '.'),
            rtrim(rtrim(number_format($p['punto_reorden'], 3, '.', ''), '0'), '.'),
            number_format($p['precio_costo'], 0, ',', '.'),
            number_format($p['precio_venta'], 0, ',', '.'),
            number_format($p['valor_inventario'], 0, ',', '.'),
            $p['estado_stock'],
        ], $productos);

        $resumen = Alerta::resumen();

        Exportacion::enviar(
            $formato,
            'inventario-' . date('Y-m-d'),
            'Estado del inventario',
            'Corte al ' . date('d/m/Y H:i'),
            $encabezados,
            $filas,
            [
                'Referencias' => (string) count($productos),
                'Agotados'    => (string) ($resumen['Agotado'] ?? 0),
                'Criticos'    => (string) ($resumen['Critico'] ?? 0),
                'Stock bajo'  => (string) ($resumen['Bajo'] ?? 0),
                'Valorizado'  => '$' . number_format($resumen['valor_inventario'] ?? 0, 0, ',', '.'),
            ]
        );
    }

    public static function exportarMovimientos(Peticion $peticion): void
    {
        Autenticacion::exigir('reportes.exportar');

        $formato = (string) $peticion->consulta('formato', 'pdf');
        $desde = (string) $peticion->consulta('desde', date('Y-m-01'));
        $hasta = (string) $peticion->consulta('hasta', date('Y-m-d'));

        $movimientos = Inventario::movimientos([
            'desde'  => $desde,
            'hasta'  => $hasta,
            'tipo'   => $peticion->consulta('tipo', ''),
            'limite' => 2000,
        ]);

        $encabezados = ['Fecha', 'SKU', 'Producto', 'Tipo', 'Cantidad', 'Anterior', 'Nuevo', 'Usuario', 'Motivo'];
        $filas = array_map(static fn (array $m): array => [
            $m['fecha'],
            $m['sku'],
            $m['producto'],
            $m['tipo'],
            rtrim(rtrim(number_format($m['cantidad'], 3, '.', ''), '0'), '.'),
            rtrim(rtrim(number_format($m['stock_anterior'], 3, '.', ''), '0'), '.'),
            rtrim(rtrim(number_format($m['stock_nuevo'], 3, '.', ''), '0'), '.'),
            $m['usuario'],
            $m['motivo'],
        ], $movimientos);

        Exportacion::enviar(
            $formato,
            'auditoria-movimientos-' . $desde . '-a-' . $hasta,
            'Auditoría de movimientos de inventario',
            'Del ' . $desde . ' al ' . $hasta,
            $encabezados,
            $filas,
            ['Movimientos' => (string) count($movimientos), 'Periodo' => $desde . ' a ' . $hasta]
        );
    }

    public static function papeleraProductos(Peticion $peticion): void
    {
        Autenticacion::exigir('productos.gestionar');
        Respuesta::exito(Catalogo::papelera());
    }

    public static function restaurarProducto(Peticion $peticion): void
    {
        Autenticacion::exigir('productos.gestionar');
        Respuesta::exito(
            Catalogo::restaurarProducto((int) $peticion->parametro('id')),
            'Producto restaurado desde la papelera'
        );
    }

}
