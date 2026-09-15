<?php

declare(strict_types=1);

namespace Kerico\Controlador;

use Kerico\Modelo\Catalogo;
use Kerico\Modelo\Inventario;
use Kerico\Modelo\Venta;
use Kerico\Nucleo\Autenticacion;
use Kerico\Nucleo\Exportador;
use Kerico\Nucleo\Paginador;
use Kerico\Nucleo\Peticion;
use Kerico\Nucleo\Respuesta;
use Kerico\Nucleo\Validador;

final class VentasControlador
{
    public static function listar(Peticion $peticion): void
    {
        Autenticacion::exigir('ventas.ver');
        Respuesta::exito(Venta::paginar(Paginador::desdePeticion($peticion, [
            'busqueda'    => $peticion->consulta('busqueda', ''),
            'estado'      => $peticion->consulta('estado', ''),
            'desde'       => $peticion->consulta('desde', ''),
            'hasta'       => $peticion->consulta('hasta', ''),
            'usuario_id'  => $peticion->consulta('usuario_id', ''),
            'metodo_pago' => $peticion->consulta('metodo_pago', ''),
        ])));
    }

    public static function ver(Peticion $peticion): void
    {
        Autenticacion::exigir('ventas.ver');
        Respuesta::exito(Venta::porId((int) $peticion->parametro('id')));
    }

    public static function registrar(Peticion $peticion): void
    {
        Autenticacion::exigir('ventas.registrar');

        $validador = new Validador($peticion->cuerpo());
        $validador->lista('items', 'El detalle de la venta', 1);
        $validador->opcion('metodo_pago', 'El método de pago', Venta::METODOS, false, 'Efectivo');
        $validador->validar();

        $resultado = Venta::registrar($peticion->cuerpo());
        Respuesta::exito($resultado, 'Venta ' . $resultado['venta']['folio'] . ' registrada correctamente', 201);
    }

    public static function anular(Peticion $peticion): void
    {
        Autenticacion::exigir('ventas.anular');

        $validador = new Validador($peticion->cuerpo());
        $motivo = $validador->texto('motivo', 'El motivo de la anulacion', true, 255, 5);
        $validador->validar();

        $venta = Venta::anular((int) $peticion->parametro('id'), $motivo);
        Respuesta::exito($venta, 'Venta ' . $venta['folio'] . ' anulada y existencias devueltas');
    }

    public static function catalogo(Peticion $peticion): void
    {
        Autenticacion::exigir('ventas.registrar');
        Respuesta::exito(Inventario::listar([
            'busqueda'   => $peticion->consulta('busqueda', ''),
            'solo_venta' => true,
        ]));
    }

    public static function resumen(Peticion $peticion): void
    {
        Autenticacion::exigir('ventas.ver');
        Respuesta::exito(Venta::resumenDia((string) $peticion->consulta('dia', date('Y-m-d'))));
    }

    public static function clientes(Peticion $peticion): void
    {
        Autenticacion::exigir('ventas.registrar');
        Respuesta::exito(Catalogo::clientes((string) $peticion->consulta('busqueda', '')));
    }

    public static function crearCliente(Peticion $peticion): void
    {
        Autenticacion::exigir('ventas.registrar');

        $validador = new Validador($peticion->cuerpo());
        $datos = [
            'tipo_documento' => $validador->opcion('tipo_documento', 'El tipo de documento', ['CC', 'TI', 'CE', 'PA', 'NIT', 'NA'], false, 'CC'),
            'documento'      => $validador->texto('documento', 'El documento', false, 30),
            'nombre'         => $validador->texto('nombre', 'El nombre', true, 120),
            'telefono'       => $validador->texto('telefono', 'El telefono', false, 30),
            'correo'         => $validador->correo('correo', 'El correo', false),
            'direccion'      => $validador->texto('direccion', 'La direccion', false, 180),
        ];
        $validador->validar();

        Respuesta::exito(Catalogo::crearCliente($datos), 'Cliente registrado', 201);
    }

    public static function exportar(Peticion $peticion): void
    {
        Autenticacion::exigir('ventas.ver');

        $formato = (string) $peticion->consulta('formato', 'pdf');
        $desde = (string) $peticion->consulta('desde', date('Y-m-01'));
        $hasta = (string) $peticion->consulta('hasta', date('Y-m-d'));

        $ventas = Venta::listar(['desde' => $desde, 'hasta' => $hasta, 'estado' => $peticion->consulta('estado', '')]);

        $encabezados = ['Folio', 'Fecha', 'Cajero', 'Cliente', 'Items', 'Descuento', 'Impuesto', 'Total', 'Pago', 'Estado'];
        $filas = array_map(static fn (array $v): array => [
            $v['folio'],
            $v['fecha'],
            $v['cajero'],
            $v['cliente'],
            $v['lineas'],
            number_format($v['descuento_total'], 0, ',', '.'),
            number_format($v['impuesto_total'], 0, ',', '.'),
            number_format($v['total'], 0, ',', '.'),
            $v['metodo_pago'],
            $v['estado'],
        ], $ventas);

        $totalPeriodo = array_sum(array_map(static fn (array $v): float => $v['estado'] === 'Completada' ? $v['total'] : 0.0, $ventas));

        Exportacion::enviar(
            $formato,
            'ventas-' . $desde . '-a-' . $hasta,
            'Reporte de ventas',
            'Del ' . $desde . ' al ' . $hasta,
            $encabezados,
            $filas,
            [
                'Transacciones' => (string) count($ventas),
                'Total vendido' => '$' . number_format($totalPeriodo, 0, ',', '.'),
                'Periodo'       => $desde . ' a ' . $hasta,
            ]
        );
    }
}
