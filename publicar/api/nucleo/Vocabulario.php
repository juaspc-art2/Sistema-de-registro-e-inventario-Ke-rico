<?php

declare(strict_types=1);

namespace Kerico\Nucleo;

final class Vocabulario
{
    private const MODULOS = [
        'Autenticacion' => 'Autenticación',
    ];

    private const ACCIONES = [
        'ACCESO_DENEGADO'          => 'Acceso denegado',
        'ALERTA_REVISAR'           => 'Alerta revisada',
        'ALERTA_STOCK'             => 'Alerta de stock',
        'CLIENTE_CREAR'            => 'Cliente creado',
        'COMPRA_RECIBIR'           => 'Compra recibida',
        'COMPRA_REGISTRAR'         => 'Compra registrada',
        'COMPROBANTE_EMITIR'       => 'Comprobante emitido',
        'CONFIGURACION_ACTUALIZAR' => 'Configuración actualizada',
        'CSRF_RECHAZADO'           => 'Token CSRF rechazado',
        'DESCUENTO_APLICAR'        => 'Descuento aplicado',
        'INSTALACION'              => 'Instalación',
        'INVENTARIO_AJUSTE'        => 'Ajuste de inventario',
        'INVENTARIO_CARGA'         => 'Carga de inventario',
        'INVENTARIO_DEVOLUCION'    => 'Devolución al inventario',
        'INVENTARIO_ENTRADA'       => 'Entrada de inventario',
        'INVENTARIO_MERMA'         => 'Merma de inventario',
        'INVENTARIO_SALIDA'        => 'Salida de inventario',
        'LOGIN'                    => 'Inicio de sesión',
        'LOGIN_FALLIDO'            => 'Inicio de sesión fallido',
        'PASSWORD_CAMBIAR'         => 'Contraseña cambiada',
        'PASSWORD_RECUPERAR'       => 'Recuperación de contraseña',
        'PASSWORD_RESTABLECER'     => 'Contraseña restablecida',
        'PRECIO_MODIFICAR'         => 'Precio modificado',
        'PRODUCTO_ACTUALIZAR'      => 'Producto actualizado',
        'PRODUCTO_CREAR'           => 'Producto creado',
        'PRODUCTO_ELIMINAR'        => 'Producto eliminado',
        'PRODUCTO_RESTAURAR'       => 'Producto restaurado',
        'PROMOCION_ACTUALIZAR'     => 'Promoción actualizada',
        'PROMOCION_CREAR'          => 'Promoción creada',
        'PROMOCION_ELIMINAR'       => 'Promoción eliminada',
        'PROMOCION_ESTADO'         => 'Estado de promoción',
        'PROMOCION_RESTAURAR'      => 'Promoción restaurada',
        'PROVEEDOR_ACTUALIZAR'     => 'Proveedor actualizado',
        'PROVEEDOR_CREAR'          => 'Proveedor creado',
        'PROVEEDOR_ELIMINAR'       => 'Proveedor eliminado',
        'PROVEEDOR_ESTADO'         => 'Estado de proveedor',
        'PROVEEDOR_RESTAURAR'      => 'Proveedor restaurado',
        'REORDEN_CONFIGURAR'       => 'Punto de reorden configurado',
        'REPORTE_EXPORTAR'         => 'Reporte exportado',
        'REPORTE_GENERAR'          => 'Reporte generado',
        'RESPALDO'                 => 'Respaldo generado',
        'SESION_EXPIRADA'          => 'Sesión expirada',
        'USUARIO_ACTUALIZAR'       => 'Usuario actualizado',
        'USUARIO_CREAR'            => 'Usuario creado',
        'USUARIO_DESBLOQUEAR'      => 'Usuario desbloqueado',
        'USUARIO_ELIMINAR'         => 'Usuario eliminado',
        'USUARIO_RESTAURAR'        => 'Usuario restaurado',
        'VENTA_ANULAR'             => 'Venta anulada',
        'VENTA_REGISTRAR'          => 'Venta registrada',
    ];

    public static function modulo(string $valor): string
    {
        return self::MODULOS[$valor] ?? $valor;
    }

    public static function accion(string $valor): string
    {
        if (isset(self::ACCIONES[$valor])) {
            return self::ACCIONES[$valor];
        }
        $texto = str_replace('_', ' ', mb_strtolower($valor));
        return $texto === '' ? '' : mb_strtoupper(mb_substr($texto, 0, 1)) . mb_substr($texto, 1);
    }
}
