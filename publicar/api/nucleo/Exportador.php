<?php

declare(strict_types=1);

namespace Kerico\Nucleo;

use ZipArchive;

final class Exportador
{
    // Tope de filas por archivo. Medido: 20.000 filas en PDF usan menos de 256 MB de memoria
    public const MAXIMO_FILAS = 20000;

    /*
     * Celdas numéricas. Cada una lleva el número, con el que Excel y CSV pueden sumar y ordenar, y el texto
     * con que se lee en el PDF. Un número escrito solo como texto ("9.500") no se puede volver a interpretar
     * sin riesgo: lo mismo puede ser nueve mil quinientos pesos que nueve kilos y medio.
     */
    public static function dinero(float|int|string|null $valor): array
    {
        return ['numero' => round((float) $valor), 'texto' => number_format((float) $valor, 0, ',', '.'), 'decimales' => 0];
    }

    // Existencias y unidades: hasta tres decimales, solo los que hagan falta
    public static function cantidad(float|int|string|null $valor): array
    {
        $texto = rtrim(rtrim(number_format((float) $valor, 3, '.', ''), '0'), '.');
        return ['numero' => (float) $texto, 'texto' => $texto, 'decimales' => null];
    }

    public static function decimal(float|int|string|null $valor, int $decimales = 2): array
    {
        return [
            'numero'    => round((float) $valor, $decimales),
            'texto'     => number_format((float) $valor, $decimales, ',', '.'),
            'decimales' => $decimales,
        ];
    }

    private static function textoCelda(mixed $celda): string
    {
        return is_array($celda) ? $celda['texto'] : (string) $celda;
    }

    public static function csv(array $encabezados, array $filas): string
    {
        // Un número va sin separador de miles y con coma decimal, como lo espera una hoja de cálculo en español
        $celda = static fn ($v): string => is_array($v)
            ? str_replace('.', ',', $v['decimales'] === null ? $v['texto'] : number_format($v['numero'], $v['decimales'], '.', ''))
            : Sanitizador::celdaCsv((string) $v);

        $manejador = fopen('php://temp', 'r+');
        fputs($manejador, "\xEF\xBB\xBF");
        fputcsv($manejador, array_map($celda, $encabezados), ';', '"', '');
        foreach ($filas as $fila) {
            fputcsv($manejador, array_map($celda, array_values($fila)), ';', '"', '');
        }
        rewind($manejador);
        $contenido = stream_get_contents($manejador);
        fclose($manejador);
        return $contenido === false ? '' : $contenido;
    }

    public static function xlsx(string $titulo, array $encabezados, array $filas): string
    {
        $archivo = tempnam(sys_get_temp_dir(), 'kerico');
        $zip = new ZipArchive();
        $zip->open($archivo, ZipArchive::OVERWRITE);

        $zip->addFromString('[Content_Types].xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>');

        $zip->addFromString('_rels/.rels',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>');

        $zip->addFromString('xl/_rels/workbook.xml.rels',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>');

        $nombreHoja = mb_substr(preg_replace('/[\\\\\/\*\[\]:\?]/', '', $titulo) ?: 'Datos', 0, 31);
        $zip->addFromString('xl/workbook.xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
            . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<bookViews><workbookView/></bookViews>'
            . '<sheets><sheet name="' . self::escapar($nombreHoja) . '" sheetId="1" r:id="rId1"/></sheets>'
            . '</workbook>');

        $zip->addFromString('xl/styles.xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="2">'
            . '<font><sz val="11"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
            . '</fonts>'
            . '<fills count="3">'
            . '<fill><patternFill patternType="none"/></fill>'
            . '<fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFEB5E15"/><bgColor indexed="64"/></patternFill></fill>'
            . '</fills>'
            . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            // Estilos de celda: 0 normal, 1 encabezado, 2 número con miles (#,##0), 3 número con dos decimales (#,##0.00)
            . '<cellXfs count="4">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>'
            . '<xf numFmtId="3" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            . '<xf numFmtId="4" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            . '</cellXfs>'
            . '</styleSheet>');

        // Cada columna toma el ancho de su texto más largo, para que nada quede cortado al abrir el archivo
        $anchos = array_map(static fn ($t): int => mb_strlen((string) $t), array_values($encabezados));
        foreach ($filas as $fila) {
            foreach (array_values($fila) as $i => $celda) {
                $anchos[$i] = max($anchos[$i] ?? 0, mb_strlen(self::textoCelda($celda)));
            }
        }
        $columnas = '';
        foreach ($anchos as $i => $caracteres) {
            $columnas .= sprintf('<col min="%d" max="%d" width="%d" customWidth="1"/>', $i + 1, $i + 1, min(max($caracteres + 3, 9), 60));
        }

        // La fila de encabezados queda fija al desplazarse
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            . '<cols>' . $columnas . '</cols><sheetData>';

        $xml .= '<row r="1">';
        foreach (array_values($encabezados) as $i => $titulo2) {
            $xml .= '<c r="' . self::columna($i) . '1" t="inlineStr" s="1"><is><t>' . self::escapar((string) $titulo2) . '</t></is></c>';
        }
        $xml .= '</row>';

        $numero = 2;
        foreach ($filas as $fila) {
            $xml .= '<row r="' . $numero . '">';
            foreach (array_values($fila) as $i => $celda) {
                $ref = self::columna($i) . $numero;
                if (is_array($celda)) {
                    $estilo = match ($celda['decimales']) { 0 => ' s="2"', null => '', default => ' s="3"' };
                    $xml .= '<c r="' . $ref . '"' . $estilo . '><v>' . $celda['numero'] . '</v></c>';
                } elseif (is_int($celda) || is_float($celda)) {
                    $xml .= '<c r="' . $ref . '"><v>' . $celda . '</v></c>';
                } elseif ((string) $celda !== '') {
                    $xml .= '<c r="' . $ref . '" t="inlineStr"><is><t>' . self::escapar((string) $celda) . '</t></is></c>';
                }
            }
            $xml .= '</row>';
            $numero++;
        }

        $xml .= '</sheetData></worksheet>';
        $zip->addFromString('xl/worksheets/sheet1.xml', $xml);
        $zip->close();

        $contenido = file_get_contents($archivo);
        unlink($archivo);
        return $contenido === false ? '' : $contenido;
    }

    private static function columna(int $indice): string
    {
        $letras = '';
        $indice++;
        while ($indice > 0) {
            $resto = ($indice - 1) % 26;
            $letras = chr(65 + $resto) . $letras;
            $indice = intdiv($indice - 1, 26);
        }
        return $letras;
    }

    private static function escapar(string $texto): string
    {
        return htmlspecialchars($texto, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    // La base guarda 2026-10-10 17:15:00; en un PDF se lee 10/10/2026 17:15, como en la página
    public static function fechaLegible(string $texto): string
    {
        return preg_replace_callback(
            '/\b(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::\d{2})?)?\b/',
            static fn (array $m): string => $m[3] . '/' . $m[2] . '/' . $m[1] . (($m[4] ?? '') === '' ? '' : ' ' . $m[4] . ':' . $m[5]),
            $texto
        ) ?? $texto;
    }

    public static function pdf(string $titulo, string $subtitulo, array $encabezados, array $filas, array $resumen = []): string
    {
        $legible = static fn (mixed $celda): string => self::fechaLegible(self::textoCelda($celda));

        $doc = new DocumentoPdf($titulo, self::fechaLegible($subtitulo));
        if ($resumen !== []) {
            $doc->resumen(array_map($legible, $resumen));
        }
        $doc->tabla($encabezados, array_map(static fn (array $fila): array => array_map($legible, array_values($fila)), $filas));
        return $doc->generar();
    }
}
