<?php

declare(strict_types=1);

namespace Kerico\Nucleo;

final class Validador
{
    private array $datos;
    private array $errores = [];

    public function __construct(array $datos)
    {
        $this->datos = $datos;
    }

    public function texto(string $campo, string $etiqueta, bool $obligatorio = true, int $maximo = 255, int $minimo = 0): string
    {
        $valor = Sanitizador::texto((string) ($this->datos[$campo] ?? ''));
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
        }
        return $valor;
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
        if (!is_numeric($crudo) || (int) $crudo != $crudo) {
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
        if (!is_numeric($crudo)) {
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

    public function fecha(string $campo, string $etiqueta, bool $obligatorio = true): ?string
    {
        $valor = trim((string) ($this->datos[$campo] ?? ''));
        if ($valor === '') {
            if ($obligatorio) {
                $this->errores[$campo] = $etiqueta . ' es obligatorio';
            }
            return null;
        }
        $marca = strtotime($valor);
        if ($marca === false) {
            $this->errores[$campo] = $etiqueta . ' no tiene una fecha válida';
            return null;
        }
        return date('Y-m-d', $marca);
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
