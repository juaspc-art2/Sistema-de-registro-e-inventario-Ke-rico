<?php

declare(strict_types=1);

use Kerico\Modelo\Sistema;
use Kerico\Nucleo\Bd;

$config = require __DIR__ . '/../config/config.php';

date_default_timezone_set($config['app']['zona_horaria']);

spl_autoload_register(static function (string $clase): void {
    $prefijo = 'Kerico\\';
    if (!str_starts_with($clase, $prefijo)) {
        return;
    }
    $partes = explode('\\', substr($clase, strlen($prefijo)));
    $mapa = ['Nucleo' => 'nucleo', 'Modelo' => 'modelo', 'Controlador' => 'controlador'];
    $carpeta = $mapa[$partes[0]] ?? strtolower($partes[0]);
    $archivo = __DIR__ . '/../' . $carpeta . '/' . end($partes) . '.php';
    if (is_file($archivo)) {
        require $archivo;
    }
});

Bd::configurar($config['bd']);

$forzar = in_array('--forzar', $argv ?? [], true);

if (!$forzar && !Sistema::respaldoPendiente()) {
    echo 'Aún no corresponde generar un respaldo.', PHP_EOL;
    exit(0);
}

try {
    $respaldo = Sistema::generarRespaldo('Automatico');
    echo 'Respaldo generado: ', $respaldo['nombre_archivo'], ' (', $respaldo['tamano_bytes'], ' bytes)', PHP_EOL;
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'Falló el respaldo: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
