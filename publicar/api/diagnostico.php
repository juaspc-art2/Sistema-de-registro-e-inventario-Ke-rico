<?php

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');

$config = require __DIR__ . '/config/config.php';

$resultados = [];

function revisar(array &$lista, string $nombre, bool $ok, string $detalle, bool $critico = true): void
{
    $lista[] = ['nombre' => $nombre, 'ok' => $ok, 'detalle' => $detalle, 'critico' => $critico];
}

revisar(
    $resultados,
    'Versión de PHP',
    version_compare(PHP_VERSION, '8.1.0', '>='),
    PHP_VERSION . ' (se requiere 8.1 o superior)'
);

foreach (['pdo_mysql', 'mbstring', 'json', 'fileinfo'] as $ext) {
    revisar($resultados, 'Extensión ' . $ext, extension_loaded($ext), extension_loaded($ext) ? 'cargada' : 'ausente en php.ini');
}

revisar(
    $resultados,
    'Extensión zip',
    extension_loaded('zip'),
    extension_loaded('zip') ? 'cargada' : 'ausente: la exportación a Excel no funcionará',
    false
);

$reescritura = function_exists('apache_get_modules') ? in_array('mod_rewrite', apache_get_modules(), true) : null;
revisar(
    $resultados,
    'mod_rewrite',
    $reescritura !== false,
    $reescritura === null ? 'no se pudo comprobar desde PHP' : ($reescritura ? 'activo' : 'inactivo en httpd.conf'),
    $reescritura === false
);

$respaldos = $config['app']['ruta_respaldos'];
revisar(
    $resultados,
    'Carpeta de respaldos',
    is_dir($respaldos) && is_writable($respaldos),
    is_dir($respaldos) ? ($respaldos . ' (escritura ' . (is_writable($respaldos) ? 'permitida' : 'denegada') . ')') : 'no existe: ' . $respaldos,
    false
);

$bd = $config['bd'];
$conexion = null;
$errorBd = '';
try {
    $conexion = new PDO(
        'mysql:host=' . $bd['host'] . ';port=' . $bd['puerto'] . ';dbname=' . $bd['nombre'] . ';charset=' . $bd['charset'],
        $bd['usuario'],
        $bd['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (Throwable $e) {
    $errorBd = $e->getMessage();
}

revisar(
    $resultados,
    'Conexión a MySQL',
    $conexion !== null,
    $conexion !== null
        ? ('base "' . $bd['nombre'] . '" en ' . $bd['host'] . ':' . $bd['puerto'] . ' como ' . $bd['usuario'])
        : $errorBd
);

$TABLAS = [
    'roles', 'permisos', 'rol_permisos', 'usuarios', 'sesiones', 'configuracion',
    'categorias', 'proveedores', 'productos', 'clientes', 'promociones', 'ventas',
    'venta_detalle', 'descuentos_aplicados', 'comprobantes', 'compras', 'compra_detalle',
    'movimientos_inventario', 'alertas_stock', 'bitacora', 'respaldos',
];
$VISTAS = ['v_estado_inventario', 'v_rentabilidad_producto', 'v_ventas_diarias', 'v_rentabilidad_categoria'];

if ($conexion !== null) {
    $existentes = [];
    foreach ($conexion->query('SHOW TABLES') as $fila) {
        $existentes[] = (string) array_values($fila)[0];
    }
    $faltanTablas = array_values(array_diff($TABLAS, $existentes));
    $faltanVistas = array_values(array_diff($VISTAS, $existentes));

    revisar(
        $resultados,
        'Tablas',
        $faltanTablas === [],
        $faltanTablas === [] ? (count($TABLAS) . ' presentes') : ('faltan: ' . implode(', ', $faltanTablas))
    );
    revisar(
        $resultados,
        'Vistas',
        $faltanVistas === [],
        $faltanVistas === [] ? (count($VISTAS) . ' presentes') : ('faltan: ' . implode(', ', $faltanVistas))
    );

    if ($faltanTablas === []) {
        $usuarios = (int) $conexion->query('SELECT COUNT(*) FROM usuarios')->fetchColumn();
        $productos = (int) $conexion->query('SELECT COUNT(*) FROM productos')->fetchColumn();
        revisar(
            $resultados,
            'Datos de arranque',
            $usuarios > 0,
            $usuarios > 0
                ? ($usuarios . ' usuarios, ' . $productos . ' productos')
                : 'la base no tiene datos: importe database/kerico_seed.sql'
        );

        $juego = (string) $conexion->query("SELECT @@character_set_database")->fetchColumn();
        revisar(
            $resultados,
            'Juego de caracteres',
            str_starts_with($juego, 'utf8mb4'),
            $juego . ($juego === 'utf8mb4' ? '' : ' (se requiere utf8mb4 para las tildes y la eñe)')
        );
    }
}

$fallosCriticos = 0;
$advertencias = 0;
foreach ($resultados as $r) {
    if (!$r['ok']) {
        if ($r['critico']) {
            $fallosCriticos++;
        } else {
            $advertencias++;
        }
    }
}

http_response_code($fallosCriticos > 0 ? 500 : 200);

$titulo = $fallosCriticos > 0
    ? 'El sistema no puede funcionar todavía'
    : ($advertencias > 0 ? 'El sistema funciona, con observaciones' : 'Todo listo');
$tono = $fallosCriticos > 0 ? '#c0392b' : ($advertencias > 0 ? '#b7791f' : '#2f855a');

?><!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Diagnóstico · Ke-Rico!</title>
<style>
body{margin:0;background:#FFF8F5;color:#1E1B18;font:15px/1.6 "Segoe UI",system-ui,sans-serif;padding:24px}
main{max-width:760px;margin:0 auto}
h1{font-size:22px;margin:0 0 4px;color:<?= $tono ?>}
p.sub{margin:0 0 20px;color:#8D7166}
table{width:100%;border-collapse:collapse;background:#fff;border:1px solid #E1BFB3;border-radius:10px;overflow:hidden}
th,td{padding:10px 12px;text-align:left;border-bottom:1px solid #F0E2DA;vertical-align:top}
th{background:#E1BFB3;font-size:12px;letter-spacing:.06em;text-transform:uppercase}
tr:last-child td{border-bottom:none}
td.estado{width:90px;font-weight:700}
.ok{color:#2f855a}.mal{color:#c0392b}.aviso{color:#b7791f}
td.detalle{color:#8D7166;font-size:13.5px;word-break:break-word}
footer{margin-top:18px;font-size:13px;color:#8D7166}
code{background:#F0E2DA;padding:1px 5px;border-radius:4px}
</style>
</head>
<body>
<main>
<h1><?= htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8') ?></h1>
<p class="sub">Revisión del entorno para el Sistema de Registro e Inventario Ke-Rico!</p>
<table>
<thead><tr><th>Estado</th><th>Comprobación</th><th>Detalle</th></tr></thead>
<tbody>
<?php foreach ($resultados as $r): ?>
<tr>
<td class="estado <?= $r['ok'] ? 'ok' : ($r['critico'] ? 'mal' : 'aviso') ?>"><?= $r['ok'] ? 'OK' : ($r['critico'] ? 'FALLA' : 'AVISO') ?></td>
<td><?= htmlspecialchars($r['nombre'], ENT_QUOTES, 'UTF-8') ?></td>
<td class="detalle"><?= htmlspecialchars($r['detalle'], ENT_QUOTES, 'UTF-8') ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<footer>
<?php if ($fallosCriticos > 0): ?>
Corrija las filas en rojo y recargue. Si falta la base o está vacía, importe <code>database/kerico_seed.sql</code>.
<?php else: ?>
Abra <code>../index.html</code> para entrar al sistema.
<?php endif; ?>
</footer>
</main>
</body>
</html>
