<?php

declare(strict_types=1);

namespace Kerico\Nucleo;

final class Peticion
{
    private string $metodo;
    private string $ruta;
    private array $consulta;
    private array $cuerpo;
    private array $parametros = [];

    public function __construct()
    {
        $this->metodo = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $this->ruta = $this->resolverRuta();
        $this->consulta = $_GET ?? [];
        $this->cuerpo = $this->leerCuerpo();
    }

    private function resolverRuta(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $uri = parse_url($uri, PHP_URL_PATH) ?: '/';
        $script = $_SERVER['SCRIPT_NAME'] ?? '';
        $base = rtrim(str_replace('\\', '/', dirname($script)), '/');

        if ($base !== '' && $base !== '/' && str_starts_with($uri, $base)) {
            $uri = substr($uri, strlen($base));
        }

        $uri = '/' . trim($uri, '/');

        if (str_starts_with($uri, '/index.php')) {
            $uri = substr($uri, strlen('/index.php'));
            $uri = '/' . trim($uri, '/');
        }

        if (str_starts_with($uri, '/api')) {
            $uri = substr($uri, strlen('/api'));
            $uri = '/' . trim($uri, '/');
        }

        return $uri === '' ? '/' : $uri;
    }

    private function leerCuerpo(): array
    {
        $crudo = file_get_contents('php://input');
        if ($crudo === false || trim($crudo) === '') {
            return is_array($_POST) ? $_POST : [];
        }

        $tipo = $_SERVER['CONTENT_TYPE'] ?? '';
        if (str_contains($tipo, 'application/json')) {
            $datos = json_decode($crudo, true);
            return is_array($datos) ? $datos : [];
        }

        parse_str($crudo, $datos);
        return is_array($datos) ? $datos : [];
    }

    public function metodo(): string
    {
        return $this->metodo;
    }

    public function ruta(): string
    {
        return $this->ruta;
    }

    public function definirParametros(array $parametros): void
    {
        $this->parametros = $parametros;
    }

    public function parametro(string $nombre, $porDefecto = null)
    {
        return $this->parametros[$nombre] ?? $porDefecto;
    }

    public function consulta(string $nombre, $porDefecto = null)
    {
        $valor = $this->consulta[$nombre] ?? null;
        if ($valor === null || $valor === '') {
            return $porDefecto;
        }
        return $valor;
    }

    public function entrada(string $nombre, $porDefecto = null)
    {
        return $this->cuerpo[$nombre] ?? $porDefecto;
    }

    public function cuerpo(): array
    {
        return $this->cuerpo;
    }

    public function cabecera(string $nombre): string
    {
        $clave = 'HTTP_' . str_replace('-', '_', strtoupper($nombre));
        if (isset($_SERVER[$clave])) {
            return (string) $_SERVER[$clave];
        }
        if (function_exists('getallheaders')) {
            foreach (getallheaders() as $k => $v) {
                if (strcasecmp($k, $nombre) === 0) {
                    return (string) $v;
                }
            }
        }
        return '';
    }

    public function token(): string
    {
        $autorizacion = $this->cabecera('Authorization');
        if (preg_match('/Bearer\s+(.+)/i', $autorizacion, $coincidencias) === 1) {
            return trim($coincidencias[1]);
        }
        $alterno = $this->cabecera('X-Token-Kerico');
        if ($alterno !== '') {
            return trim($alterno);
        }
        return (string) ($this->consulta['token'] ?? '');
    }

    public function csrf(): string
    {
        $cabecera = $this->cabecera('X-CSRF-Kerico');
        if ($cabecera !== '') {
            return trim($cabecera);
        }
        return trim((string) ($this->cuerpo['csrf_token'] ?? ''));
    }

    public function ip(): string
    {
        return (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    }

    public function agente(): string
    {
        return substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
    }
}
