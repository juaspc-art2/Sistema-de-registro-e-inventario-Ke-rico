<?php

declare(strict_types=1);

namespace Kerico\Nucleo;

// El comprobante en PDF, igual al que muestra la página: mismo contenido, fuentes y medidas que el componente
// Tirilla (frontend/src/componentes/GestionVentas.jsx) y la regla .tirilla de index.css. Si cambia uno, cambia el otro.
final class TirillaPdf
{
    // Medidas en píxeles de CSS, como en la hoja de estilos; un píxel son 0,75 puntos de PDF
    private const PUNTOS = 0.75;
    private const ANCHO = 378.0;
    // 1 px de borde más 20 px de relleno
    private const MARGEN = 21.0;
    private const RADIO = 8.0;
    private const INTERLINEA = 1.5;
    // El navegador redondea la altura de la letra al píxel físico: se imita una pantalla de doble densidad
    private const DENSIDAD = 2;

    private const NARANJA = [0.922, 0.369, 0.082];
    private const CARBON = [0.118, 0.106, 0.094];
    private const PIEDRA = [0.553, 0.443, 0.400];
    private const ARENA = [0.882, 0.749, 0.702];
    private const ERROR = [0.729, 0.102, 0.102];

    // Nombre de cada fuente dentro del PDF y su archivo
    private const FUENTES = [
        'normal' => ['F1', 'WorkSans-Regular'],
        'fuerte' => ['F2', 'WorkSans-SemiBold'],
        'marca'  => ['F3', 'Baloo2-ExtraBold'],
    ];

    private array $comprobante;
    private array $emisor;
    private array $cliente;
    private array $detalle;
    /** @var array<string, FuentePdf> */
    private array $fuentes = [];
    private string $flujo = '';

    public function __construct(array $comprobante)
    {
        $this->comprobante = $comprobante;
        $this->emisor = $comprobante['emisor'] ?? [];
        $this->cliente = $comprobante['cliente'] ?? [];
        $this->detalle = $comprobante['detalle'] ?? [];
        foreach (self::FUENTES as $clave => [, $archivo]) {
            $this->fuentes[$clave] = new FuentePdf(__DIR__ . '/../recursos/fuentes/' . $archivo . '.ttf');
        }
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

    // La misma fecha que escribe fechaHora() en la página: 24/09/2026, 02:20 p. m.
    private function fecha(string $valor): string
    {
        $marca = strtotime($valor);
        if ($marca === false) {
            return $valor;
        }
        return date('d/m/Y, h:i', $marca) . (date('a', $marca) === 'am' ? ' a. m.' : ' p. m.');
    }

    // Parte un texto en renglones que caben en $ancho, cortando entre palabras
    private function envolver(string $texto, string $fuente, float $tamano, float $ancho): array
    {
        $lineas = [];
        $actual = '';
        foreach (preg_split('/\s+/u', trim($texto), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $palabra) {
            $prueba = $actual === '' ? $palabra : $actual . ' ' . $palabra;
            if ($actual !== '' && $this->fuentes[$fuente]->ancho($prueba, $tamano) > $ancho) {
                $lineas[] = $actual;
                $actual = $palabra;
            } else {
                $actual = $prueba;
            }
        }
        if ($actual !== '') {
            $lineas[] = $actual;
        }
        return $lineas;
    }

    /**
     * Dibuja un renglón, que en la página es una caja de línea, y devuelve dónde empieza el siguiente.
     * Cada tramo es [fuente, texto, color, cifras tabulares]. Los textos vecinos de igual estilo van en un
     * solo tramo, porque el navegador los compone juntos y aplica kerning entre ellos.
     */
    private function renglon(float $tope, float $tamano, array $tramos, string $alineacion = 'izquierda', ?float $alto = null, float $espaciado = 0.0): float
    {
        $alto ??= $tamano * self::INTERLINEA;
        $ancho = 0.0;
        foreach ($tramos as [$fuente, $texto, , $tabular]) {
            $ancho += $this->fuentes[$fuente]->ancho($texto, $tamano, $tabular, $espaciado);
        }
        $x = match ($alineacion) {
            'centro'  => (self::ANCHO - $ancho) / 2,
            'derecha' => self::ANCHO - self::MARGEN - $ancho,
            default   => self::MARGEN,
        };

        // Línea base como la calcula el navegador: letra centrada en el alto del renglón, redondeando al píxel físico
        $letra = $this->fuentes[$tramos[0][0]];
        $ascenso = round($letra->ascenso * $tamano * self::DENSIDAD) / self::DENSIDAD;
        $descenso = round($letra->descenso * $tamano * self::DENSIDAD) / self::DENSIDAD;
        $base = $tope + $ascenso + floor(($alto - $ascenso - $descenso) / 2 * self::DENSIDAD) / self::DENSIDAD;

        foreach ($tramos as [$fuente, $texto, $color, $tabular]) {
            $this->flujo .= sprintf(
                "BT /%s %.2F Tf %.3F %.3F %.3F rg %.4F Tc 1 0 0 -1 %.3F %.3F Tm %s TJ ET\n",
                self::FUENTES[$fuente][0], $tamano, $color[0], $color[1], $color[2], $espaciado, $x, $base,
                $this->fuentes[$fuente]->escribir($texto, $tabular)
            );
            $x += $this->fuentes[$fuente]->ancho($texto, $tamano, $tabular, $espaciado);
        }
        return $tope + $alto;
    }

    // La raya punteada entre secciones: un <hr> de 1 px con 10 px de margen arriba y abajo
    private function raya(float $tope): float
    {
        // Rayas de 3 px separadas unos 2 px. Como el navegador, se ajusta el hueco para que la línea empiece y
        // termine en raya, eligiendo entre poner una raya más o una menos el hueco que quede más cerca de 2 px
        $largo = self::ANCHO - 2 * self::MARGEN;
        $rayas = floor(($largo + 2) / 5);
        $huecoAmplio = ($largo - $rayas * 3) / ($rayas - 1);
        $huecoEstrecho = ($largo - ($rayas + 1) * 3) / $rayas;
        $hueco = ($huecoEstrecho <= 0 || abs($huecoAmplio - 2) < abs($huecoEstrecho - 2)) ? $huecoAmplio : $huecoEstrecho;

        $this->flujo .= sprintf(
            "%.3F %.3F %.3F RG 1 w [3 %.4F] 0 d %.2F %.2F m %.2F %.2F l S [] 0 d\n",
            self::ARENA[0], self::ARENA[1], self::ARENA[2], $hueco,
            self::MARGEN, $tope + 10.5, self::ANCHO - self::MARGEN, $tope + 10.5
        );
        return $tope + 21;
    }

    // El marco redondeado de la tarjeta
    private function marco(float $alto): string
    {
        // 0,5 px hacia adentro para que el trazo de 1 px quede completo dentro de la hoja
        [$x0, $y0, $x1, $y1, $r] = [0.5, 0.5, self::ANCHO - 0.5, $alto - 0.5, self::RADIO - 0.5];
        // Distancia de los puntos de control que hace de una curva de Bézier un cuarto de círculo
        $k = $r * 0.5523;
        return sprintf(
            "%.3F %.3F %.3F RG 1 w %.3F %.3F m %.3F %.3F l %.3F %.3F %.3F %.3F %.3F %.3F c %.3F %.3F l "
            . "%.3F %.3F %.3F %.3F %.3F %.3F c %.3F %.3F l %.3F %.3F %.3F %.3F %.3F %.3F c %.3F %.3F l "
            . "%.3F %.3F %.3F %.3F %.3F %.3F c h S\n",
            self::ARENA[0], self::ARENA[1], self::ARENA[2],
            $x0 + $r, $y0, $x1 - $r, $y0, $x1 - $r + $k, $y0, $x1, $y0 + $r - $k, $x1, $y0 + $r,
            $x1, $y1 - $r, $x1, $y1 - $r + $k, $x1 - $r + $k, $y1, $x1 - $r, $y1,
            $x0 + $r, $y1, $x0 + $r - $k, $y1, $x0, $y1 - $r + $k, $x0, $y1 - $r,
            $x0, $y0 + $r, $x0, $y0 + $r - $k, $x0 + $r - $k, $y0, $x0 + $r, $y0
        );
    }

    public function generar(): string
    {
        $contenido = self::ANCHO - 2 * self::MARGEN;
        $normal = static fn (string $texto, array $color = self::CARBON, bool $tabular = false): array => ['normal', $texto, $color, $tabular];
        $y = self::MARGEN;

        // Encabezado: nombre y datos del emisor, centrados
        $nombre = trim((string) ($this->emisor['nombre'] ?? '')) ?: 'Ke-Rico!';
        $y = $this->renglon($y, 22, [['marca', $nombre, self::NARANJA, false]], 'centro', 22 * 0.95, -0.012 * 22);
        $nit = trim((string) ($this->emisor['nit'] ?? ''));
        foreach ([
            (string) ($this->emisor['razon_social'] ?? ''),
            $nit === '' ? '' : 'NIT ' . $nit,
            (string) ($this->emisor['direccion'] ?? ''),
            (string) ($this->emisor['telefono'] ?? ''),
        ] as $linea) {
            if (trim($linea) !== '') {
                $y = $this->renglon($y, 11, [$normal(trim($linea), self::PIEDRA)], 'centro');
            }
        }
        $y = $this->raya($y);

        // Datos del comprobante
        $y = $this->renglon($y, 12, [
            ['fuerte', (string) ($this->comprobante['tipo'] ?? 'Comprobante'), self::CARBON, false],
            $normal(' N.º ' . (string) ($this->comprobante['numero'] ?? '')),
        ]);
        $documento = trim((string) ($this->cliente['documento'] ?? ''));
        foreach ([
            'Fecha: ' . $this->fecha((string) ($this->comprobante['fecha_emision'] ?? '')),
            'Venta: ' . (string) ($this->comprobante['folio'] ?? ''),
            'Cajero: ' . (string) ($this->comprobante['cajero'] ?? ''),
            'Cliente: ' . (trim((string) ($this->cliente['nombre'] ?? '')) ?: 'Consumidor final'),
            $documento === '' ? '' : trim((string) ($this->cliente['tipo_documento'] ?? '') . ' ' . $documento),
        ] as $linea) {
            if ($linea !== '') {
                $y = $this->renglon($y, 12, [$normal($linea)]);
            }
        }
        $y = $this->raya($y);

        // Productos: el nombre y debajo "cantidad x precio"; a la derecha, el total de la línea
        $valores = array_map(fn (array $item): string => $this->dinero($item['total'] ?? 0), $this->detalle);
        $anchoValores = 0.0;
        foreach ($valores as $valor) {
            $anchoValores = max($anchoValores, $this->fuentes['normal']->ancho($valor, 12.5, true));
        }
        foreach ($this->detalle as $i => $item) {
            $nombreProducto = trim((string) ($item['nombre'] ?? ''));
            // Un nombre que no cabe junto a la columna de valores baja al renglón siguiente, como en la tabla de la página
            $lineas = $this->fuentes['normal']->ancho($nombreProducto, 12.5) + $anchoValores > $contenido
                ? $this->envolver($nombreProducto, 'normal', 12.5, $contenido - $anchoValores)
                : [$nombreProducto];
            $fila = $y + 3;
            $y = $fila;
            foreach ($lineas as $linea) {
                $y = $this->renglon($y, 12.5, [$normal($linea)]);
            }
            $y = $this->renglon($y, 12.5, [$normal(
                $this->cantidad($item['cantidad'] ?? 0) . ' x ' . $this->dinero($item['precio_unitario'] ?? 0),
                self::PIEDRA,
                true
            )]);
            // El valor se escribe al final para que el texto copiado salga en el orden de la página
            $this->renglon($fila, 12.5, [$normal($valores[$i], self::CARBON, true)], 'derecha');
            $y += 3;
        }
        $y = $this->raya($y);

        // Totales
        $totales = [['Subtotal', $this->dinero($this->comprobante['subtotal'] ?? 0)]];
        if ((float) ($this->comprobante['descuento'] ?? 0) > 0) {
            $totales[] = ['Descuento', '- ' . $this->dinero($this->comprobante['descuento'])];
        }
        $totales[] = ['Base gravable', $this->dinero($this->comprobante['base_gravable'] ?? 0)];
        $totales[] = ['Impuesto', $this->dinero($this->comprobante['impuesto'] ?? 0)];
        foreach ($totales as [$etiqueta, $valor]) {
            $this->renglon($y + 3, 12.5, [$normal($etiqueta)]);
            $y = $this->renglon($y + 3, 12.5, [$normal($valor, self::CARBON, true)], 'derecha') + 3;
        }
        $this->renglon($y + 3, 15, [['fuerte', 'TOTAL', self::CARBON, false]]);
        $y = $this->renglon($y + 3, 15, [['fuerte', $this->dinero($this->comprobante['total'] ?? 0), self::CARBON, true]], 'derecha') + 3;
        $y = $this->raya($y);

        // Pie
        if (($this->comprobante['estado'] ?? '') === 'Anulado') {
            $y = $this->renglon($y, 13, [['fuerte', 'COMPROBANTE ANULADO', self::ERROR, false]], 'centro') + 8;
        }
        foreach ($this->envolver((string) ($this->comprobante['resolucion_dian'] ?? ''), 'normal', 10, $contenido) as $linea) {
            $y = $this->renglon($y, 10, [$normal($linea, self::PIEDRA)], 'centro');
        }
        $y = $this->renglon($y + 8, 11, [$normal('Gracias por su compra')], 'centro');

        return $this->ensamblar($y + self::MARGEN);
    }

    private function ensamblar(float $alto): string
    {
        $objetos = [1 => '<< /Type /Catalog /Pages 2 0 R >>', 2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>', 3 => '', 4 => ''];

        // El origen pasa a la esquina superior izquierda y las medidas a píxeles, como en la página
        $flujo = sprintf("%.4F 0 0 -%.4F 0 %.3F cm\n", self::PUNTOS, self::PUNTOS, $alto * self::PUNTOS)
            . $this->marco($alto) . $this->flujo;
        $objetos[4] = '<< /Length ' . strlen($flujo) . " >>\nstream\n" . $flujo . 'endstream';

        // Las fuentes se incrustan al final, cuando ya se sabe qué letras usó el documento
        $recursos = '';
        foreach (self::FUENTES as $clave => [$nombre, $archivo]) {
            $recursos .= '/' . $nombre . ' ' . $this->fuentes[$clave]->incrustar($objetos, 'KERICO+' . $archivo) . ' 0 R ';
        }
        $objetos[3] = sprintf(
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] /Resources << /Font << %s>> >> /Contents 4 0 R >>',
            self::ANCHO * self::PUNTOS,
            $alto * self::PUNTOS,
            $recursos
        );

        // La segunda línea, con bytes altos, avisa a quien transporte el archivo de que es binario
        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
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
