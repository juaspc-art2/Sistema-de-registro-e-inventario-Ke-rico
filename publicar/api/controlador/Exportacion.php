<?php

declare(strict_types=1);

namespace Kerico\Controlador;

use Kerico\Nucleo\ErrorHttp;
use Kerico\Nucleo\Exportador;
use Kerico\Nucleo\Respuesta;

final class Exportacion
{
    public static function enviar(
        string $formato,
        string $nombreBase,
        string $titulo,
        string $subtitulo,
        array $encabezados,
        array $filas,
        array $resumen = []
    ): void {
        // Los listados piden una fila más que el tope: si llega, se avisa en vez de entregar un archivo recortado
        if (count($filas) > Exportador::MAXIMO_FILAS) {
            throw ErrorHttp::validacion(
                'El rango tiene más de ' . number_format(Exportador::MAXIMO_FILAS, 0, ',', '.')
                . ' filas. Acote las fechas o los filtros y exporte por partes.',
                ['desde' => 'Rango demasiado amplio']
            );
        }

        $formato = strtolower($formato);
        $descripcion = trim($titulo . ' · ' . $subtitulo, ' ·');

        if ($formato === 'pdf') {
            Respuesta::archivo(
                Exportador::pdf($titulo, $subtitulo, $encabezados, $filas, $resumen),
                $nombreBase . '.pdf',
                'application/pdf',
                $descripcion
            );
            return;
        }

        if ($formato === 'excel' || $formato === 'xlsx') {
            Respuesta::archivo(
                Exportador::xlsx($titulo, $encabezados, $filas),
                $nombreBase . '.xlsx',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                $descripcion
            );
            return;
        }

        if ($formato === 'csv') {
            Respuesta::archivo(
                Exportador::csv($encabezados, $filas),
                $nombreBase . '.csv',
                'text/csv; charset=utf-8',
                $descripcion
            );
            return;
        }

        throw ErrorHttp::validacion('Formato de exportación no soportado', ['formato' => 'Use pdf, excel o csv']);
    }
}
