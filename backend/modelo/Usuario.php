<?php

declare(strict_types=1);

namespace Kerico\Modelo;

use Kerico\Nucleo\Autenticacion;
use Kerico\Nucleo\Bd;
use Kerico\Nucleo\Bitacora;
use Kerico\Nucleo\ErrorHttp;
use Kerico\Nucleo\Paginador;

final class Usuario
{
    public static function listar(array $filtros = []): array
    {
        $sql = 'SELECT u.*, r.nombre AS rol,
                       (SELECT COUNT(*) FROM sesiones s WHERE s.usuario_id = u.id AND s.cerrada_en IS NULL) AS sesiones_activas
                FROM usuarios u JOIN roles r ON r.id = u.rol_id WHERE u.deleted_at IS NULL';
        $parametros = [];

        if (!empty($filtros['busqueda'])) {
            $sql .= ' AND (u.nombre LIKE ? OR u.apellido LIKE ? OR u.usuario LIKE ? OR u.correo LIKE ? OR u.documento LIKE ?)';
            $termino = '%' . $filtros['busqueda'] . '%';
            for ($i = 0; $i < 5; $i++) {
                $parametros[] = $termino;
            }
        }
        if (!empty($filtros['rol_id'])) {
            $sql .= ' AND u.rol_id = ?';
            $parametros[] = (int) $filtros['rol_id'];
        }

        $sql .= ' ORDER BY u.nombre, u.apellido';

        return array_map([self::class, 'normalizar'], Bd::consultar($sql, $parametros));
    }

    private const ORDEN = [
        'nombre'  => 'u.nombre',
        'usuario' => 'u.usuario',
        'correo'  => 'u.correo',
        'rol'     => 'r.nombre',
        'acceso'  => 'u.ultimo_acceso',
        'estado'  => 'u.activo',
    ];

    public static function paginar(array $filtros = []): array
    {
        $desde = ' FROM usuarios u JOIN roles r ON r.id = u.rol_id WHERE u.deleted_at IS NULL';
        $parametros = [];

        if (!empty($filtros['busqueda'])) {
            $desde .= ' AND (u.nombre LIKE ? OR u.apellido LIKE ? OR u.usuario LIKE ? OR u.correo LIKE ? OR u.documento LIKE ?)';
            $termino = '%' . $filtros['busqueda'] . '%';
            for ($i = 0; $i < 5; $i++) {
                $parametros[] = $termino;
            }
        }
        if (!empty($filtros['rol_id'])) {
            $desde .= ' AND u.rol_id = ?';
            $parametros[] = (int) $filtros['rol_id'];
        }
        if (isset($filtros['activo']) && $filtros['activo'] !== '') {
            $desde .= ' AND u.activo = ?';
            $parametros[] = (int) $filtros['activo'];
        }

        return Paginador::consultar(
            'SELECT u.*, r.nombre AS rol,
                    (SELECT COUNT(*) FROM sesiones s WHERE s.usuario_id = u.id AND s.cerrada_en IS NULL) AS sesiones_activas',
            $desde,
            $parametros,
            $filtros,
            self::ORDEN,
            'nombre',
            [self::class, 'normalizar']
        );
    }

    public static function eliminar(int $id): void
    {
        $usuario = Bd::primero('SELECT * FROM usuarios WHERE id = ? AND deleted_at IS NULL', [$id]);
        if ($usuario === null) {
            throw ErrorHttp::noEncontrado('El usuario no existe');
        }
        if ($id === Autenticacion::idUsuario()) {
            throw ErrorHttp::conflicto('No puede eliminar su propia cuenta');
        }

        $administradores = (int) Bd::valor(
            'SELECT COUNT(*) FROM usuarios WHERE rol_id = 1 AND activo = 1 AND deleted_at IS NULL'
        );
        if ((int) $usuario['rol_id'] === 1 && $administradores <= 1) {
            throw ErrorHttp::conflicto('Debe existir al menos un administrador activo en el sistema');
        }

        Bd::ejecutar('UPDATE usuarios SET activo = 0, deleted_at = NOW() WHERE id = ?', [$id]);
        Bd::ejecutar(
            "UPDATE sesiones SET cerrada_en = NOW(), motivo_cierre = 'Forzado'
             WHERE usuario_id = ? AND cerrada_en IS NULL",
            [$id]
        );

        Bitacora::registrar(
            'USUARIO_ELIMINAR',
            'Usuarios',
            'Usuario ' . $usuario['usuario'] . ' enviado a la papelera (borrado lógico)',
            'Advertencia',
            'usuarios',
            $id,
            ['deleted_at' => null, 'activo' => (int) $usuario['activo']],
            ['deleted_at' => date('Y-m-d H:i:s'), 'activo' => 0]
        );
    }

    public static function restaurar(int $id): array
    {
        $usuario = Bd::primero('SELECT * FROM usuarios WHERE id = ? AND deleted_at IS NOT NULL', [$id]);
        if ($usuario === null) {
            throw ErrorHttp::noEncontrado('El usuario no está en la papelera');
        }

        Bd::ejecutar('UPDATE usuarios SET activo = 1, deleted_at = NULL WHERE id = ?', [$id]);

        Bitacora::registrar(
            'USUARIO_RESTAURAR',
            'Usuarios',
            'Usuario ' . $usuario['usuario'] . ' restaurado desde la papelera',
            'Advertencia',
            'usuarios',
            $id
        );

        return self::porId($id);
    }

    public static function papelera(): array
    {
        return array_map(static fn (array $u): array => [
            'id'         => (int) $u['id'],
            'usuario'    => (string) $u['usuario'],
            'nombre'     => trim((string) $u['nombre'] . ' ' . (string) $u['apellido']),
            'correo'     => (string) $u['correo'],
            'deleted_at' => (string) $u['deleted_at'],
        ], Bd::consultar(
            'SELECT id, usuario, nombre, apellido, correo, deleted_at FROM usuarios
             WHERE deleted_at IS NOT NULL ORDER BY deleted_at DESC'
        ));
    }

    public static function porId(int $id): array
    {
        $fila = Bd::primero(
            'SELECT u.*, r.nombre AS rol,
                    (SELECT COUNT(*) FROM sesiones s WHERE s.usuario_id = u.id AND s.cerrada_en IS NULL) AS sesiones_activas
             FROM usuarios u JOIN roles r ON r.id = u.rol_id WHERE u.id = ? AND u.deleted_at IS NULL',
            [$id]
        );
        if ($fila === null) {
            throw ErrorHttp::noEncontrado('El usuario no existe');
        }
        return self::normalizar($fila);
    }

    public static function normalizar(array $f): array
    {
        return [
            'id'               => (int) $f['id'],
            'usuario'          => (string) $f['usuario'],
            'nombre'           => (string) $f['nombre'],
            'apellido'         => (string) $f['apellido'],
            'tipo_documento'   => (string) $f['tipo_documento'],
            'documento'        => (string) $f['documento'],
            'fecha_nac'        => $f['fecha_nac'],
            'correo'           => (string) $f['correo'],
            'telefono'         => (string) $f['telefono'],
            'cargo'            => (string) $f['cargo'],
            'rol_id'           => (int) $f['rol_id'],
            'rol'              => (string) $f['rol'],
            'activo'           => (int) $f['activo'] === 1,
            'bloqueado'        => $f['bloqueado_hasta'] !== null && strtotime((string) $f['bloqueado_hasta']) > time(),
            'ultimo_acceso'    => $f['ultimo_acceso'],
            'sesiones_activas' => (int) ($f['sesiones_activas'] ?? 0),
        ];
    }

    public static function roles(): array
    {
        return array_map(static fn (array $r): array => [
            'id'          => (int) $r['id'],
            'nombre'      => (string) $r['nombre'],
            'descripcion' => (string) $r['descripcion'],
            'usuarios'    => (int) $r['usuarios'],
            'permisos'    => (int) $r['permisos'],
        ], Bd::consultar(
            'SELECT r.*,
                    (SELECT COUNT(*) FROM usuarios u WHERE u.rol_id = r.id AND u.deleted_at IS NULL) AS usuarios,
                    (SELECT COUNT(*) FROM rol_permisos rp WHERE rp.rol_id = r.id) AS permisos
             FROM roles r ORDER BY r.id'
        ));
    }

    public static function crear(array $datos): array
    {
        self::validarUnico('usuario', $datos['usuario'], null, 'El nombre de usuario ya está en uso');
        self::validarUnico('correo', $datos['correo'], null, 'El correo ya está registrado');
        self::validarUnico('documento', $datos['documento'], null, 'El documento ya está registrado');

        $id = Bd::insertar(
            'INSERT INTO usuarios
                (usuario, password_hash, nombre, apellido, tipo_documento, documento, fecha_nac,
                 correo, telefono, cargo, rol_id, activo)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
            [
                $datos['usuario'],
                password_hash($datos['password'], PASSWORD_BCRYPT),
                $datos['nombre'], $datos['apellido'], $datos['tipo_documento'], $datos['documento'],
                $datos['fecha_nac'], $datos['correo'], $datos['telefono'], $datos['cargo'],
                $datos['rol_id'], $datos['activo'] ? 1 : 0,
            ]
        );

        Bitacora::registrar(
            'USUARIO_CREAR',
            'Usuarios',
            'Usuario ' . $datos['usuario'] . ' creado con el rol ' . $datos['rol_id'],
            'Info',
            'usuarios',
            $id
        );

        return self::porId($id);
    }

    public static function actualizar(int $id, array $datos): array
    {
        $antes = Bd::primero('SELECT * FROM usuarios WHERE id = ?', [$id]);
        if ($antes === null) {
            throw ErrorHttp::noEncontrado('El usuario no existe');
        }

        self::validarUnico('usuario', $datos['usuario'], $id, 'El nombre de usuario ya está en uso');
        self::validarUnico('correo', $datos['correo'], $id, 'El correo ya está registrado');
        self::validarUnico('documento', $datos['documento'], $id, 'El documento ya está registrado');

        if ($id === Autenticacion::idUsuario() && !$datos['activo']) {
            throw ErrorHttp::conflicto('No puede desactivar su propia cuenta');
        }

        Bd::ejecutar(
            'UPDATE usuarios SET
                usuario = ?, nombre = ?, apellido = ?, tipo_documento = ?, documento = ?, fecha_nac = ?,
                correo = ?, telefono = ?, cargo = ?, rol_id = ?, activo = ?
             WHERE id = ?',
            [
                $datos['usuario'], $datos['nombre'], $datos['apellido'], $datos['tipo_documento'],
                $datos['documento'], $datos['fecha_nac'], $datos['correo'], $datos['telefono'],
                $datos['cargo'], $datos['rol_id'], $datos['activo'] ? 1 : 0, $id,
            ]
        );

        if (!empty($datos['password'])) {
            Bd::ejecutar(
                'UPDATE usuarios SET password_hash = ? WHERE id = ?',
                [password_hash($datos['password'], PASSWORD_BCRYPT), $id]
            );
            Bitacora::registrar(
                'PASSWORD_RESTABLECER',
                'Usuarios',
                'Contraseña del usuario ' . $datos['usuario'] . ' restablecida por un administrador',
                'Advertencia',
                'usuarios',
                $id
            );
        }

        if (!$datos['activo']) {
            Bd::ejecutar(
                "UPDATE sesiones SET cerrada_en = NOW(), motivo_cierre = 'Forzado'
                 WHERE usuario_id = ? AND cerrada_en IS NULL",
                [$id]
            );
        }

        Bitacora::registrar(
            'USUARIO_ACTUALIZAR',
            'Usuarios',
            'Usuario ' . $datos['usuario'] . ' actualizado',
            'Info',
            'usuarios',
            $id,
            ['rol_id' => (int) $antes['rol_id'], 'activo' => (int) $antes['activo']],
            ['rol_id' => (int) $datos['rol_id'], 'activo' => $datos['activo'] ? 1 : 0]
        );

        return self::porId($id);
    }

    public static function desbloquear(int $id): array
    {
        Bd::ejecutar('UPDATE usuarios SET bloqueado_hasta = NULL, intentos_fallidos = 0 WHERE id = ?', [$id]);
        Bitacora::registrar('USUARIO_DESBLOQUEAR', 'Usuarios', 'Cuenta desbloqueada manualmente', 'Advertencia', 'usuarios', $id);
        return self::porId($id);
    }

    public static function cambiarPassword(int $id, string $actual, string $nueva): void
    {
        $fila = Bd::primero('SELECT password_hash, usuario FROM usuarios WHERE id = ?', [$id]);
        if ($fila === null) {
            throw ErrorHttp::noEncontrado('El usuario no existe');
        }
        if (!password_verify($actual, (string) $fila['password_hash'])) {
            throw ErrorHttp::validacion('La contraseña actual no es correcta', ['password_actual' => 'Incorrecta']);
        }
        if (strlen($nueva) < 3) {
            throw ErrorHttp::validacion('La nueva contraseña debe tener al menos 3 caracteres', ['password_nueva' => 'Muy corta']);
        }

        Bd::ejecutar('UPDATE usuarios SET password_hash = ? WHERE id = ?', [password_hash($nueva, PASSWORD_BCRYPT), $id]);

        Bitacora::registrar(
            'PASSWORD_CAMBIAR',
            'Usuarios',
            'El usuario ' . $fila['usuario'] . ' cambió su contraseña',
            'Advertencia',
            'usuarios',
            $id
        );
    }

    public static function solicitarRecuperacion(string $correo): array
    {
        $fila = Bd::primero('SELECT id, usuario, nombre FROM usuarios WHERE correo = ? AND activo = 1 AND deleted_at IS NULL', [$correo]);
        if ($fila === null) {
            return ['enviado' => true];
        }

        $token = bin2hex(random_bytes(24));
        Bd::ejecutar(
            'UPDATE usuarios SET token_recuperacion = ?, token_expira = ? WHERE id = ?',
            [$token, date('Y-m-d H:i:s', time() + 3600), (int) $fila['id']]
        );

        Bitacora::registrar(
            'PASSWORD_RECUPERAR',
            'Autenticacion',
            'Se generó un enlace de recuperación para ' . $fila['usuario'],
            'Advertencia',
            'usuarios',
            (int) $fila['id'],
            null,
            null,
            null
        );

        return ['enviado' => true, 'token' => $token, 'usuario' => (string) $fila['usuario']];
    }

    public static function restablecerConToken(string $token, string $nueva): void
    {
        if (strlen($nueva) < 3) {
            throw ErrorHttp::validacion('La nueva contraseña debe tener al menos 3 caracteres', ['password' => 'Muy corta']);
        }

        $fila = Bd::primero(
            'SELECT id, usuario FROM usuarios WHERE token_recuperacion = ? AND token_expira > NOW() AND deleted_at IS NULL',
            [$token]
        );
        if ($fila === null) {
            throw ErrorHttp::validacion('El enlace de recuperación no es válido o ya venció', ['token' => 'Inválido']);
        }

        Bd::ejecutar(
            'UPDATE usuarios SET password_hash = ?, token_recuperacion = NULL, token_expira = NULL,
                    intentos_fallidos = 0, bloqueado_hasta = NULL
             WHERE id = ?',
            [password_hash($nueva, PASSWORD_BCRYPT), (int) $fila['id']]
        );

        Bitacora::registrar(
            'PASSWORD_RESTABLECER',
            'Autenticacion',
            'El usuario ' . $fila['usuario'] . ' restableció su contraseña con un enlace de recuperación',
            'Advertencia',
            'usuarios',
            (int) $fila['id'],
            null,
            null,
            null
        );
    }

    public static function sesionesActivas(): array
    {
        return array_map(static fn (array $s): array => [
            'id'               => substr((string) $s['id'], 0, 12),
            'usuario'          => (string) $s['usuario'],
            'nombre'           => trim((string) $s['nombre'] . ' ' . (string) $s['apellido']),
            'rol'              => (string) $s['rol'],
            'ip'               => (string) $s['ip'],
            'creada_en'        => (string) $s['creada_en'],
            'ultima_actividad' => (string) $s['ultima_actividad'],
            'expira_en'        => (string) $s['expira_en'],
        ], Bd::consultar(
            'SELECT s.*, u.usuario, u.nombre, u.apellido, r.nombre AS rol
             FROM sesiones s
             JOIN usuarios u ON u.id = s.usuario_id
             JOIN roles r ON r.id = u.rol_id
             WHERE s.cerrada_en IS NULL
             ORDER BY s.ultima_actividad DESC'
        ));
    }

    private static function validarUnico(string $columna, string $valor, ?int $excluir, string $mensaje): void
    {
        $sql = 'SELECT id FROM usuarios WHERE deleted_at IS NULL AND ' . $columna . ' = ?';
        $parametros = [$valor];
        if ($excluir !== null) {
            $sql .= ' AND id <> ?';
            $parametros[] = $excluir;
        }
        if (Bd::valor($sql, $parametros) !== null) {
            throw ErrorHttp::validacion($mensaje, [$columna => $mensaje]);
        }
    }
}
