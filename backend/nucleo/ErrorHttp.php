<?php

declare(strict_types=1);

namespace Kerico\Nucleo;

use RuntimeException;

class ErrorHttp extends RuntimeException
{
    private array $detalles;

    public function __construct(string $mensaje, int $codigo = 400, array $detalles = [])
    {
        parent::__construct($mensaje, $codigo);
        $this->detalles = $detalles;
    }

    public function detalles(): array
    {
        return $this->detalles;
    }

    public static function noAutenticado(string $mensaje = 'Debe iniciar sesión para continuar'): self
    {
        return new self($mensaje, 401);
    }

    public static function sinPermiso(string $mensaje = 'No tiene permisos para realizar esta acción'): self
    {
        return new self($mensaje, 403);
    }

    public static function noEncontrado(string $mensaje = 'El recurso solicitado no existe'): self
    {
        return new self($mensaje, 404);
    }

    public static function validacion(string $mensaje, array $errores = []): self
    {
        return new self($mensaje, 422, $errores);
    }

    public static function conflicto(string $mensaje): self
    {
        return new self($mensaje, 409);
    }
}
