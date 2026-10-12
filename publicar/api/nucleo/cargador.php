<?php

declare(strict_types=1);

// Carga cada clase Kerico\Nucleo, Kerico\Modelo o Kerico\Controlador desde su carpeta.
// Lo incluyen la API (index.php) y las tareas de consola.
spl_autoload_register(static function (string $clase): void {
    $prefijo = 'Kerico\\';
    if (!str_starts_with($clase, $prefijo)) {
        return;
    }
    $partes = explode('\\', substr($clase, strlen($prefijo)));
    $archivo = dirname(__DIR__) . '/' . strtolower($partes[0]) . '/' . end($partes) . '.php';
    if (is_file($archivo)) {
        require $archivo;
    }
});
