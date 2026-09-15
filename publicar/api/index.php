<?php

declare(strict_types=1);

use Kerico\Controlador\AutenticacionControlador;
use Kerico\Controlador\ComprobantesControlador;
use Kerico\Controlador\DescuentosControlador;
use Kerico\Controlador\InventarioControlador;
use Kerico\Controlador\ProveedoresControlador;
use Kerico\Controlador\ReportesControlador;
use Kerico\Controlador\SistemaControlador;
use Kerico\Controlador\VentasControlador;
use Kerico\Nucleo\Autenticacion;
use Kerico\Nucleo\Bd;
use Kerico\Nucleo\Bitacora;
use Kerico\Nucleo\Enrutador;
use Kerico\Nucleo\ErrorHttp;
use Kerico\Nucleo\Peticion;
use Kerico\Nucleo\Respuesta;

$config = require __DIR__ . '/config/config.php';

date_default_timezone_set($config['app']['zona_horaria']);
error_reporting($config['app']['depuracion'] ? E_ALL : 0);
ini_set('display_errors', $config['app']['depuracion'] ? '1' : '0');

spl_autoload_register(static function (string $clase): void {
    $prefijo = 'Kerico\\';
    if (!str_starts_with($clase, $prefijo)) {
        return;
    }
    $relativa = substr($clase, strlen($prefijo));
    $mapa = ['Nucleo' => 'nucleo', 'Modelo' => 'modelo', 'Controlador' => 'controlador'];
    $partes = explode('\\', $relativa);
    $carpeta = $mapa[$partes[0]] ?? strtolower($partes[0]);
    $archivo = __DIR__ . '/' . $carpeta . '/' . end($partes) . '.php';
    if (is_file($archivo)) {
        require $archivo;
    }
});

Bd::configurar($config['bd']);

ob_start();

$peticion = new Peticion();
Bitacora::contexto($peticion->ip(), $peticion->agente());

$origen = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origen !== '' && in_array($origen, $config['app']['origenes'], true)) {
    header('Access-Control-Allow-Origin: ' . $origen);
    header('Vary: Origin');
} else {
    header('Access-Control-Allow-Origin: *');
}
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Token-Kerico, X-CSRF-Kerico');
header('Access-Control-Expose-Headers: X-Nombre-Archivo');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');

if ($peticion->metodo() === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$enrutador = new Enrutador();

$enrutador->get('/', static function (): void {
    Respuesta::exito([
        'nombre'  => 'API del Sistema de Registro e Inventario Ke-Rico!',
        'version' => '1.0.0',
        'estado'  => 'en línea',
    ]);
}, true);

$enrutador->post('/auth/login', [AutenticacionControlador::class, 'ingresar'], true);
$enrutador->post('/auth/recuperar', [AutenticacionControlador::class, 'recuperar'], true);
$enrutador->post('/auth/restablecer', [AutenticacionControlador::class, 'restablecer'], true);
$enrutador->post('/auth/logout', [AutenticacionControlador::class, 'salir']);
$enrutador->get('/auth/sesion', [AutenticacionControlador::class, 'sesion']);
$enrutador->post('/auth/password', [AutenticacionControlador::class, 'cambiarPassword']);

$enrutador->get('/ventas', [VentasControlador::class, 'listar']);
$enrutador->get('/ventas/catalogo', [VentasControlador::class, 'catalogo']);
$enrutador->get('/ventas/resumen', [VentasControlador::class, 'resumen']);
$enrutador->get('/ventas/exportar', [VentasControlador::class, 'exportar']);
$enrutador->get('/ventas/clientes', [VentasControlador::class, 'clientes']);
$enrutador->post('/ventas/clientes', [VentasControlador::class, 'crearCliente']);
$enrutador->get('/ventas/{id}', [VentasControlador::class, 'ver']);
$enrutador->post('/ventas', [VentasControlador::class, 'registrar']);
$enrutador->post('/ventas/{id}/anular', [VentasControlador::class, 'anular']);

$enrutador->get('/comprobantes', [ComprobantesControlador::class, 'listar']);
$enrutador->get('/comprobantes/exportar', [ComprobantesControlador::class, 'exportar']);
$enrutador->get('/comprobantes/{id}', [ComprobantesControlador::class, 'ver']);
$enrutador->get('/comprobantes/{id}/descargar', [ComprobantesControlador::class, 'descargar']);
$enrutador->post('/comprobantes', [ComprobantesControlador::class, 'emitir']);

$enrutador->get('/descuentos', [DescuentosControlador::class, 'listar']);
$enrutador->get('/descuentos/historial', [DescuentosControlador::class, 'historial']);
$enrutador->get('/descuentos/exportar', [DescuentosControlador::class, 'exportar']);
$enrutador->get('/descuentos/{id}', [DescuentosControlador::class, 'ver']);
$enrutador->post('/descuentos', [DescuentosControlador::class, 'crear']);
$enrutador->post('/descuentos/simular', [DescuentosControlador::class, 'simular']);
$enrutador->put('/descuentos/{id}', [DescuentosControlador::class, 'actualizar']);
$enrutador->post('/descuentos/{id}/alternar', [DescuentosControlador::class, 'alternar']);
$enrutador->delete('/descuentos/{id}', [DescuentosControlador::class, 'eliminar']);
$enrutador->post('/descuentos/{id}/restaurar', [DescuentosControlador::class, 'restaurar']);

$enrutador->get('/inventario/productos', [InventarioControlador::class, 'productos']);
$enrutador->get('/inventario/categorias', [InventarioControlador::class, 'categorias']);
$enrutador->get('/inventario/movimientos', [InventarioControlador::class, 'movimientos']);
$enrutador->get('/inventario/movimientos/resumen', [InventarioControlador::class, 'resumenMovimientos']);
$enrutador->get('/inventario/movimientos/exportar', [InventarioControlador::class, 'exportarMovimientos']);
$enrutador->get('/inventario/exportar', [InventarioControlador::class, 'exportarInventario']);
$enrutador->get('/inventario/alertas', [InventarioControlador::class, 'alertas']);
$enrutador->post('/inventario/alertas/vistas', [InventarioControlador::class, 'marcarTodasAlertas']);
$enrutador->post('/inventario/alertas/recalcular', [InventarioControlador::class, 'recalcularAlertas']);
$enrutador->post('/inventario/alertas/{id}/vista', [InventarioControlador::class, 'marcarAlerta']);
$enrutador->get('/inventario/papelera', [InventarioControlador::class, 'papeleraProductos']);
$enrutador->get('/inventario/productos/{id}', [InventarioControlador::class, 'producto']);
$enrutador->post('/inventario/productos', [InventarioControlador::class, 'crearProducto']);
$enrutador->put('/inventario/productos/{id}', [InventarioControlador::class, 'actualizarProducto']);
$enrutador->delete('/inventario/productos/{id}', [InventarioControlador::class, 'eliminarProducto']);
$enrutador->put('/inventario/productos/{id}/reorden', [InventarioControlador::class, 'definirReorden']);
$enrutador->post('/inventario/productos/{id}/restaurar', [InventarioControlador::class, 'restaurarProducto']);
$enrutador->post('/inventario/movimientos', [InventarioControlador::class, 'registrarMovimiento']);

$enrutador->get('/proveedores', [ProveedoresControlador::class, 'listar']);
$enrutador->get('/proveedores/exportar', [ProveedoresControlador::class, 'exportar']);
$enrutador->get('/proveedores/compras', [ProveedoresControlador::class, 'compras']);
$enrutador->post('/proveedores/compras', [ProveedoresControlador::class, 'registrarCompra']);
$enrutador->get('/proveedores/compras/{id}', [ProveedoresControlador::class, 'verCompra']);
$enrutador->post('/proveedores/compras/{id}/recibir', [ProveedoresControlador::class, 'recibirCompra']);
$enrutador->get('/proveedores/papelera', [ProveedoresControlador::class, 'papelera']);
$enrutador->get('/proveedores/{id}', [ProveedoresControlador::class, 'ver']);
$enrutador->post('/proveedores', [ProveedoresControlador::class, 'crear']);
$enrutador->put('/proveedores/{id}', [ProveedoresControlador::class, 'actualizar']);
$enrutador->delete('/proveedores/{id}', [ProveedoresControlador::class, 'eliminar']);
$enrutador->post('/proveedores/{id}/restaurar', [ProveedoresControlador::class, 'restaurar']);

$enrutador->get('/reportes/panel', [ReportesControlador::class, 'panel']);
$enrutador->get('/reportes/rentabilidad', [ReportesControlador::class, 'rentabilidad']);
$enrutador->get('/reportes/rentabilidad/exportar', [ReportesControlador::class, 'exportarRentabilidad']);
$enrutador->get('/reportes/inventario', [ReportesControlador::class, 'inventarioValorizado']);

$enrutador->get('/sistema/bitacora', [SistemaControlador::class, 'bitacora']);
$enrutador->get('/sistema/bitacora/exportar', [SistemaControlador::class, 'exportarBitacora']);
$enrutador->get('/sistema/usuarios/papelera', [SistemaControlador::class, 'papeleraUsuarios']);
$enrutador->get('/sistema/usuarios', [SistemaControlador::class, 'usuarios']);
$enrutador->post('/sistema/usuarios', [SistemaControlador::class, 'crearUsuario']);
$enrutador->put('/sistema/usuarios/{id}', [SistemaControlador::class, 'actualizarUsuario']);
$enrutador->post('/sistema/usuarios/{id}/desbloquear', [SistemaControlador::class, 'desbloquearUsuario']);
$enrutador->delete('/sistema/usuarios/{id}', [SistemaControlador::class, 'eliminarUsuario']);
$enrutador->post('/sistema/usuarios/{id}/restaurar', [SistemaControlador::class, 'restaurarUsuario']);
$enrutador->get('/sistema/sesiones', [SistemaControlador::class, 'sesiones']);
$enrutador->get('/sistema/estado', [SistemaControlador::class, 'estado']);
$enrutador->get('/sistema/configuracion', [SistemaControlador::class, 'configuracion']);
$enrutador->put('/sistema/configuracion', [SistemaControlador::class, 'guardarConfiguracion']);
$enrutador->get('/sistema/respaldos', [SistemaControlador::class, 'respaldos']);
$enrutador->post('/sistema/respaldos', [SistemaControlador::class, 'generarRespaldo']);
$enrutador->get('/sistema/respaldos/{id}/descargar', [SistemaControlador::class, 'descargarRespaldo']);

try {
    $enrutador->despachar($peticion);
} catch (ErrorHttp $e) {
    Respuesta::error($e->getMessage(), $e->getCode(), $e->detalles());
} catch (Throwable $e) {
    $mensaje = $config['app']['depuracion']
        ? $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine()
        : 'Ocurrió un error inesperado en el servidor';

    try {
        Bitacora::registrar(
            'ERROR_SERVIDOR',
            'Sistema',
            mb_substr($e->getMessage(), 0, 255),
            'Error',
            'excepcion',
            '',
            null,
            null,
            Autenticacion::usuarioActual()
        );
    } catch (Throwable $ignorado) {
        $mensaje = $mensaje;
    }

    Respuesta::error($mensaje, 500);
}
