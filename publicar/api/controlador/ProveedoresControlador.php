<?php

declare(strict_types=1);

namespace Kerico\Controlador;

use Kerico\Modelo\Proveedor;
use Kerico\Nucleo\Autenticacion;
use Kerico\Nucleo\Paginador;
use Kerico\Nucleo\Peticion;
use Kerico\Nucleo\Respuesta;
use Kerico\Nucleo\Validador;

final class ProveedoresControlador
{
    public static function listar(Peticion $peticion): void
    {
        Autenticacion::exigir('proveedores.ver');
        Respuesta::exito(Proveedor::paginar(Paginador::desdePeticion($peticion, [
            'busqueda' => $peticion->consulta('busqueda', ''),
            'estado'   => $peticion->consulta('estado', ''),
        ])));
    }

    public static function ver(Peticion $peticion): void
    {
        Autenticacion::exigir('proveedores.ver');
        Respuesta::exito(Proveedor::porId((int) $peticion->parametro('id')));
    }

    private static function datos(Peticion $peticion): array
    {
        $validador = new Validador($peticion->cuerpo());
        $datos = [
            'nombre_empresa'  => $validador->texto('nombre_empresa', 'El nombre de la empresa', true, 120, 2),
            'nit'             => $validador->texto('nit', 'El NIT', true, 30, 5),
            'telefono'        => $validador->texto('telefono', 'El telefono', false, 30),
            'correo'          => $validador->correo('correo', 'El correo', false),
            'direccion'       => $validador->texto('direccion', 'La direccion', false, 180),
            'contacto_nombre' => $validador->texto('contacto_nombre', 'El contacto', false, 120),
            'tipo_productos'  => $validador->texto('tipo_productos', 'El tipo de productos', true, 180, 3),
            'estado'          => $validador->opcion('estado', 'El estado', Proveedor::ESTADOS, false, 'Activo'),
            'calificacion'    => $validador->decimal('calificacion', 'La calificacion', false, 0, 5) ?? 0.0,
            'notas'           => $validador->texto('notas', 'Las notas', false, 255),
        ];
        $validador->validar();
        return $datos;
    }

    public static function crear(Peticion $peticion): void
    {
        Autenticacion::exigir('proveedores.gestionar');
        Respuesta::exito(Proveedor::crear(self::datos($peticion)), 'Proveedor registrado correctamente', 201);
    }

    public static function actualizar(Peticion $peticion): void
    {
        Autenticacion::exigir('proveedores.gestionar');
        Respuesta::exito(
            Proveedor::actualizar((int) $peticion->parametro('id'), self::datos($peticion)),
            'Proveedor actualizado correctamente'
        );
    }

    public static function eliminar(Peticion $peticion): void
    {
        Autenticacion::exigir('proveedores.gestionar');
        Proveedor::eliminar((int) $peticion->parametro('id'));
        Respuesta::exito(null, 'Proveedor eliminado');
    }

    public static function compras(Peticion $peticion): void
    {
        Autenticacion::exigir('compras.registrar');
        Respuesta::exito(Proveedor::compras([
            'proveedor_id' => $peticion->consulta('proveedor_id', ''),
            'estado'       => $peticion->consulta('estado', ''),
            'desde'        => $peticion->consulta('desde', ''),
            'hasta'        => $peticion->consulta('hasta', ''),
        ]));
    }

    public static function verCompra(Peticion $peticion): void
    {
        Autenticacion::exigir('compras.registrar');
        Respuesta::exito(Proveedor::compra((int) $peticion->parametro('id')));
    }

    public static function registrarCompra(Peticion $peticion): void
    {
        Autenticacion::exigir('compras.registrar');

        $validador = new Validador($peticion->cuerpo());
        $validador->entero('proveedor_id', 'El proveedor', true, 1);
        $validador->lista('items', 'El detalle de la compra', 1);
        $validador->validar();

        $compra = Proveedor::registrarCompra($peticion->cuerpo());
        Respuesta::exito($compra, 'Compra ' . $compra['numero'] . ' registrada', 201);
    }

    public static function recibirCompra(Peticion $peticion): void
    {
        Autenticacion::exigir('compras.registrar');
        $compra = Proveedor::recibirCompra((int) $peticion->parametro('id'));
        Respuesta::exito($compra, 'Compra ' . $compra['numero'] . ' recibida e ingresada al inventario');
    }

    public static function exportar(Peticion $peticion): void
    {
        Autenticacion::exigir('reportes.exportar');

        $formato = (string) $peticion->consulta('formato', 'pdf');
        $proveedores = Proveedor::listar(['estado' => $peticion->consulta('estado', '')]);

        $encabezados = ['Empresa', 'NIT', 'Contacto', 'Teléfono', 'Correo', 'Suministra', 'Estado', 'Calif.', 'Compras', 'Monto'];
        $filas = array_map(static fn (array $p): array => [
            $p['nombre_empresa'],
            $p['nit'],
            $p['contacto_nombre'],
            $p['telefono'],
            $p['correo'],
            $p['tipo_productos'],
            $p['estado'],
            number_format($p['calificacion'], 2, ',', '.'),
            (string) $p['compras'],
            number_format($p['monto_comprado'], 0, ',', '.'),
        ], $proveedores);

        $activos = count(array_filter($proveedores, static fn (array $p): bool => $p['estado'] === 'Activo'));

        Exportacion::enviar(
            $formato,
            'proveedores-' . date('Y-m-d'),
            'Directorio de proveedores',
            'Corte al ' . date('d/m/Y H:i'),
            $encabezados,
            $filas,
            [
                'Proveedores' => (string) count($proveedores),
                'Activos'     => (string) $activos,
                'Comprado'    => '$' . number_format(
                    array_sum(array_map(static fn (array $p): float => $p['monto_comprado'], $proveedores)),
                    0,
                    ',',
                    '.'
                ),
            ]
        );
    }

    public static function papelera(Peticion $peticion): void
    {
        Autenticacion::exigir('proveedores.gestionar');
        Respuesta::exito(Proveedor::papelera());
    }

    public static function restaurar(Peticion $peticion): void
    {
        Autenticacion::exigir('proveedores.gestionar');
        Respuesta::exito(
            Proveedor::restaurar((int) $peticion->parametro('id')),
            'Proveedor restaurado desde la papelera'
        );
    }

}
