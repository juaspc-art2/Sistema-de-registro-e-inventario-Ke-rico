<?php

declare(strict_types=1);

// Tarea de consola: por web cualquiera podría lanzar respaldos sin iniciar sesión
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

use Kerico\Modelo\Sistema;
use Kerico\Nucleo\Bd;

$config = require __DIR__ . '/../config/config.php';

date_default_timezone_set($config['app']['zona_horaria']);

require __DIR__ . '/../nucleo/cargador.php';

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
