<?php

declare(strict_types=1);

namespace Kerico\Nucleo;

final class DocumentoPdf
{
    private const ANCHO = 841.89;
    private const ALTO = 595.28;
    private const MARGEN = 36.0;

    private const NARANJA = [0.922, 0.369, 0.082];
    private const AMARILLO = [0.929, 0.792, 0.204];
    private const CARBON = [0.118, 0.106, 0.094];
    private const PIEDRA = [0.553, 0.443, 0.400];
    private const ARENA = [0.882, 0.749, 0.702];
    private const CREMA = [1.0, 0.973, 0.961];

    private string $titulo;
    private string $subtitulo;
    private array $paginas = [];
    private string $actual = '';
    private float $y = 0.0;
    private int $numeroPagina = 0;
    private array $emisor;

    public function __construct(string $titulo, string $subtitulo = '')
    {
        $this->titulo = $titulo;
        $this->subtitulo = $subtitulo;
        $this->emisor = Ajustes::emisor();
        $this->nuevaPagina();
    }

    private function nuevaPagina(): void
    {
        if ($this->actual !== '') {
            $this->paginas[] = $this->actual;
        }
        $this->actual = '';
        $this->numeroPagina++;
        $this->y = self::ALTO - self::MARGEN;
        $this->encabezado();
    }

    private function encabezado(): void
    {
        $this->rectangulo(0, self::ALTO - 74, self::ANCHO, 74, self::NARANJA);
        $this->rectangulo(0, self::ALTO - 78, self::ANCHO, 4, self::AMARILLO);
        $this->texto($this->emisor['nombre'], self::MARGEN, self::ALTO - 28, 18, true, [1, 1, 1]);

        $institucional = $this->emisor['razon_social'];
        if ($this->emisor['nit'] !== '') {
            $institucional .= '   NIT ' . $this->emisor['nit'];
        }
        if ($this->emisor['direccion'] !== '') {
            $institucional .= '   ' . $this->emisor['direccion'];
        }
        $this->texto($institucional, self::MARGEN, self::ALTO - 43, 8, false, [1, 1, 1]);
        $this->texto($this->titulo, self::MARGEN, self::ALTO - 62, 11, true, [1, 1, 1]);

        if ($this->subtitulo !== '') {
            $this->textoDerecha($this->subtitulo, self::ANCHO - self::MARGEN, self::ALTO - 62, 9, false, [1, 1, 1]);
        }
        $this->textoDerecha(
            'Generado el ' . date('d/m/Y H:i'),
            self::ANCHO - self::MARGEN,
            self::ALTO - 28,
            9,
            false,
            [1, 1, 1]
        );
        $this->y = self::ALTO - 102;
    }

    public function resumen(array $tarjetas): void
    {
        $disponible = self::ANCHO - self::MARGEN * 2;
        $cantidad = max(count($tarjetas), 1);
        $ancho = ($disponible - ($cantidad - 1) * 10) / $cantidad;
        $alto = 44.0;
        $x = self::MARGEN;

        foreach ($tarjetas as $etiqueta => $valor) {
            $this->rectangulo($x, $this->y - $alto, $ancho, $alto, self::CREMA);
            $this->rectangulo($x, $this->y - $alto, 3, $alto, self::NARANJA);
            $this->texto((string) $etiqueta, $x + 10, $this->y - 16, 8, false, self::PIEDRA);
            $this->texto((string) $valor, $x + 10, $this->y - 33, 13, true, self::CARBON);
            $x += $ancho + 10;
        }

        $this->y -= $alto + 18;
    }

    public function tabla(array $encabezados, array $filas): void
    {
        $columnas = count($encabezados);
        if ($columnas === 0) {
            return;
        }

        $disponible = self::ANCHO - self::MARGEN * 2;
        $anchos = $this->repartir($this->calcularPesos($encabezados, $filas), $disponible);

        $alturaFila = 17.0;
        $this->filaEncabezado($encabezados, $anchos, $alturaFila);

        $indice = 0;
        foreach ($filas as $fila) {
            if ($this->y - $alturaFila < self::MARGEN + 24) {
                $this->pie();
                $this->nuevaPagina();
                $this->filaEncabezado($encabezados, $anchos, $alturaFila);
            }

            if ($indice % 2 === 1) {
                $this->rectangulo(self::MARGEN, $this->y - $alturaFila, $disponible, $alturaFila, self::CREMA);
            }

            $x = self::MARGEN;
            $valores = array_values($fila);
            foreach ($valores as $i => $valor) {
                if ($i >= $columnas) {
                    break;
                }
                $texto = $this->recortar((string) $valor, $anchos[$i] - 12, 8.5);
                if ($this->esNumero((string) $valor)) {
                    $this->textoDerecha($texto, $x + $anchos[$i] - 6, $this->y - 12, 8.5, false, self::CARBON);
                } else {
                    $this->texto($texto, $x + 6, $this->y - 12, 8.5, false, self::CARBON);
                }
                $x += $anchos[$i];
            }

            $this->linea(self::MARGEN, $this->y - $alturaFila, self::ANCHO - self::MARGEN, $this->y - $alturaFila, self::ARENA, 0.4);
            $this->y -= $alturaFila;
            $indice++;
        }

        if ($filas === []) {
            $this->texto('No hay registros para el filtro seleccionado.', self::MARGEN + 6, $this->y - 14, 9, false, self::PIEDRA);
            $this->y -= 24;
        }

        $this->pie();
    }

    private function repartir(array $necesarios, float $disponible): array
    {
        $total = array_sum($necesarios);
        if ($total <= 0.0) {
            return $necesarios;
        }

        if ($total <= $disponible) {
            $sobra = $disponible - $total;
            return array_map(
                static fn (float $n): float => $n + $sobra * ($n / $total),
                $necesarios
            );
        }

        $orden = $necesarios;
        asort($orden);

        $anchos = [];
        $restante = $disponible;
        $quedan = count($orden);

        foreach ($orden as $indice => $necesario) {
            $cuota = $restante / $quedan;
            $asignado = $necesario <= $cuota ? $necesario : $cuota;
            $anchos[$indice] = $asignado;
            $restante -= $asignado;
            $quedan--;
        }

        ksort($anchos);
        return array_values($anchos);
    }

    private function calcularPesos(array $encabezados, array $filas): array
    {
        $pesos = [];
        $muestra = array_slice($filas, 0, 60);
        foreach (array_values($encabezados) as $i => $titulo) {
            $maximo = $this->medir(mb_strtoupper((string) $titulo), 8.0, true) + 14;
            foreach ($muestra as $fila) {
                $valores = array_values($fila);
                if (!isset($valores[$i])) {
                    continue;
                }
                $largo = $this->medir((string) $valores[$i], 8.5, false) + 12;
                if ($largo > $maximo) {
                    $maximo = $largo;
                }
            }
            $pesos[] = (float) max($maximo, 34.0);
        }
        return $pesos;
    }

    private function filaEncabezado(array $encabezados, array $anchos, float $altura): void
    {
        $disponible = self::ANCHO - self::MARGEN * 2;
        $this->rectangulo(self::MARGEN, $this->y - $altura, $disponible, $altura, self::NARANJA);
        $x = self::MARGEN;
        foreach (array_values($encabezados) as $i => $titulo) {
            $encabezado = mb_strtoupper((string) $titulo);
            while ($encabezado !== '' && $this->medir($encabezado, 8.0, true) > $anchos[$i] - 10) {
                $encabezado = mb_substr($encabezado, 0, mb_strlen($encabezado) - 1);
            }
            $this->texto($encabezado, $x + 6, $this->y - 12, 8, true, [1, 1, 1]);
            $x += $anchos[$i];
        }
        $this->y -= $altura;
    }

    private function pie(): void
    {
        $this->linea(self::MARGEN, self::MARGEN + 14, self::ANCHO - self::MARGEN, self::MARGEN + 14, self::ARENA, 0.4);
        $this->texto(
            'Sistema de Registro e Inventario Ke-Rico!',
            self::MARGEN,
            self::MARGEN + 3,
            8,
            false,
            self::PIEDRA
        );
        $this->textoDerecha(
            'Página ' . $this->numeroPagina,
            self::ANCHO - self::MARGEN,
            self::MARGEN + 3,
            8,
            false,
            self::PIEDRA
        );
    }

    private const ANCHOS = [
        ' ' => 278, '!' => 278, '"' => 355, '#' => 556, '$' => 556, '%' => 889, '&' => 667,
        "'" => 191, '(' => 333, ')' => 333, '*' => 389, '+' => 584, ',' => 278, '-' => 333,
        '.' => 278, '/' => 278, ':' => 278, ';' => 278, '<' => 584, '=' => 584, '>' => 584,
        '?' => 556, '@' => 1015, '[' => 278, ']' => 278, '_' => 556, '|' => 260, '…' => 1000,
        '0' => 556, '1' => 556, '2' => 556, '3' => 556, '4' => 556,
        '5' => 556, '6' => 556, '7' => 556, '8' => 556, '9' => 556,
        'A' => 667, 'B' => 667, 'C' => 722, 'D' => 722, 'E' => 667, 'F' => 611, 'G' => 778,
        'H' => 722, 'I' => 278, 'J' => 500, 'K' => 667, 'L' => 556, 'M' => 833, 'N' => 722,
        'O' => 778, 'P' => 667, 'Q' => 778, 'R' => 722, 'S' => 667, 'T' => 611, 'U' => 722,
        'V' => 667, 'W' => 944, 'X' => 667, 'Y' => 667, 'Z' => 611,
        'a' => 556, 'b' => 556, 'c' => 500, 'd' => 556, 'e' => 556, 'f' => 278, 'g' => 556,
        'h' => 556, 'i' => 222, 'j' => 222, 'k' => 500, 'l' => 222, 'm' => 833, 'n' => 556,
        'o' => 556, 'p' => 556, 'q' => 556, 'r' => 333, 's' => 500, 't' => 278, 'u' => 556,
        'v' => 500, 'w' => 722, 'x' => 500, 'y' => 500, 'z' => 500,
        'á' => 556, 'é' => 556, 'í' => 222, 'ó' => 556, 'ú' => 556, 'ñ' => 556, 'ü' => 556,
        'Á' => 667, 'É' => 667, 'Í' => 278, 'Ó' => 778, 'Ú' => 722, 'Ñ' => 722, 'º' => 365,
    ];

    private function medir(string $texto, float $tamano, bool $negrita): float
    {
        $total = 0;
        $largo = mb_strlen($texto);
        for ($i = 0; $i < $largo; $i++) {
            $total += self::ANCHOS[mb_substr($texto, $i, 1)] ?? 556;
        }
        return $total / 1000 * $tamano * ($negrita ? 1.08 : 1.0);
    }

    private function recortar(string $texto, float $disponible, float $tamano): string
    {
        if ($disponible <= 0 || $this->medir($texto, $tamano, false) <= $disponible + 0.5) {
            return $texto;
        }
        $corte = $texto;
        while ($corte !== '' && $this->medir($corte . '…', $tamano, false) > $disponible) {
            $corte = mb_substr($corte, 0, mb_strlen($corte) - 1);
        }
        return $corte === '' ? '' : $corte . '…';
    }

    private function esNumero(string $valor): bool
    {
        $limpio = str_replace(['$', '.', ',', ' ', '%'], '', $valor);
        return $limpio !== '' && is_numeric($limpio);
    }

    private function rectangulo(float $x, float $y, float $ancho, float $alto, array $color): void
    {
        $this->actual .= sprintf(
            "%.3F %.3F %.3F rg %.2F %.2F %.2F %.2F re f\n",
            $color[0], $color[1], $color[2], $x, $y, $ancho, $alto
        );
    }

    private function linea(float $x1, float $y1, float $x2, float $y2, array $color, float $grosor): void
    {
        $this->actual .= sprintf(
            "%.3F %.3F %.3F RG %.2F w %.2F %.2F m %.2F %.2F l S\n",
            $color[0], $color[1], $color[2], $grosor, $x1, $y1, $x2, $y2
        );
    }

    private function texto(string $texto, float $x, float $y, float $tamano, bool $negrita, array $color): void
    {
        $this->actual .= sprintf(
            "BT %.3F %.3F %.3F rg /%s %.2F Tf %.2F %.2F Td (%s) Tj ET\n",
            $color[0], $color[1], $color[2],
            $negrita ? 'F2' : 'F1',
            $tamano, $x, $y,
            $this->escapar($texto)
        );
    }

    private function textoDerecha(string $texto, float $x, float $y, float $tamano, bool $negrita, array $color): void
    {
        $ancho = $this->medir($texto, $tamano, $negrita);
        $this->texto($texto, $x - $ancho, $y, $tamano, $negrita, $color);
    }

    private function escapar(string $texto): string
    {
        $convertido = @iconv('UTF-8', 'Windows-1252//IGNORE', $texto);
        if ($convertido === false || $convertido === '') {
            $convertido = @iconv('UTF-8', 'Windows-1252//TRANSLIT', $texto);
        }
        if ($convertido === false) {
            $convertido = preg_replace('/[^\x20-\x7E]/', '', $texto) ?? '';
        }
        return str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', '', ' '], $convertido);
    }

    public function generar(): string
    {
        $this->paginas[] = $this->actual;
        $total = count($this->paginas);

        $objetos = [];
        $objetos[1] = "<< /Type /Catalog /Pages 2 0 R >>";

        $referencias = [];
        for ($i = 0; $i < $total; $i++) {
            $referencias[] = (3 + $i * 2) . ' 0 R';
        }
        $objetos[2] = '<< /Type /Pages /Kids [' . implode(' ', $referencias) . '] /Count ' . $total . ' >>';

        $idFuenteNormal = 3 + $total * 2;
        $idFuenteNegrita = $idFuenteNormal + 1;

        for ($i = 0; $i < $total; $i++) {
            $idPagina = 3 + $i * 2;
            $idContenido = $idPagina + 1;
            $objetos[$idPagina] = sprintf(
                '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] '
                . '/Resources << /Font << /F1 %d 0 R /F2 %d 0 R >> >> /Contents %d 0 R >>',
                self::ANCHO,
                self::ALTO,
                $idFuenteNormal,
                $idFuenteNegrita,
                $idContenido
            );
            $flujo = $this->paginas[$i];
            $objetos[$idContenido] = '<< /Length ' . strlen($flujo) . " >>\nstream\n" . $flujo . "endstream";
        }

        $objetos[$idFuenteNormal] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objetos[$idFuenteNegrita] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';

        ksort($objetos);

        $pdf = "%PDF-1.4\n";
        $posiciones = [];
        foreach ($objetos as $id => $cuerpo) {
            $posiciones[$id] = strlen($pdf);
            $pdf .= $id . " 0 obj\n" . $cuerpo . "\nendobj\n";
        }

        $inicioXref = strlen($pdf);
        $maximo = max(array_keys($objetos));
        $pdf .= "xref\n0 " . ($maximo + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i <= $maximo; $i++) {
            $pdf .= isset($posiciones[$i])
                ? sprintf("%010d 00000 n \n", $posiciones[$i])
                : "0000000000 65535 f \n";
        }
        $pdf .= "trailer\n<< /Size " . ($maximo + 1) . " /Root 1 0 R >>\n";
        $pdf .= "startxref\n" . $inicioXref . "\n%%EOF";

        return $pdf;
    }
}
