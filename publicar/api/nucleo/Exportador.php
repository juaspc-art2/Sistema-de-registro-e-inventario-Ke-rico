<?php

declare(strict_types=1);

namespace Kerico\Nucleo;

use ZipArchive;

final class Exportador
{
    public static function csv(array $encabezados, array $filas): string
    {
        $manejador = fopen('php://temp', 'r+');
        fputs($manejador, "\xEF\xBB\xBF");
        fputcsv($manejador, array_map(static fn ($v) => Sanitizador::celdaCsv((string) $v), $encabezados), ';', '"', '');
        foreach ($filas as $fila) {
            fputcsv(
                $manejador,
                array_map(static fn ($v) => Sanitizador::celdaCsv((string) $v), array_values($fila)),
                ';',
                '"',
                ''
            );
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
            . '<cellXfs count="2">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>'
            . '</cellXfs>'
            . '</styleSheet>');

        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';

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
                if (is_int($celda) || is_float($celda) || (is_string($celda) && $celda !== '' && is_numeric($celda))) {
                    $xml .= '<c r="' . $ref . '"><v>' . (0 + $celda) . '</v></c>';
                } else {
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

    public static function pdf(string $titulo, string $subtitulo, array $encabezados, array $filas, array $resumen = []): string
    {
        $doc = new DocumentoPdf($titulo, $subtitulo);
        if ($resumen !== []) {
            $doc->resumen($resumen);
        }
        $doc->tabla($encabezados, $filas);
        return $doc->generar();
    }
}
