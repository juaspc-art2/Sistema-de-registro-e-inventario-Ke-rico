<?php

declare(strict_types=1);

namespace Kerico\Controlador;

use Kerico\Modelo\Promocion;
use Kerico\Nucleo\Autenticacion;
use Kerico\Nucleo\Bitacora;
use Kerico\Nucleo\ErrorHttp;
use Kerico\Nucleo\Paginador;
use Kerico\Nucleo\Peticion;
use Kerico\Nucleo\Respuesta;
use Kerico\Nucleo\Validador;

final class DescuentosControlador
{
    public static function listar(Peticion $peticion): void
    {
        Autenticacion::exigir('descuentos.aplicar');
        Respuesta::exito(Promocion::paginar(Paginador::desdePeticion($peticion, [
            'activa'   => $peticion->consulta('activa', ''),
            'busqueda' => $peticion->consulta('busqueda', ''),
        ])));
    }

    public static function ver(Peticion $peticion): void
    {
        Autenticacion::exigir('descuentos.aplicar');
        Respuesta::exito(Promocion::porId((int) $peticion->parametro('id')));
    }

    private static function datos(Peticion $peticion): array
    {
        $validador = new Validador($peticion->cuerpo());
        $datos = [
            'codigo'                => strtoupper($validador->texto('codigo', 'El codigo', true, 30, 3)),
            'descripcion'           => $validador->texto('descripcion', 'La descripcion', true, 180),
            'tipo'                  => $validador->opcion('tipo', 'El tipo', ['porcentaje', 'fijo'], true),
            'valor'                 => $validador->decimal('valor', 'El valor', true, 0.01),
            'alcance'               => $validador->opcion('alcance', 'El alcance', ['venta', 'producto', 'categoria'], false, 'venta'),
            'producto_id'           => $validador->entero('producto_id', 'El producto', false, 1),
            'categoria_id'          => $validador->entero('categoria_id', 'La categoria', false, 1),
            'monto_minimo'          => $validador->decimal('monto_minimo', 'El monto minimo', false, 0) ?? 0.0,
            'fecha_inicio'          => $validador->fecha('fecha_inicio', 'La fecha de inicio', false),
            'fecha_fin'             => $validador->fecha('fecha_fin', 'La fecha de fin', false),
            'usos_maximos'          => $validador->entero('usos_maximos', 'Los usos maximos', false, 0) ?? 0,
            'requiere_autorizacion' => $validador->booleano('requiere_autorizacion', false),
            'activa'                => $validador->booleano('activa', true),
        ];

        if ($datos['tipo'] === 'porcentaje' && ($datos['valor'] ?? 0) > 100) {
            $validador->agregarError('valor', 'Un descuento porcentual no puede superar el 100 por ciento');
        }
        if ($datos['alcance'] === 'producto' && $datos['producto_id'] === null) {
            $validador->agregarError('producto_id', 'Debe seleccionar el producto al que aplica la promoción');
        }
        if ($datos['alcance'] === 'categoria' && $datos['categoria_id'] === null) {
            $validador->agregarError('categoria_id', 'Debe seleccionar la categoría a la que aplica la promoción');
        }
        if ($datos['fecha_inicio'] !== null && $datos['fecha_fin'] !== null && $datos['fecha_fin'] < $datos['fecha_inicio']) {
            $validador->agregarError('fecha_fin', 'La fecha de fin no puede ser anterior a la de inicio');
        }

        $validador->validar();

        if ($datos['alcance'] !== 'producto') {
            $datos['producto_id'] = null;
        }
        if ($datos['alcance'] !== 'categoria') {
            $datos['categoria_id'] = null;
        }

        return $datos;
    }

    public static function crear(Peticion $peticion): void
    {
        Autenticacion::exigir('descuentos.gestionar');
        $datos = self::datos($peticion);

        if (Promocion::porCodigo($datos['codigo']) !== null) {
            throw ErrorHttp::validacion('Ya existe una promoción con ese código', ['codigo' => 'Debe ser único']);
        }

        $id = Promocion::crear($datos, Autenticacion::idUsuario());

        Bitacora::registrar(
            'PROMOCION_CREAR',
            'Descuentos',
            'Promoción ' . $datos['codigo'] . ' creada',
            'Info',
            'promociones',
            $id
        );

        Respuesta::exito(Promocion::porId($id), 'Promoción creada correctamente', 201);
    }

    public static function actualizar(Peticion $peticion): void
    {
        Autenticacion::exigir('descuentos.gestionar');
        $id = (int) $peticion->parametro('id');
        $antes = Promocion::porId($id);
        $datos = self::datos($peticion);

        $existente = Promocion::porCodigo($datos['codigo']);
        if ($existente !== null && $existente['id'] !== $id) {
            throw ErrorHttp::validacion('Ya existe una promoción con ese código', ['codigo' => 'Debe ser único']);
        }

        Promocion::actualizar($id, $datos);

        Bitacora::registrar(
            'PROMOCION_ACTUALIZAR',
            'Descuentos',
            'Promoción ' . $datos['codigo'] . ' actualizada',
            'Info',
            'promociones',
            $id,
            ['valor' => $antes['valor'], 'activa' => $antes['activa']],
            ['valor' => $datos['valor'], 'activa' => $datos['activa']]
        );

        Respuesta::exito(Promocion::porId($id), 'Promoción actualizada correctamente');
    }

    public static function alternar(Peticion $peticion): void
    {
        Autenticacion::exigir('descuentos.gestionar');
        $promocion = Promocion::alternar((int) $peticion->parametro('id'));

        Bitacora::registrar(
            'PROMOCION_ESTADO',
            'Descuentos',
            'Promoción ' . $promocion['codigo'] . ' ' . ($promocion['activa'] ? 'activada' : 'desactivada'),
            'Advertencia',
            'promociones',
            $promocion['id']
        );

        Respuesta::exito($promocion, 'Promoción ' . ($promocion['activa'] ? 'activada' : 'desactivada'));
    }

    public static function simular(Peticion $peticion): void
    {
        Autenticacion::exigir('descuentos.aplicar');

        $validador = new Validador($peticion->cuerpo());
        $codigo = $validador->texto('codigo', 'El codigo', true, 30);
        $items = $validador->lista('items', 'El detalle de la venta', 1);
        $validador->validar();

        $promocion = Promocion::porCodigo($codigo);
        if ($promocion === null) {
            throw ErrorHttp::validacion('El código de promoción no existe', ['codigo' => 'Código inválido']);
        }

        $lineas = Promocion::lineasDesdeItems($items);
        $bruto = round(array_sum(array_column($lineas, 'total')), 2);
        $evaluacion = Promocion::evaluar($promocion, $lineas, $bruto);

        Respuesta::exito([
            'promocion'  => $promocion,
            'valido'     => $evaluacion['valido'],
            'motivo'     => $evaluacion['motivo'],
            'subtotal'   => $bruto,
            'descuento'  => $evaluacion['descuento'],
            'total'      => round($bruto - $evaluacion['descuento'], 2),
            'autorizado' => !$promocion['requiere_autorizacion'] || Autenticacion::tienePermiso('descuentos.gestionar'),
        ]);
    }

    public static function historial(Peticion $peticion): void
    {
        Autenticacion::exigir('descuentos.gestionar');
        Respuesta::exito(Promocion::historial([
            'desde'  => $peticion->consulta('desde', ''),
            'hasta'  => $peticion->consulta('hasta', ''),
            'codigo' => $peticion->consulta('codigo', ''),
        ]));
    }

    public static function exportar(Peticion $peticion): void
    {
        Autenticacion::exigir('reportes.exportar');

        $formato = (string) $peticion->consulta('formato', 'pdf');
        $desde = (string) $peticion->consulta('desde', date('Y-m-01'));
        $hasta = (string) $peticion->consulta('hasta', date('Y-m-d'));
        $historial = Promocion::historial(['desde' => $desde, 'hasta' => $hasta]);

        $encabezados = ['Fecha', 'Folio', 'Codigo', 'Tipo', 'Valor original', 'Descuento', 'Valor final', 'Motivo', 'Autorizado por'];
        $filas = array_map(static fn (array $d): array => [
            $d['fecha'],
            $d['folio'],
            $d['codigo'],
            $d['tipo'],
            number_format($d['valor_original'], 0, ',', '.'),
            number_format($d['valor_descuento'], 0, ',', '.'),
            number_format($d['valor_final'], 0, ',', '.'),
            $d['motivo'],
            $d['autorizado_por'],
        ], $historial);

        $totalDescuento = array_sum(array_map(static fn (array $d): float => $d['valor_descuento'], $historial));

        Exportacion::enviar(
            $formato,
            'descuentos-' . $desde . '-a-' . $hasta,
            'Auditoría de descuentos aplicados',
            'Del ' . $desde . ' al ' . $hasta,
            $encabezados,
            $filas,
            [
                'Aplicaciones'      => (string) count($historial),
                'Total descontado'  => '$' . number_format($totalDescuento, 0, ',', '.'),
                'Periodo'           => $desde . ' a ' . $hasta,
            ]
        );
    }

    public static function eliminar(Peticion $peticion): void
    {
        Autenticacion::exigir('descuentos.gestionar');
        Promocion::eliminar((int) $peticion->parametro('id'));
        Respuesta::exito(null, 'Promoción enviada a la papelera');
    }

    public static function restaurar(Peticion $peticion): void
    {
        Autenticacion::exigir('descuentos.gestionar');
        Respuesta::exito(
            Promocion::restaurar((int) $peticion->parametro('id')),
            'Promoción restaurada desde la papelera'
        );
    }

}
