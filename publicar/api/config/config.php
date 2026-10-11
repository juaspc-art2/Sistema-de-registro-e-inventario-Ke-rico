<?php

declare(strict_types=1);

function entorno(string $clave, string $porDefecto): string
{
    $valor = getenv($clave);
    if ($valor === false || $valor === '') {
        return $porDefecto;
    }
    return $valor;
}

return [
    'bd' => [
        'host'     => entorno('KERICO_DB_HOST', '127.0.0.1'),
        'puerto'   => (int) entorno('KERICO_DB_PORT', '3306'),
        'nombre'   => entorno('KERICO_DB_NAME', 'kerico'),
        'usuario'  => entorno('KERICO_DB_USER', 'root'),
        'password' => entorno('KERICO_DB_PASS', ''),
        'charset'  => 'utf8mb4',
    ],
    // Sin KERICO_SMTP_HOST el sistema no envía correos. Puerto 465: TLS directo; otros: STARTTLS
    'correo' => [
        'host'      => entorno('KERICO_SMTP_HOST', ''),
        'puerto'    => (int) entorno('KERICO_SMTP_PORT', '587'),
        'usuario'   => entorno('KERICO_SMTP_USER', ''),
        'password'  => entorno('KERICO_SMTP_PASS', ''),
        'remitente' => entorno('KERICO_SMTP_FROM', ''),
    ],
    'app' => [
        'nombre'          => 'Sistema de Registro e Inventario Ke-Rico!',
        'version'         => '1.0.0',
        'zona_horaria'    => 'America/Bogota',
        'origenes'        => ['http://localhost:5173', 'http://127.0.0.1:5173', 'http://localhost', 'http://127.0.0.1'],
        'depuracion'      => entorno('KERICO_DEBUG', '0') === '1',
        'ruta_respaldos'  => __DIR__ . '/../respaldos',
    ],
];
