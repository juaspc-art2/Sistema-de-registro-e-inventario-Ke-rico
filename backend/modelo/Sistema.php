<?php

declare(strict_types=1);

namespace Kerico\Modelo;

use Kerico\Nucleo\Ajustes;
use Kerico\Nucleo\Autenticacion;
use Kerico\Nucleo\Bd;
use Kerico\Nucleo\Bitacora;
use Kerico\Nucleo\ErrorHttp;
use Kerico\Nucleo\Paginador;
use Kerico\Nucleo\Vocabulario;

final class Sistema
{
    private const TABLAS = [
        'roles', 'permisos', 'rol_permisos', 'usuarios', 'configuracion', 'categorias',
        'proveedores', 'productos', 'clientes', 'promociones', 'ventas', 'venta_detalle',
        'descuentos_aplicados', 'comprobantes', 'compras', 'compra_detalle',
        'movimientos_inventario', 'alertas_stock', 'bitacora', 'respaldos',
    ];

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
        $sql = 'SELECT * FROM bitacora WHERE 1 = 1';
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

        $sql .= ' ORDER BY creado_en DESC, id DESC';
        $limite = (int) ($filtros['limite'] ?? 300);
        $sql .= ' LIMIT ' . max(1, min($limite, 2000));

        return array_map([self::class, 'normalizarBitacora'], Bd::consultar($sql, $parametros));
    }

    public static function resumenBitacora(): array
    {
        $hoy = date('Y-m-d');
        $filas = Bd::consultar(
            'SELECT nivel, COUNT(*) AS n FROM bitacora WHERE DATE(creado_en) = ? GROUP BY nivel',
            [$hoy]
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
        $directorio = (string) Ajustes::obtener('respaldo_directorio', 'respaldos');
        $rutaBase = __DIR__ . '/../' . $directorio;
        if (!is_dir($rutaBase) && !mkdir($rutaBase, 0775, true) && !is_dir($rutaBase)) {
            throw new ErrorHttp('No fue posible crear la carpeta de respaldos', 500);
        }

        $nombre = 'kerico-' . date('Ymd-His') . '.sql';
        $ruta = $rutaBase . '/' . $nombre;

        $respaldoId = Bd::insertar(
            'INSERT INTO respaldos (nombre_archivo, ruta, tipo, estado) VALUES (?,?,?,?)',
            [$nombre, $directorio . '/' . $nombre, $tipo, 'En proceso']
        );

        try {
            $contenido = self::volcado();
            $escritos = file_put_contents($ruta, $contenido);
            if ($escritos === false) {
                throw new ErrorHttp('No fue posible escribir el archivo de respaldo', 500);
            }

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
            Bd::ejecutar(
                "UPDATE respaldos SET estado = 'Fallido', finalizado_en = NOW(), mensaje = ? WHERE id = ?",
                [mb_substr($e->getMessage(), 0, 255), $respaldoId]
            );
            throw $e;
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

    private static function volcado(): string
    {
        $pdo = Bd::conexion();
        $salida = "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n";

        foreach (self::TABLAS as $tabla) {
            $salida .= 'TRUNCATE TABLE `' . $tabla . "`;\n";
        }
        $salida .= "\n";

        foreach (self::TABLAS as $tabla) {
            $filas = Bd::consultar('SELECT * FROM `' . $tabla . '`');
            if ($filas === []) {
                continue;
            }

            $columnas = array_keys($filas[0]);
            $listaColumnas = '`' . implode('`, `', $columnas) . '`';

            $lotes = array_chunk($filas, 100);
            foreach ($lotes as $lote) {
                $valores = [];
                foreach ($lote as $fila) {
                    $celdas = [];
                    foreach ($columnas as $columna) {
                        $valor = $fila[$columna];
                        if ($valor === null) {
                            $celdas[] = 'NULL';
                        } elseif (is_int($valor) || is_float($valor)) {
                            $celdas[] = (string) $valor;
                        } else {
                            $celdas[] = $pdo->quote((string) $valor);
                        }
                    }
                    $valores[] = '(' . implode(',', $celdas) . ')';
                }
                $salida .= 'INSERT INTO `' . $tabla . '` (' . $listaColumnas . ") VALUES\n"
                    . implode(",\n", $valores) . ";\n";
            }
            $salida .= "\n";
        }

        $salida .= "SET FOREIGN_KEY_CHECKS = 1;\n";
        return $salida;
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

        $contenido = file_get_contents($ruta);
        return [
            'nombre'    => (string) $fila['nombre_archivo'],
            'contenido' => $contenido === false ? '' : $contenido,
        ];
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

    public static function actualizarConfiguracion(array $valores): array
    {
        $permitidas = array_column(Ajustes::todos(), 'clave');
        foreach ($valores as $clave => $valor) {
            if (!in_array($clave, $permitidas, true)) {
                continue;
            }
            Ajustes::definir((string) $clave, (string) $valor);
        }

        Bitacora::registrar(
            'CONFIGURACION_ACTUALIZAR',
            'Sistema',
            'Configuración del sistema actualizada',
            'Advertencia',
            'configuracion',
            '',
            null,
            $valores
        );

        return Ajustes::todos();
    }
}
