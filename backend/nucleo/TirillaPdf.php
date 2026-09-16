<?php

declare(strict_types=1);

namespace Kerico\Nucleo;

final class TirillaPdf
{
    private const ANCHO = 226.77;
    private const MARGEN = 14.0;

    private const NARANJA = [0.922, 0.369, 0.082];
    private const CARBON = [0.118, 0.106, 0.094];
    private const PIEDRA = [0.553, 0.443, 0.400];
    private const ARENA = [0.882, 0.749, 0.702];
    private const ERROR = [0.729, 0.102, 0.102];

    private array $comprobante;
    private array $emisor;
    private array $cliente;
    private array $detalle;
    private array $totales;
    private string $flujo = '';
    private float $alto = 0.0;
    private float $y = 0.0;

    public function __construct(array $comprobante)
    {
        $this->comprobante = $comprobante;
        $this->emisor = $comprobante['emisor'] ?? [];
        $this->cliente = $comprobante['cliente'] ?? [];
        $this->detalle = $comprobante['detalle'] ?? [];
        $this->totales = $this->filasTotales();
        $this->alto = $this->calcularAlto();
    }

    private function filasTotales(): array
    {
        $filas = [['Subtotal', $this->dinero($this->comprobante['subtotal'] ?? 0), false]];
        if ((float) ($this->comprobante['descuento'] ?? 0) > 0) {
            $filas[] = ['Descuento', '- ' . $this->dinero($this->comprobante['descuento']), false];
        }
        $filas[] = ['Base gravable', $this->dinero($this->comprobante['base_gravable'] ?? 0), false];
        $filas[] = ['Impuesto', $this->dinero($this->comprobante['impuesto'] ?? 0), false];
        $filas[] = ['TOTAL', $this->dinero($this->comprobante['total'] ?? 0), true];
        return $filas;
    }

    private function envolver(string $texto, int $ancho, int $maximo): array
    {
        $palabras = preg_split('/\s+/', trim($texto)) ?: [];
        $lineas = [];
        $actual = '';
        foreach ($palabras as $palabra) {
            $prueba = $actual === '' ? $palabra : $actual . ' ' . $palabra;
            if (mb_strlen($prueba) > $ancho && $actual !== '') {
                $lineas[] = $actual;
                $actual = $palabra;
            } else {
                $actual = $prueba;
            }
        }
        if ($actual !== '') {
            $lineas[] = $actual;
        }
        return array_slice($lineas === [] ? [$texto] : $lineas, 0, $maximo);
    }

    private function lineasNombre(string $nombre): array
    {
        $palabras = preg_split('/\s+/', trim($nombre)) ?: [];
        $lineas = [];
        $actual = '';
        foreach ($palabras as $palabra) {
            $prueba = $actual === '' ? $palabra : $actual . ' ' . $palabra;
            if (mb_strlen($prueba) > 26 && $actual !== '') {
                $lineas[] = $actual;
                $actual = $palabra;
            } else {
                $actual = $prueba;
            }
        }
        if ($actual !== '') {
            $lineas[] = $actual;
        }
        return array_slice($lineas === [] ? [$nombre] : $lineas, 0, 3);
    }

    private function calcularAlto(): float
    {
        $alto = self::MARGEN;
        $alto += 26;
        $alto += 4 * 11;
        $alto += 12;

        $alto += 6 * 12;
        if (trim((string) ($this->cliente['documento'] ?? '')) !== '') {
            $alto += 12;
        }
        $alto += 12;

        foreach ($this->detalle as $item) {
            $alto += count($this->lineasNombre((string) ($item['nombre'] ?? ''))) * 11;
            $alto += 11;
            $alto += 3;
        }
        $alto += 12;

        $alto += count($this->totales) * 13;
        $alto += 12;

        $alto += 24;
        $resolucionTexto = (string) ($this->comprobante['resolucion_dian'] ?? '');
        if (trim($resolucionTexto) !== '') {
            $alto += count($this->envolver($resolucionTexto, 44, 4)) * 9;
        }
        if (($this->comprobante['estado'] ?? '') === 'Anulado') {
            $alto += 20;
        }
        $alto += self::MARGEN;

        return max($alto, 260.0);
    }

    private function dinero($valor): string
    {
        return '$' . number_format((float) $valor, 0, ',', '.');
    }

    private function cantidad($valor): string
    {
        $texto = number_format((float) $valor, 3, '.', '');
        return rtrim(rtrim($texto, '0'), '.');
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

    private function ancho(string $texto, float $tamano, bool $negrita): float
    {
        return mb_strlen($texto) * $tamano * ($negrita ? 0.56 : 0.5);
    }

    private function texto(string $texto, float $x, float $tamano, bool $negrita, array $color): void
    {
        $this->flujo .= sprintf(
            "BT %.3F %.3F %.3F rg /%s %.2F Tf %.2F %.2F Td (%s) Tj ET\n",
            $color[0], $color[1], $color[2],
            $negrita ? 'F2' : 'F1',
            $tamano, $x, $this->y,
            $this->escapar($texto)
        );
    }

    private function centrado(string $texto, float $tamano, bool $negrita, array $color): void
    {
        $x = (self::ANCHO - $this->ancho($texto, $tamano, $negrita)) / 2;
        $this->texto($texto, max($x, self::MARGEN), $tamano, $negrita, $color);
    }

    private function derecha(string $texto, float $tamano, bool $negrita, array $color): void
    {
        $x = self::ANCHO - self::MARGEN - $this->ancho($texto, $tamano, $negrita);
        $this->texto($texto, $x, $tamano, $negrita, $color);
    }

    private function separador(): void
    {
        $this->y -= 6;
        $this->flujo .= sprintf(
            "%.3F %.3F %.3F RG 0.5 w %.2F %.2F m %.2F %.2F l S\n",
            self::ARENA[0], self::ARENA[1], self::ARENA[2],
            self::MARGEN, $this->y, self::ANCHO - self::MARGEN, $this->y
        );
        $this->y -= 6;
    }

    public function generar(): string
    {
        $this->y = $this->alto - self::MARGEN - 16;

        $this->centrado((string) ($this->emisor['nombre'] ?? 'Ke-Rico!'), 20, true, self::NARANJA);
        $this->y -= 13;

        foreach ([
            (string) ($this->emisor['razon_social'] ?? ''),
            'NIT ' . (string) ($this->emisor['nit'] ?? ''),
            (string) ($this->emisor['direccion'] ?? ''),
            (string) ($this->emisor['telefono'] ?? ''),
        ] as $linea) {
            if (trim($linea) === '' || $linea === 'NIT ') continue;
            $this->centrado($linea, 8, false, self::PIEDRA);
            $this->y -= 10;
        }

        $this->separador();

        $tipo = (string) ($this->comprobante['tipo'] ?? 'Comprobante');
        $this->texto($tipo, self::MARGEN, 9, true, self::CARBON);
        $this->derecha('N.º ' . (string) ($this->comprobante['numero'] ?? ''), 9, false, self::CARBON);
        $this->y -= 12;

        foreach ([
            'Fecha: ' . (string) ($this->comprobante['fecha_emision'] ?? ''),
            'Venta: ' . (string) ($this->comprobante['folio'] ?? ''),
            'Cajero: ' . (string) ($this->comprobante['cajero'] ?? ''),
            'Cliente: ' . (string) ($this->cliente['nombre'] ?? 'Consumidor final'),
        ] as $linea) {
            $this->texto($linea, self::MARGEN, 8, false, self::CARBON);
            $this->y -= 11;
        }

        $documento = trim((string) ($this->cliente['documento'] ?? '')) === ''
            ? ''
            : trim((string) ($this->cliente['tipo_documento'] ?? '') . ' ' . (string) ($this->cliente['documento'] ?? ''));
        if ($documento !== '') {
            $this->texto($documento, self::MARGEN, 8, false, self::CARBON);
            $this->y -= 11;
        }

        $this->separador();

        foreach ($this->detalle as $item) {
            foreach ($this->lineasNombre((string) ($item['nombre'] ?? '')) as $linea) {
                $this->texto($linea, self::MARGEN, 8.5, false, self::CARBON);
                $this->y -= 10;
            }
            $this->texto(
                $this->cantidad($item['cantidad'] ?? 0) . ' x ' . $this->dinero($item['precio_unitario'] ?? 0),
                self::MARGEN + 6,
                8,
                false,
                self::PIEDRA
            );
            $this->derecha($this->dinero($item['total'] ?? 0), 8.5, false, self::CARBON);
            $this->y -= 14;
        }

        $this->separador();

        foreach ($this->totales as [$etiqueta, $valor, $fuerte]) {
            $tamano = $fuerte ? 11 : 8.5;
            $this->texto($etiqueta, self::MARGEN, $tamano, $fuerte, self::CARBON);
            $this->derecha($valor, $tamano, $fuerte, self::CARBON);
            $this->y -= $fuerte ? 15 : 12;
        }

        $this->separador();

        if (($this->comprobante['estado'] ?? '') === 'Anulado') {
            $this->centrado('COMPROBANTE ANULADO', 10, true, self::ERROR);
            $this->y -= 16;
        }

        $resolucion = (string) ($this->comprobante['resolucion_dian'] ?? '');
        if (trim($resolucion) !== '') {
            foreach ($this->envolver($resolucion, 44, 4) as $linea) {
                $this->centrado($linea, 7, false, self::PIEDRA);
                $this->y -= 9;
            }
        }

        $this->y -= 4;
        $this->centrado('Gracias por su compra', 9, false, self::CARBON);

        return $this->ensamblar();
    }

    private function ensamblar(): string
    {
        $objetos = [];
        $objetos[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objetos[2] = '<< /Type /Pages /Kids [3 0 R] /Count 1 >>';
        $objetos[3] = sprintf(
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] '
            . '/Resources << /Font << /F1 5 0 R /F2 6 0 R >> >> /Contents 4 0 R >>',
            self::ANCHO,
            $this->alto
        );
        $objetos[4] = '<< /Length ' . strlen($this->flujo) . " >>\nstream\n" . $this->flujo . 'endstream';
        $objetos[5] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objetos[6] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';

        $pdf = "%PDF-1.4\n";
        $posiciones = [];
        foreach ($objetos as $id => $cuerpo) {
            $posiciones[$id] = strlen($pdf);
            $pdf .= $id . " 0 obj\n" . $cuerpo . "\nendobj\n";
        }

        $inicioXref = strlen($pdf);
        $maximo = max(array_keys($objetos));
        $pdf .= "xref\n0 " . ($maximo + 1) . "\n0000000000 65535 f \n";
        for ($i = 1; $i <= $maximo; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $posiciones[$i]);
        }
        $pdf .= "trailer\n<< /Size " . ($maximo + 1) . " /Root 1 0 R >>\n";
        $pdf .= 'startxref' . "\n" . $inicioXref . "\n%%EOF";

        return $pdf;
    }
}
