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
        $formato = strtolower($formato);

        if ($formato === 'pdf') {
            Respuesta::archivo(
                Exportador::pdf($titulo, $subtitulo, $encabezados, $filas, $resumen),
                $nombreBase . '.pdf',
                'application/pdf'
            );
            return;
        }

        if ($formato === 'excel' || $formato === 'xlsx') {
            Respuesta::archivo(
                Exportador::xlsx($titulo, $encabezados, $filas),
                $nombreBase . '.xlsx',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            );
            return;
        }

        if ($formato === 'csv') {
            Respuesta::archivo(
                Exportador::csv($encabezados, $filas),
                $nombreBase . '.csv',
                'text/csv; charset=utf-8'
            );
            return;
        }

        throw ErrorHttp::validacion('Formato de exportación no soportado', ['formato' => 'Use pdf, excel o csv']);
    }
}
