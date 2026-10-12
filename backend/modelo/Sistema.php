<?php

declare(strict_types=1);

namespace Kerico\Modelo;

use Kerico\Nucleo\Ajustes;
use Kerico\Nucleo\Autenticacion;
use Kerico\Nucleo\Bd;
use Kerico\Nucleo\Bitacora;
use Kerico\Nucleo\ErrorHttp;
use Kerico\Nucleo\Exportador;
use Kerico\Nucleo\Paginador;
use Kerico\Nucleo\Validador;
use Kerico\Nucleo\Vocabulario;
use PDO;

final class Sistema
{
    // La tabla respaldos no se copia: es la lista de las propias copias, y al copiarse a sí misma
    // la que estaba en curso quedaba para siempre "En proceso" después de restaurar
    private const TABLAS = [
        'roles', 'permisos', 'rol_permisos', 'usuarios', 'configuracion', 'categorias',
        'proveedores', 'productos', 'clientes', 'promociones', 'ventas', 'venta_detalle',
        'descuentos_aplicados', 'comprobantes', 'compras', 'compra_detalle',
        'movimientos_inventario', 'alertas_stock', 'bitacora',
    ];

    // Días que un archivo de respaldo se conserva en disco
    private const DIAS_RESPALDO = 30;

    private const CARPETA_RESPALDOS = 'respaldos';

    private const ORDEN_BITACORA = [
        'fecha'   => 'creado_en',
        'usuario' => 'usuario_nombre',
        'modulo'  => 'modulo',
        'accion'  => 'accion',
        'nivel'   => 'nivel',
    ];

    private static function filtrarBitacora(array $filtros): array
    {
        $sql = ' FROM bitacora WHERE 1 = 1';
        $parametros = [];

        if (!empty($filtros['busqueda'])) {
            $sql .= ' AND (descripcion LIKE ? OR usuario_nombre LIKE ? OR accion LIKE ?)';
            $termino = '%' . $filtros['busqueda'] . '%';
            $parametros[] = $termino;
            $parametros[] = $termino;
            $parametros[] = $termino;
        }
        if (!empty($filtros['nivel'])) {
            $sql .= ' AND nivel = ?';
            $parametros[] = $filtros['nivel'];
        }
        if (!empty($filtros['modulo'])) {
            $sql .= ' AND modulo = ?';
            $parametros[] = $filtros['modulo'];
        }
        if (!empty($filtros['usuario_id'])) {
            $sql .= ' AND usuario_id = ?';
            $parametros[] = (int) $filtros['usuario_id'];
        }
        if (!empty($filtros['desde'])) {
            $sql .= ' AND creado_en >= ?';
            $parametros[] = $filtros['desde'] . ' 00:00:00';
        }
        if (!empty($filtros['hasta'])) {
            $sql .= ' AND creado_en <= ?';
            $parametros[] = $filtros['hasta'] . ' 23:59:59';
        }

        return [$sql, $parametros];
    }

    public static function paginarBitacora(array $filtros = []): array
    {
        [$desde, $parametros] = self::filtrarBitacora($filtros);
        return Paginador::consultar(
            'SELECT *',
            $desde,
            $parametros,
            $filtros,
            self::ORDEN_BITACORA,
            'fecha',
            [self::class, 'normalizarBitacora']
        );
    }

    public static function normalizarBitacora(array $b): array
    {
        return [
            'id'          => (int) $b['id'],
            'usuario_id'  => $b['usuario_id'] === null ? null : (int) $b['usuario_id'],
            'usuario'     => (string) $b['usuario_nombre'],
            'accion'        => (string) $b['accion'],
            'accion_nombre' => Vocabulario::accion((string) $b['accion']),
            'modulo'        => (string) $b['modulo'],
            'modulo_nombre' => Vocabulario::modulo((string) $b['modulo']),
            'descripcion' => (string) $b['descripcion'],
            'nivel'       => (string) $b['nivel'],
            'entidad'     => (string) $b['entidad'],
            'entidad_id'  => (string) $b['entidad_id'],
            'ip'          => (string) $b['ip'],
            'fecha'       => (string) $b['creado_en'],
        ];
    }

    public static function bitacora(array $filtros = []): array
    {
        [$desde, $parametros] = self::filtrarBitacora($filtros);
        $sql = 'SELECT *' . $desde . ' ORDER BY creado_en DESC, id DESC LIMIT ' . (Exportador::MAXIMO_FILAS + 1);

        return array_map([self::class, 'normalizarBitacora'], Bd::consultar($sql, $parametros));
    }

    public static function resumenBitacora(): array
    {
        $hoy = date('Y-m-d');
        $filas = Bd::consultar(
            'SELECT nivel, COUNT(*) AS n FROM bitacora WHERE creado_en >= ? AND creado_en < ? + INTERVAL 1 DAY GROUP BY nivel',
            [$hoy, $hoy]
        );

        $resumen = ['Info' => 0, 'Advertencia' => 0, 'Error' => 0, 'Sistema' => 0];
        foreach ($filas as $fila) {
            $resumen[$fila['nivel']] = (int) $fila['n'];
        }

        return [
            'eventos_hoy'      => array_sum($resumen),
            'niveles'          => $resumen,
            'usuarios_activos' => (int) Bd::valor('SELECT COUNT(DISTINCT usuario_id) FROM sesiones WHERE cerrada_en IS NULL'),
            'modulos'          => array_map(
                static fn (array $m): string => (string) $m['modulo'],
                Bd::consultar('SELECT DISTINCT modulo FROM bitacora ORDER BY modulo')
            ),
        ];
    }

    public static function respaldos(): array
    {
        return array_map(static fn (array $r): array => [
            'id'             => (int) $r['id'],
            'nombre_archivo' => (string) $r['nombre_archivo'],
            'tamano_bytes'   => (int) $r['tamano_bytes'],
            'tipo'           => (string) $r['tipo'],
            'estado'         => (string) $r['estado'],
            'iniciado_en'    => (string) $r['iniciado_en'],
            'finalizado_en'  => $r['finalizado_en'],
            'mensaje'        => (string) $r['mensaje'],
        ], Bd::consultar('SELECT * FROM respaldos ORDER BY iniciado_en DESC LIMIT 60'));
    }

    public static function respaldoPendiente(): bool
    {
        $horas = (int) Ajustes::obtener('respaldo_intervalo_horas', 24);
        $ultimo = Bd::valor(
            "SELECT MAX(finalizado_en) FROM respaldos WHERE estado = 'Completado'"
        );
        if ($ultimo === null) {
            return true;
        }
        return (time() - strtotime((string) $ultimo)) >= $horas * 3600;
    }

    public static function generarRespaldo(string $tipo = 'Manual', ?array $usuario = null): array
    {
        $rutaBase = __DIR__ . '/../' . self::CARPETA_RESPALDOS;
        if (!is_dir($rutaBase) && !mkdir($rutaBase, 0775, true) && !is_dir($rutaBase)) {
            throw new ErrorHttp('No fue posible crear la carpeta de respaldos', 500);
        }

        $nombre = 'kerico-' . date('Ymd-His') . '.sql';
        $ruta = $rutaBase . '/' . $nombre;

        $respaldoId = Bd::insertar(
            'INSERT INTO respaldos (nombre_archivo, ruta, tipo, estado) VALUES (?,?,?,?)',
            [$nombre, self::CARPETA_RESPALDOS . '/' . $nombre, $tipo, 'En proceso']
        );

        try {
            $escritos = self::volcado($ruta);

            Bd::ejecutar(
                "UPDATE respaldos SET estado = 'Completado', finalizado_en = NOW(), tamano_bytes = ?, mensaje = ?
                 WHERE id = ?",
                [$escritos, 'Respaldo ' . strtolower($tipo) . ' generado correctamente', $respaldoId]
            );

            Bitacora::registrar(
                'RESPALDO',
                'Sistema',
                'Respaldo ' . strtolower($tipo) . ' de la base de datos completado: ' . $nombre,
                'Sistema',
                'respaldos',
                $respaldoId,
                null,
                null,
                $usuario
            );
        } catch (\Throwable $e) {
            // Un volcado a medias vacía las tablas al restaurarlo: no puede quedar en disco
            if (is_file($ruta)) {
                unlink($ruta);
            }
            Bd::ejecutar(
                "UPDATE respaldos SET estado = 'Fallido', finalizado_en = NOW(), mensaje = ? WHERE id = ?",
                [mb_substr($e->getMessage(), 0, 255), $respaldoId]
            );
            throw $e;
        }

        // Los archivos viejos se borran; su fila sigue en la lista como historial
        foreach (glob($rutaBase . '/kerico-*.sql') ?: [] as $viejo) {
            if (filemtime($viejo) < time() - self::DIAS_RESPALDO * 86400) {
                @unlink($viejo);
            }
        }

        $fila = Bd::primero('SELECT * FROM respaldos WHERE id = ?', [$respaldoId]);
        return [
            'id'             => (int) $fila['id'],
            'nombre_archivo' => (string) $fila['nombre_archivo'],
            'tamano_bytes'   => (int) $fila['tamano_bytes'],
            'tipo'           => (string) $fila['tipo'],
            'estado'         => (string) $fila['estado'],
            'iniciado_en'    => (string) $fila['iniciado_en'],
            'finalizado_en'  => $fila['finalizado_en'],
            'mensaje'        => (string) $fila['mensaje'],
        ];
    }

    // Escribe el volcado fila a fila, así la memoria no crece con el tamaño de la base.
    // Todo se lee en una sola transacción: la copia es coherente aunque entren ventas mientras tanto.
    private static function volcado(string $ruta): int
    {
        $archivo = fopen($ruta, 'wb');
        if ($archivo === false) {
            throw new ErrorHttp('No fue posible escribir el archivo de respaldo', 500);
        }

        $bytes = 0;
        $escribir = static function (string $texto) use ($archivo, &$bytes): void {
            $escritos = fwrite($archivo, $texto);
            if ($escritos !== strlen($texto)) {
                throw new ErrorHttp('No fue posible escribir el archivo de respaldo', 500);
            }
            $bytes += $escritos;
        };

        $pdo = Bd::conexion();
        $pdo->beginTransaction();
        $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
        $sentencia = null;

        try {
            $escribir("SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n");
            foreach (self::TABLAS as $tabla) {
                $escribir('TRUNCATE TABLE `' . $tabla . "`;\n");
            }
            $escribir("\n");

            foreach (self::TABLAS as $tabla) {
                $sentencia = $pdo->query('SELECT * FROM `' . $tabla . '`');
                $encabezado = '';
                $valores = [];

                foreach ($sentencia as $fila) {
                    if ($encabezado === '') {
                        $encabezado = 'INSERT INTO `' . $tabla . '` (`' . implode('`, `', array_keys($fila)) . "`) VALUES\n";
                    }
                    $celdas = [];
                    foreach ($fila as $valor) {
                        if ($valor === null) {
                            $celdas[] = 'NULL';
                        } elseif (is_int($valor) || is_float($valor)) {
                            $celdas[] = (string) $valor;
                        } else {
                            $celdas[] = $pdo->quote((string) $valor);
                        }
                    }
                    $valores[] = '(' . implode(',', $celdas) . ')';

                    if (count($valores) === 100) {
                        $escribir($encabezado . implode(",\n", $valores) . ";\n");
                        $valores = [];
                    }
                }
                $sentencia = null;

                if ($valores !== []) {
                    $escribir($encabezado . implode(",\n", $valores) . ";\n");
                }
                if ($encabezado !== '') {
                    $escribir("\n");
                }
            }

            $escribir("SET FOREIGN_KEY_CHECKS = 1;\n");
            return $bytes;
        } finally {
            if ($sentencia !== null) {
                $sentencia->closeCursor();
            }
            $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
            $pdo->rollBack();
            fclose($archivo);
        }
    }

    public static function descargarRespaldo(int $id): array
    {
        $fila = Bd::primero('SELECT * FROM respaldos WHERE id = ?', [$id]);
        if ($fila === null) {
            throw ErrorHttp::noEncontrado('El respaldo no existe');
        }

        $ruta = realpath(__DIR__ . '/../' . $fila['ruta']);
        $base = realpath(__DIR__ . '/..');
        if ($ruta === false || $base === false || !str_starts_with($ruta, $base) || !is_file($ruta)) {
            throw ErrorHttp::noEncontrado('El archivo del respaldo ya no está disponible');
        }

        return ['nombre' => (string) $fila['nombre_archivo'], 'ruta' => $ruta];
    }

    public static function estado(): array
    {
        return [
            'version'             => '1.0.0',
            'php'                 => PHP_VERSION,
            'bd'                  => (string) Bd::valor('SELECT VERSION()'),
            'zona_horaria'        => date_default_timezone_get(),
            'hora_servidor'       => date('c'),
            'sesiones_activas'    => (int) Bd::valor('SELECT COUNT(*) FROM sesiones WHERE cerrada_en IS NULL'),
            'respaldo_pendiente'  => self::respaldoPendiente(),
            'ultimo_respaldo'     => Bd::valor("SELECT MAX(finalizado_en) FROM respaldos WHERE estado = 'Completado'"),
            'minutos_inactividad' => (int) Ajustes::obtener('sesion_minutos_inactividad', 15),
        ];
    }

    // Ajustes de texto que no son texto libre, con el formato de Validador que deben cumplir
    private const FORMATO_AJUSTE = ['empresa_telefono' => 'telefono', 'empresa_nit' => 'NIT', 'comprobante_prefijo' => 'codigo'];
    // Sin ellos el comprobante sale sin nombre de empresa o sin numeración
    private const AJUSTES_OBLIGATORIOS = ['empresa_nombre', 'comprobante_prefijo'];

    public static function actualizarConfiguracion(array $valores): array
    {
        $actuales = array_column(Ajustes::todos(), null, 'clave');
        $cambios = [];

        foreach ($valores as $clave => $valor) {
            if (!isset($actuales[$clave]) || !is_scalar($valor)) {
                continue;
            }
            $valor = trim((string) $valor);
            // Todos los ajustes enteros son conteos o duraciones: un cero deja sin sesión o sin consecutivo al sistema
            if ($actuales[$clave]['tipo'] === 'entero' && preg_match('/^[1-9]\d{0,8}$/', $valor) !== 1) {
                $etiqueta = $actuales[$clave]['descripcion'] ?: $clave;
                throw ErrorHttp::validacion(
                    $etiqueta . ': debe ser un número entero mayor que cero',
                    [$clave => 'Debe ser un número entero mayor que cero']
                );
            }
            $formato = self::FORMATO_AJUSTE[$clave] ?? null;
            // El prefijo va delante de cada número de comprobante, que tiene 30 caracteres en total
            $tope = $clave === 'comprobante_prefijo' ? 6 : 255;
            $falta = match (true) {
                $valor === ''               => in_array($clave, self::AJUSTES_OBLIGATORIOS, true) ? 'es obligatorio' : null,
                mb_strlen($valor) > $tope   => 'no puede superar ' . $tope . ' caracteres',
                $formato !== null           => Validador::faltaFormato($formato, $valor),
                $clave === 'empresa_correo' => filter_var($valor, FILTER_VALIDATE_EMAIL) === false ? 'no tiene un formato válido' : null,
                default                     => null,
            };
            if ($falta !== null) {
                $etiqueta = $actuales[$clave]['descripcion'] ?: $clave;
                throw ErrorHttp::validacion($etiqueta . ': ' . $falta, [$clave => $falta]);
            }
            if ($clave === 'notificaciones_correo') {
                foreach (preg_split('/[\s,;]+/', $valor, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $direccion) {
                    if (filter_var($direccion, FILTER_VALIDATE_EMAIL) === false) {
                        throw ErrorHttp::validacion(
                            'El correo de notificaciones ' . $direccion . ' no tiene un formato válido',
                            [$clave => 'Formato inválido']
                        );
                    }
                }
            }
            $cambios[(string) $clave] = $valor;
        }

        Bd::transaccion(static function () use ($cambios): void {
            if (isset($cambios['comprobante_consecutivo']) || isset($cambios['comprobante_prefijo'])) {
                $fila = Bd::primero("SELECT valor FROM configuracion WHERE clave = 'comprobante_consecutivo' FOR UPDATE");
                $prefijo = $cambios['comprobante_prefijo'] ?? (string) Ajustes::obtener('comprobante_prefijo', 'KR');
                $consecutivo = (int) ($cambios['comprobante_consecutivo'] ?? $fila['valor'] ?? 1);
                $usado = (int) Bd::valor('SELECT COALESCE(MAX(consecutivo), 0) FROM comprobantes WHERE prefijo = ?', [$prefijo]);
                if ($consecutivo <= $usado) {
                    throw ErrorHttp::validacion(
                        'El consecutivo debe ser mayor que ' . $usado . ', el último comprobante emitido con el prefijo ' . $prefijo,
                        ['comprobante_consecutivo' => 'Debe ser mayor que ' . $usado]
                    );
                }
            }
            foreach ($cambios as $clave => $valor) {
                Ajustes::definir($clave, $valor);
            }
        });

        Bitacora::registrar(
            'CONFIGURACION_ACTUALIZAR',
            'Sistema',
            'Configuración del sistema actualizada',
            'Advertencia',
            'configuracion',
            '',
            null,
            $cambios
        );

        return Ajustes::todos();
    }
}
