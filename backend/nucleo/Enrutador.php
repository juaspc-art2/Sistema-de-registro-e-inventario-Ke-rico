<?php

declare(strict_types=1);

namespace Kerico\Nucleo;

final class Enrutador
{
    private array $rutas = [];

    public function registrar(string $metodo, string $patron, callable $accion, bool $publica = false): void
    {
        $this->rutas[] = [
            'metodo'  => strtoupper($metodo),
            'patron'  => $patron,
            'regex'   => $this->compilar($patron),
            'accion'  => $accion,
            'publica' => $publica,
        ];
    }

    public function get(string $patron, callable $accion, bool $publica = false): void
    {
        $this->registrar('GET', $patron, $accion, $publica);
    }

    public function post(string $patron, callable $accion, bool $publica = false): void
    {
        $this->registrar('POST', $patron, $accion, $publica);
    }

    public function put(string $patron, callable $accion, bool $publica = false): void
    {
        $this->registrar('PUT', $patron, $accion, $publica);
    }

    public function delete(string $patron, callable $accion, bool $publica = false): void
    {
        $this->registrar('DELETE', $patron, $accion, $publica);
    }

    private function compilar(string $patron): string
    {
        $escapado = preg_replace('#\{([a-zA-Z_]+)\}#', '(?P<$1>[^/]+)', $patron);
        return '#^' . $escapado . '$#';
    }

    public function despachar(Peticion $peticion): void
    {
        $rutaCoincide = false;

        foreach ($this->rutas as $ruta) {
            if (preg_match($ruta['regex'], $peticion->ruta(), $coincidencias) !== 1) {
                continue;
            }
            $rutaCoincide = true;
            if ($ruta['metodo'] !== $peticion->metodo()) {
                continue;
            }

            $parametros = [];
            foreach ($coincidencias as $clave => $valor) {
                if (!is_int($clave)) {
                    $parametros[$clave] = $valor;
                }
            }
            $peticion->definirParametros($parametros);

            if (!$ruta['publica']) {
                Autenticacion::autenticar($peticion);
                if (in_array($peticion->metodo(), ['POST', 'PUT', 'DELETE'], true)) {
                    Autenticacion::exigirCsrf($peticion);
                }
            }

            ($ruta['accion'])($peticion);
            return;
        }

        if ($rutaCoincide) {
            throw new ErrorHttp('El método ' . $peticion->metodo() . ' no está permitido en esta ruta', 405);
        }

        throw ErrorHttp::noEncontrado('La ruta ' . $peticion->ruta() . ' no existe en la API');
    }
}
