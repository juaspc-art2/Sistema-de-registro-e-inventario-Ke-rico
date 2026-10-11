<?php

declare(strict_types=1);

namespace Kerico\Nucleo;

// Una fuente TrueType para un PDF. Mide y compone el texto como el navegador (kerning y cifras tabulares),
// para que lo impreso coincida con lo que muestra la página, y se incrusta en el archivo.
final class FuentePdf
{
    public readonly float $ascenso;
    public readonly float $descenso;

    private string $datos;
    private array $tablas = [];
    private int $unidades;
    private int $conMetrica;
    private array $glifoDe = [];
    private array $tabular = [];
    private ?array $kerning = null;
    // Glifos escritos en el documento, con el carácter que representan: definen los anchos y el texto copiable
    private array $usados = [];

    public function __construct(string $ruta)
    {
        $this->datos = (string) @file_get_contents($ruta);
        // Un TrueType empieza por 00 01 00 00
        if (strlen($this->datos) < 12 || $this->u32(0) !== 0x00010000) {
            throw new \RuntimeException('No se pudo leer la fuente ' . basename($ruta));
        }
        for ($i = 0, $n = $this->u16(4); $i < $n; $i++) {
            $p = 12 + $i * 16;
            $this->tablas[substr($this->datos, $p, 4)] = $this->u32($p + 8);
        }

        $this->unidades = $this->u16($this->tablas['head'] + 18);
        $this->ascenso = $this->s16($this->tablas['hhea'] + 4) / $this->unidades;
        $this->descenso = -$this->s16($this->tablas['hhea'] + 6) / $this->unidades;
        $this->conMetrica = $this->u16($this->tablas['hhea'] + 34);
        $this->leerCaracteres();
        foreach ($this->consultas('GSUB', 'tnum') as [$tipo, $sub]) {
            if ($tipo === 1) {
                $this->leerSustitucion($sub);
            }
        }
    }

    private function u16(int $p): int
    {
        return unpack('n', $this->datos, $p)[1];
    }

    private function s16(int $p): int
    {
        $v = $this->u16($p);
        return $v >= 0x8000 ? $v - 0x10000 : $v;
    }

    private function u32(int $p): int
    {
        return unpack('N', $this->datos, $p)[1];
    }

    // Tabla cmap de formato 4: qué glifo dibuja cada carácter
    private function leerCaracteres(): void
    {
        $cmap = $this->tablas['cmap'];
        for ($i = 0, $n = $this->u16($cmap + 2); $i < $n; $i++) {
            $sub = $cmap + $this->u32($cmap + 4 + $i * 8 + 4);
            if ($this->u16($sub) !== 4) {
                continue;
            }
            $segmentos = intdiv($this->u16($sub + 6), 2);
            for ($s = 0; $s < $segmentos; $s++) {
                $fin = $this->u16($sub + 14 + $s * 2);
                $inicio = $this->u16($sub + 16 + $segmentos * 2 + $s * 2);
                $delta = $this->s16($sub + 16 + $segmentos * 4 + $s * 2);
                $posicion = $sub + 16 + $segmentos * 6 + $s * 2;
                $desplazamiento = $this->u16($posicion);
                for ($c = $inicio; $c <= $fin && $c !== 0xFFFF; $c++) {
                    $glifo = $desplazamiento === 0
                        ? ($c + $delta) & 0xFFFF
                        : $this->u16($posicion + $desplazamiento + ($c - $inicio) * 2);
                    if ($desplazamiento !== 0 && $glifo !== 0) {
                        $glifo = ($glifo + $delta) & 0xFFFF;
                    }
                    $this->glifoDe[$c] = $glifo;
                }
            }
            return;
        }
        throw new \RuntimeException('La fuente no trae una tabla de caracteres compatible');
    }

    // Subtablas de las consultas que una función OpenType (kern, tnum) activa para el alfabeto latino
    private function consultas(string $tabla, string $funcion): array
    {
        if (!isset($this->tablas[$tabla])) {
            return [];
        }
        $base = $this->tablas[$tabla];
        $alfabetos = $base + $this->u16($base + 4);
        $funciones = $base + $this->u16($base + 6);
        $lista = $base + $this->u16($base + 8);

        $alfabeto = null;
        for ($i = 0, $n = $this->u16($alfabetos); $i < $n; $i++) {
            $etiqueta = substr($this->datos, $alfabetos + 2 + $i * 6, 4);
            if ($etiqueta === 'latn' || ($etiqueta === 'DFLT' && $alfabeto === null)) {
                $alfabeto = $alfabetos + $this->u16($alfabetos + 2 + $i * 6 + 4);
            }
        }
        if ($alfabeto === null || $this->u16($alfabeto) === 0) {
            return [];
        }
        $idioma = $alfabeto + $this->u16($alfabeto);

        $indices = [];
        for ($i = 0, $n = $this->u16($idioma + 4); $i < $n; $i++) {
            $registro = $funciones + 2 + $this->u16($idioma + 6 + $i * 2) * 6;
            if (substr($this->datos, $registro, 4) !== $funcion) {
                continue;
            }
            $definicion = $funciones + $this->u16($registro + 4);
            for ($j = 0, $m = $this->u16($definicion + 2); $j < $m; $j++) {
                $indices[$this->u16($definicion + 4 + $j * 2)] = true;
            }
        }
        ksort($indices);

        $subtablas = [];
        foreach (array_keys($indices) as $indice) {
            $consulta = $lista + $this->u16($lista + 2 + $indice * 2);
            $tipo = $this->u16($consulta);
            for ($i = 0, $n = $this->u16($consulta + 4); $i < $n; $i++) {
                $sub = $consulta + $this->u16($consulta + 6 + $i * 2);
                // Los tipos 7 (GSUB) y 9 (GPOS) solo envuelven otra subtabla que queda más lejos en el archivo
                if (($tabla === 'GSUB' && $tipo === 7) || ($tabla === 'GPOS' && $tipo === 9)) {
                    $subtablas[] = [$this->u16($sub + 2), $sub + $this->u32($sub + 4), $indice];
                } else {
                    $subtablas[] = [$tipo, $sub, $indice];
                }
            }
        }
        return $subtablas;
    }

    // Glifos que cubre una subtabla, cada uno con su posición en ella
    private function cobertura(int $p): array
    {
        $glifos = [];
        if ($this->u16($p) === 1) {
            for ($i = 0, $n = $this->u16($p + 2); $i < $n; $i++) {
                $glifos[$this->u16($p + 4 + $i * 2)] = $i;
            }
            return $glifos;
        }
        for ($i = 0, $n = $this->u16($p + 2); $i < $n; $i++) {
            $inicio = $this->u16($p + 4 + $i * 6);
            $posicion = $this->u16($p + 8 + $i * 6);
            for ($g = $inicio, $fin = $this->u16($p + 6 + $i * 6); $g <= $fin; $g++) {
                $glifos[$g] = $posicion + $g - $inicio;
            }
        }
        return $glifos;
    }

    private function clases(int $p): array
    {
        $clases = [];
        if ($this->u16($p) === 1) {
            $inicio = $this->u16($p + 2);
            for ($i = 0, $n = $this->u16($p + 4); $i < $n; $i++) {
                $clases[$inicio + $i] = $this->u16($p + 6 + $i * 2);
            }
            return $clases;
        }
        for ($i = 0, $n = $this->u16($p + 2); $i < $n; $i++) {
            $clase = $this->u16($p + 8 + $i * 6);
            for ($g = $this->u16($p + 4 + $i * 6), $fin = $this->u16($p + 6 + $i * 6); $g <= $fin; $g++) {
                $clases[$g] = $clase;
            }
        }
        return $clases;
    }

    private function leerSustitucion(int $sub): void
    {
        foreach ($this->cobertura($sub + $this->u16($sub + 2)) as $glifo => $posicion) {
            $this->tabular[$glifo] ??= $this->u16($sub) === 1
                ? ($glifo + $this->s16($sub + 4)) & 0xFFFF
                : $this->u16($sub + 6 + $posicion * 2);
        }
    }

    // Tamaño en bytes de un registro de valores y dónde queda en él el ajuste de avance horizontal
    private function registro(int $formato): array
    {
        $bytes = 0;
        for ($bit = 1; $bit <= 0x80; $bit <<= 1) {
            $bytes += ($formato & $bit) ? 2 : 0;
        }
        $avance = ($formato & 0x4) ? (($formato & 0x1) ? 2 : 0) + (($formato & 0x2) ? 2 : 0) : null;
        return [$bytes, $avance];
    }

    // El kerning se lee una sola vez, la primera vez que se mide un texto
    private function leerKerning(): array
    {
        $porConsulta = [];
        foreach ($this->consultas('GPOS', 'kern') as [$tipo, $sub, $consulta]) {
            if ($tipo !== 2) {
                continue;
            }
            $formato = $this->u16($sub);
            $cobertura = $this->cobertura($sub + $this->u16($sub + 2));
            [$bytes1, $avance] = $this->registro($this->u16($sub + 4));
            [$bytes2] = $this->registro($this->u16($sub + 6));
            if ($avance === null) {
                continue;
            }
            if ($formato === 1) {
                $pares = [];
                foreach ($cobertura as $primero => $posicion) {
                    $conjunto = $sub + $this->u16($sub + 10 + $posicion * 2);
                    for ($i = 0, $n = $this->u16($conjunto); $i < $n; $i++) {
                        $par = $conjunto + 2 + $i * (2 + $bytes1 + $bytes2);
                        $pares[$primero][$this->u16($par)] = $this->s16($par + 2 + $avance);
                    }
                }
                $porConsulta[$consulta][] = ['pares' => $pares];
            } else {
                $porConsulta[$consulta][] = [
                    'cobertura' => $cobertura,
                    'clases1'   => $this->clases($sub + $this->u16($sub + 8)),
                    'clases2'   => $this->clases($sub + $this->u16($sub + 10)),
                    'columnas'  => $this->u16($sub + 14),
                    'inicio'    => $sub + 16,
                    'celda'     => $bytes1 + $bytes2,
                    'avance'    => $avance,
                ];
            }
        }
        return array_values($porConsulta);
    }

    private function ajuste(int $a, int $b): int
    {
        $this->kerning ??= $this->leerKerning();
        $total = 0;
        foreach ($this->kerning as $subtablas) {
            // Dentro de una consulta solo cuenta la primera subtabla que conoce el par
            foreach ($subtablas as $s) {
                if (isset($s['pares'])) {
                    if (isset($s['pares'][$a][$b])) {
                        $total += $s['pares'][$a][$b];
                        break;
                    }
                    continue;
                }
                if (isset($s['cobertura'][$a])) {
                    $celda = ($s['clases1'][$a] ?? 0) * $s['columnas'] + ($s['clases2'][$b] ?? 0);
                    $total += $this->s16($s['inicio'] + $celda * $s['celda'] + $s['avance']);
                    break;
                }
            }
        }
        return $total;
    }

    private function avance(int $glifo): int
    {
        return $this->u16($this->tablas['hmtx'] + min($glifo, $this->conMetrica - 1) * 4);
    }

    // Convierte un texto en glifos: [glifo, avance, ajuste de kerning con el siguiente], en unidades de la fuente
    private function componer(string $texto, bool $tabular): array
    {
        $glifos = [];
        foreach (mb_str_split($texto, 1, 'UTF-8') as $caracter) {
            $glifo = $this->glifoDe[mb_ord($caracter, 'UTF-8')] ?? 0;
            // Un carácter que la fuente no tiene se omite, en vez de dibujar un recuadro vacío
            if ($glifo === 0) {
                continue;
            }
            if ($tabular) {
                $glifo = $this->tabular[$glifo] ?? $glifo;
            }
            $this->usados[$glifo] ??= $caracter;
            $glifos[] = [$glifo, $this->avance($glifo), 0];
        }
        for ($i = 0, $n = count($glifos) - 1; $i < $n; $i++) {
            $glifos[$i][2] = $this->ajuste($glifos[$i][0], $glifos[$i + 1][0]);
        }
        return $glifos;
    }

    public function ancho(string $texto, float $tamano, bool $tabular = false, float $espaciado = 0.0): float
    {
        $unidades = 0;
        $glifos = $this->componer($texto, $tabular);
        foreach ($glifos as [, $avance, $ajuste]) {
            $unidades += $avance + $ajuste;
        }
        return $unidades * $tamano / $this->unidades + count($glifos) * $espaciado;
    }

    // El texto listo para el operador TJ: glifos en hexadecimal, con el kerning entre ellos
    public function escribir(string $texto, bool $tabular = false): string
    {
        $salida = '';
        $abierto = false;
        foreach ($this->componer($texto, $tabular) as [$glifo, , $ajuste]) {
            $salida .= ($abierto ? '' : '<') . sprintf('%04X', $glifo);
            $abierto = true;
            if ($ajuste !== 0) {
                // TJ resta sus números, en milésimas del tamaño de letra
                $salida .= sprintf('> %.1F ', -$ajuste * 1000 / $this->unidades);
                $abierto = false;
            }
        }
        return '[' . $salida . ($abierto ? '>' : '') . ']';
    }

    // Agrega a $objetos los cinco objetos PDF de la fuente y devuelve el número del principal
    public function incrustar(array &$objetos, string $nombre): int
    {
        $escala = 1000 / $this->unidades;
        $head = $this->tablas['head'];
        $os2 = $this->tablas['OS/2'];
        ksort($this->usados);

        $anchos = '';
        $unicode = [];
        foreach ($this->usados as $glifo => $caracter) {
            $anchos .= sprintf('%d [%d] ', $glifo, round($this->avance($glifo) * $escala));
            $unicode[] = sprintf('<%04X> <%s>', $glifo, strtoupper(bin2hex(mb_convert_encoding($caracter, 'UTF-16BE', 'UTF-8'))));
        }
        // Tabla para copiar y buscar texto: qué carácter es cada glifo. El formato admite 100 entradas por bloque
        $mapa = "/CIDInit /ProcSet findresource begin 12 dict begin begincmap\n"
            . "/CIDSystemInfo << /Registry (Adobe) /Ordering (UCS) /Supplement 0 >> def\n"
            . "/CMapName /Adobe-Identity-UCS def /CMapType 2 def\n"
            . "1 begincodespacerange <0000> <FFFF> endcodespacerange\n";
        foreach (array_chunk($unicode, 100) as $bloque) {
            $mapa .= count($bloque) . " beginbfchar\n" . implode("\n", $bloque) . "\nendbfchar\n";
        }
        $mapa .= 'endcmap CMapName currentdict /CMap defineresource pop end end';

        $siguiente = $objetos === [] ? 1 : max(array_keys($objetos)) + 1;
        $comprimida = gzcompress($this->datos, 9);
        $objetos[$siguiente] = '<< /Length ' . strlen($comprimida) . ' /Length1 ' . strlen($this->datos)
            . " /Filter /FlateDecode >>\nstream\n" . $comprimida . "\nendstream";
        $objetos[$siguiente + 1] = sprintf(
            '<< /Type /FontDescriptor /FontName /%s /Flags 4 /FontBBox [%d %d %d %d] /ItalicAngle 0 '
            . '/Ascent %d /Descent %d /CapHeight %d /StemV %d /FontFile2 %d 0 R >>',
            $nombre,
            round($this->s16($head + 36) * $escala), round($this->s16($head + 38) * $escala),
            round($this->s16($head + 40) * $escala), round($this->s16($head + 42) * $escala),
            round($this->ascenso * 1000), round(-$this->descenso * 1000),
            round($this->s16($os2 + 88) * $escala),
            // Grosor aproximado del trazo según el peso declarado; solo lo usa un lector que no pueda cargar la fuente
            round(10 + 220 * ($this->u16($os2 + 4) - 50) / 900),
            $siguiente
        );
        $objetos[$siguiente + 2] = '<< /Type /Font /Subtype /CIDFontType2 /BaseFont /' . $nombre
            . ' /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /FontDescriptor '
            . ($siguiente + 1) . ' 0 R /DW 1000 /W [' . trim($anchos) . '] /CIDToGIDMap /Identity >>';
        $objetos[$siguiente + 3] = '<< /Length ' . strlen($mapa) . " >>\nstream\n" . $mapa . "\nendstream";
        $objetos[$siguiente + 4] = '<< /Type /Font /Subtype /Type0 /BaseFont /' . $nombre . ' /Encoding /Identity-H '
            . '/DescendantFonts [' . ($siguiente + 2) . ' 0 R] /ToUnicode ' . ($siguiente + 3) . ' 0 R >>';

        return $siguiente + 4;
    }
}
