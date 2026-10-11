<?php

declare(strict_types=1);

namespace Kerico\Nucleo;

final class Validador
{
    // Topes de negocio, muy por debajo de lo que cabe en las columnas DECIMAL(12,2) y DECIMAL(12,3)
    public const DINERO_MAXIMO = 99999999.99;
    public const CANTIDAD_MAXIMA = 999999.999;

    // Lo que admite cada formato. La vista filtra los mismos caracteres mientras se escribe (FORMATOS en ui/Componentes.jsx)
    private const FORMATOS = [
        'telefono' => ['/^(?=(?:\D*\d){7,15}\D*$)[\d\s()+\-]+$/', 'debe tener entre 7 y 15 dígitos; admite espacios, paréntesis, + y guion'],
        'nombre'   => ['/^[\p{L}\p{M}][\p{L}\p{M}\s.\'\-]*$/u', 'solo admite letras, espacios, punto, apóstrofo y guion'],
        'usuario'  => ['/^[A-Za-z0-9._\-]+$/', 'solo admite letras sin tilde, números, punto y guiones, sin espacios'],
        'codigo'   => ['/^[A-Za-z0-9][A-Za-z0-9_\-]*$/', 'solo admite letras sin tilde, números y guiones, sin espacios'],
        'unidad'   => ['/^[\p{L}][\p{L}\s.]*$/u', 'solo admite letras'],
        'imagen'   => ['#^(?!.*\.\.)[A-Za-z0-9_\-][\w\-/.]*\.(jpe?g|png|webp|gif|svg)$#i', 'debe ser la ruta de una imagen del sistema, como marca/productos/foto.jpg'],
        // Documentos de identidad, por tipo
        'CC'       => ['/^\d{5,12}$/', 'debe tener entre 5 y 12 dígitos, sin puntos ni espacios'],
        'TI'       => ['/^\d{5,12}$/', 'debe tener entre 5 y 12 dígitos, sin puntos ni espacios'],
        'CE'       => ['/^[A-Za-z0-9]{5,15}$/', 'debe tener entre 5 y 15 letras o números, sin espacios'],
        'PA'       => ['/^[A-Za-z0-9]{5,15}$/', 'debe tener entre 5 y 15 letras o números, sin espacios'],
        'NIT'      => ['/^\d{3}\.?\d{3}\.?\d{3}(-?\d)?$/', 'debe tener 9 dígitos y, si lo lleva, el dígito de verificación: 900.100.001-1'],
        'NA'       => ['/^[A-Za-z0-9.\-]+$/', 'solo admite letras, números, punto y guion'],
    ];

    private array $datos;
    private array $errores = [];

    public function __construct(array $datos)
    {
        $this->datos = $datos;
    }

    public function texto(string $campo, string $etiqueta, bool $obligatorio = true, int $maximo = 255, int $minimo = 0, bool $crudo = false): string
    {
        $valor = (string) ($this->datos[$campo] ?? '');
        if (!$crudo) {
            $valor = Sanitizador::texto($valor);
        }
        if ($valor === '' && $obligatorio) {
            $this->errores[$campo] = $etiqueta . ' es obligatorio';
            return '';
        }
        if ($valor !== '' && mb_strlen($valor) > $maximo) {
            $this->errores[$campo] = $etiqueta . ' no puede superar ' . $maximo . ' caracteres';
        }
        if ($valor !== '' && mb_strlen($valor) < $minimo) {
            $this->errores[$campo] = $etiqueta . ' debe tener al menos ' . $minimo . ' caracteres';
        }
        return $valor;
    }

    // Devuelve qué le falta al valor para cumplir el formato, o null si lo cumple
    public static function faltaFormato(string $formato, string $valor): ?string
    {
        [$patron, $falta] = self::FORMATOS[$formato];
        return preg_match($patron, $valor) === 1 ? null : $falta;
    }

    public function formato(string $campo, string $etiqueta, string $formato, bool $obligatorio = true, int $maximo = 255, int $minimo = 0): string
    {
        $valor = $this->texto($campo, $etiqueta, $obligatorio, $maximo, $minimo);
        // Un formato desconocido solo llega cuando el tipo de documento ya falló su propia validación
        if ($valor !== '' && !isset($this->errores[$campo]) && isset(self::FORMATOS[$formato])) {
            $falta = self::faltaFormato($formato, $valor);
            if ($falta !== null) {
                $this->errores[$campo] = $etiqueta . ' ' . $falta;
            }
        }
        return $valor;
    }

    public function correo(string $campo, string $etiqueta, bool $obligatorio = true): string
    {
        $valor = trim((string) ($this->datos[$campo] ?? ''));
        if ($valor === '') {
            if ($obligatorio) {
                $this->errores[$campo] = $etiqueta . ' es obligatorio';
            }
            return '';
        }
        if (filter_var($valor, FILTER_VALIDATE_EMAIL) === false) {
            $this->errores[$campo] = $etiqueta . ' no tiene un formato válido';
        } elseif (strlen($valor) > 120) {
            $this->errores[$campo] = $etiqueta . ' no puede superar 120 caracteres';
        }
        return $valor;
    }

    // is_numeric acepta "1e5" y un JSON con 1e400 llega como infinito: aquí solo pasan números escritos con dígitos
    private static function esNumero(mixed $crudo): bool
    {
        if (is_string($crudo)) {
            return preg_match('/^-?(\d+(\.\d*)?|\.\d+)$/', trim($crudo)) === 1;
        }
        return is_int($crudo) || (is_float($crudo) && is_finite($crudo));
    }

    public function entero(string $campo, string $etiqueta, bool $obligatorio = true, ?int $minimo = null, ?int $maximo = null): ?int
    {
        $crudo = $this->datos[$campo] ?? null;
        if ($crudo === null || $crudo === '') {
            if ($obligatorio) {
                $this->errores[$campo] = $etiqueta . ' es obligatorio';
            }
            return null;
        }
        if (!self::esNumero($crudo) || (int) $crudo != $crudo) {
            $this->errores[$campo] = $etiqueta . ' debe ser un número entero';
            return null;
        }
        $valor = (int) $crudo;
        if ($minimo !== null && $valor < $minimo) {
            $this->errores[$campo] = $etiqueta . ' no puede ser menor que ' . $minimo;
        }
        if ($maximo !== null && $valor > $maximo) {
            $this->errores[$campo] = $etiqueta . ' no puede ser mayor que ' . $maximo;
        }
        return $valor;
    }

    public function decimal(string $campo, string $etiqueta, bool $obligatorio = true, ?float $minimo = null, ?float $maximo = null): ?float
    {
        $crudo = $this->datos[$campo] ?? null;
        if ($crudo === null || $crudo === '') {
            if ($obligatorio) {
                $this->errores[$campo] = $etiqueta . ' es obligatorio';
            }
            return null;
        }
        if (!self::esNumero($crudo)) {
            $this->errores[$campo] = $etiqueta . ' debe ser un número';
            return null;
        }
        $valor = (float) $crudo;
        if ($minimo !== null && $valor < $minimo) {
            $this->errores[$campo] = $etiqueta . ' no puede ser menor que ' . $minimo;
        }
        if ($maximo !== null && $valor > $maximo) {
            $this->errores[$campo] = $etiqueta . ' no puede ser mayor que ' . $maximo;
        }
        return $valor;
    }

    public function opcion(string $campo, string $etiqueta, array $permitidos, bool $obligatorio = true, string $porDefecto = ''): string
    {
        $valor = trim((string) ($this->datos[$campo] ?? ''));
        if ($valor === '') {
            if ($obligatorio) {
                $this->errores[$campo] = $etiqueta . ' es obligatorio';
                return '';
            }
            return $porDefecto;
        }
        if (!in_array($valor, $permitidos, true)) {
            $this->errores[$campo] = $etiqueta . ' debe ser uno de: ' . implode(', ', $permitidos);
        }
        return $valor;
    }

    public function booleano(string $campo, bool $porDefecto = false): bool
    {
        if (!array_key_exists($campo, $this->datos)) {
            return $porDefecto;
        }
        $valor = $this->datos[$campo];
        if (is_bool($valor)) {
            return $valor;
        }
        return in_array(strtolower((string) $valor), ['1', 'true', 'si', 'on'], true);
    }

    // $minima y $maxima van como AAAA-MM-DD, igual que el valor
    public function fecha(string $campo, string $etiqueta, bool $obligatorio = true, ?string $minima = null, ?string $maxima = null): ?string
    {
        $valor = trim((string) ($this->datos[$campo] ?? ''));
        if ($valor === '') {
            if ($obligatorio) {
                $this->errores[$campo] = $etiqueta . ' es obligatorio';
            }
            return null;
        }
        // strtotime aceptaba "tomorrow" o "31 de febrero": solo vale una fecha real escrita como AAAA-MM-DD
        $fecha = \DateTimeImmutable::createFromFormat('!Y-m-d', $valor);
        if ($fecha === false || $fecha->format('Y-m-d') !== $valor) {
            $this->errores[$campo] = $etiqueta . ' no tiene una fecha válida';
            return null;
        }
        if ($minima !== null && $valor < $minima) {
            $this->errores[$campo] = $etiqueta . ' no puede ser anterior a ' . $minima;
        }
        if ($maxima !== null && $valor > $maxima) {
            $this->errores[$campo] = $etiqueta . ' no puede ser posterior a ' . $maxima;
        }
        return $valor;
    }

    public function lista(string $campo, string $etiqueta, int $minimo = 1): array
    {
        $valor = $this->datos[$campo] ?? null;
        if (!is_array($valor) || count($valor) < $minimo) {
            $this->errores[$campo] = $etiqueta . ' debe contener al menos ' . $minimo . ' elemento(s)';
            return [];
        }
        return $valor;
    }

    public function agregarError(string $campo, string $mensaje): void
    {
        $this->errores[$campo] = $mensaje;
    }

    public function fallo(): bool
    {
        return $this->errores !== [];
    }

    public function errores(): array
    {
        return $this->errores;
    }

    public function validar(string $mensaje = 'Los datos enviados no son válidos'): void
    {
        if ($this->fallo()) {
            throw ErrorHttp::validacion($mensaje, $this->errores);
        }
    }
}
