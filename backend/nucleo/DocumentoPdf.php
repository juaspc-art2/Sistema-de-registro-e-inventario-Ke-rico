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

    public function __construct(string $titulo, string $subtitulo = '')
    {
        $this->titulo = $titulo;
        $this->subtitulo = $subtitulo;
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
        $this->rectangulo(0, self::ALTO - 58, self::ANCHO, 58, self::NARANJA);
        $this->rectangulo(0, self::ALTO - 62, self::ANCHO, 4, self::AMARILLO);
        $this->texto('Ke-Rico!', self::MARGEN, self::ALTO - 30, 18, true, [1, 1, 1]);
        $this->texto($this->titulo, self::MARGEN, self::ALTO - 48, 11, true, [1, 1, 1]);
        if ($this->subtitulo !== '') {
            $this->textoDerecha($this->subtitulo, self::ANCHO - self::MARGEN, self::ALTO - 48, 9, false, [1, 1, 1]);
        }
        $this->textoDerecha(
            'Generado el ' . date('d/m/Y H:i'),
            self::ANCHO - self::MARGEN,
            self::ALTO - 30,
            9,
            false,
            [1, 1, 1]
        );
        $this->y = self::ALTO - 86;
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
        $pesos = $this->calcularPesos($encabezados, $filas);
        $suma = array_sum($pesos);
        $anchos = array_map(static fn (float $p): float => $disponible * ($p / $suma), $pesos);

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
                $texto = (string) $valor;
                $limite = (int) max(6, ($anchos[$i] - 10) / 4.6);
                if (mb_strlen($texto) > $limite) {
                    $texto = mb_substr($texto, 0, $limite - 1) . '.';
                }
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

    private function calcularPesos(array $encabezados, array $filas): array
    {
        $pesos = [];
        $muestra = array_slice($filas, 0, 60);
        foreach (array_values($encabezados) as $i => $titulo) {
            $maximo = mb_strlen((string) $titulo);
            foreach ($muestra as $fila) {
                $valores = array_values($fila);
                if (!isset($valores[$i])) {
                    continue;
                }
                $largo = mb_strlen((string) $valores[$i]);
                if ($largo > $maximo) {
                    $maximo = $largo;
                }
            }
            $pesos[] = (float) min(max($maximo, 6), 40);
        }
        return $pesos;
    }

    private function filaEncabezado(array $encabezados, array $anchos, float $altura): void
    {
        $disponible = self::ANCHO - self::MARGEN * 2;
        $this->rectangulo(self::MARGEN, $this->y - $altura, $disponible, $altura, self::NARANJA);
        $x = self::MARGEN;
        foreach (array_values($encabezados) as $i => $titulo) {
            $this->texto(mb_strtoupper((string) $titulo), $x + 6, $this->y - 12, 8, true, [1, 1, 1]);
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
        $ancho = mb_strlen($texto) * $tamano * ($negrita ? 0.56 : 0.5);
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
