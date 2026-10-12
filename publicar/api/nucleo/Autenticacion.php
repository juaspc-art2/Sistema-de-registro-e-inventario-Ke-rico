<?php

declare(strict_types=1);

namespace Kerico\Nucleo;

final class Autenticacion
{
    private const HASH_SENUELO = '$2y$10$OenjZa2Me9AZ6EswgdESW.haK4/TTbcrCNBrtXuveW.zbDhSlLJDG';

    // Duración máxima de una sesión, por mucho que se use: un turno largo
    private const HORAS_MAXIMAS = 12;

    // Cierra las sesiones abiertas de un usuario, menos la indicada. Se usa al cambiar su contraseña o desactivarlo
    public static function cerrarSesionesDe(int $usuarioId, string $excepto = ''): void
    {
        Bd::ejecutar(
            "UPDATE sesiones SET cerrada_en = NOW(), motivo_cierre = 'Forzado'
             WHERE usuario_id = ? AND cerrada_en IS NULL AND id <> ?",
            [$usuarioId, $excepto]
        );
    }

    private static ?array $usuario = null;
    private static ?string $token = null;
    private static ?string $csrf = null;
    private static array $permisos = [];

    public static function iniciarSesion(string $usuario, string $password, string $ip, string $agente): array
    {
        $intentosMaximos = (int) Ajustes::obtener('intentos_maximos_login', 5);
        $bloqueoMinutos = (int) Ajustes::obtener('bloqueo_minutos', 15);

        // Una sola respuesta para usuario inexistente, clave errada y cuenta bloqueada:
        // así el inicio de sesión no sirve para averiguar qué cuentas existen
        $rechazo = ErrorHttp::noAutenticado(
            'Usuario o contraseña incorrectos. Tras ' . $intentosMaximos . ' intentos fallidos la cuenta se bloquea '
            . $bloqueoMinutos . ' minutos.'
        );

        $id = Bd::valor(
            'SELECT id FROM usuarios WHERE (usuario = ? OR correo = ?) AND deleted_at IS NULL LIMIT 1',
            [$usuario, $usuario]
        );

        if ($id === null) {
            // Mismo trabajo que con una cuenta real: el tiempo de respuesta tampoco la delata
            password_verify($password, self::HASH_SENUELO);
            // Solo el comienzo de lo escrito: a veces lo que se teclea en este campo es la contraseña
            Bitacora::registrar(
                'LOGIN_FALLIDO',
                'Autenticacion',
                'Intento de inicio de sesión con un usuario inexistente: ' . mb_substr($usuario, 0, 3) . '… ('
                . mb_strlen($usuario) . ' caracteres)',
                'Error',
                'usuarios',
                '',
                null,
                null,
                null
            );
            throw $rechazo;
        }

        // Los intentos sobre una cuenta pasan de uno en uno: su fila queda tomada mientras se revisa la clave.
        // Sin esto, muchas claves enviadas a la vez pasaban todas antes de que el contador llegara al máximo.
        // Un rechazo devuelve null en vez de lanzar: una excepción desharía el conteo del intento.
        $fila = Bd::transaccion(static function () use ($id, $password, $intentosMaximos, $bloqueoMinutos): ?array {
            $fila = Bd::primero('SELECT * FROM usuarios WHERE id = ? FOR UPDATE', [$id]);
            if ($fila === null) {
                return null;
            }
            $fila['rol'] = (string) Bd::valor('SELECT nombre FROM roles WHERE id = ?', [$fila['rol_id']]);

            if ($fila['bloqueado_hasta'] !== null && strtotime((string) $fila['bloqueado_hasta']) > time()) {
                password_verify($password, self::HASH_SENUELO);
                return null;
            }
            if (password_verify($password, (string) $fila['password_hash'])) {
                return $fila;
            }

            $intentos = (int) $fila['intentos_fallidos'] + 1;
            $bloqueo = null;
            if ($intentos >= $intentosMaximos) {
                $bloqueo = date('Y-m-d H:i:s', time() + $bloqueoMinutos * 60);
                $intentos = 0;
            }
            Bd::ejecutar(
                'UPDATE usuarios SET intentos_fallidos = ?, bloqueado_hasta = ? WHERE id = ?',
                [$intentos, $bloqueo, $fila['id']]
            );
            Bitacora::registrar(
                'LOGIN_FALLIDO',
                'Autenticacion',
                'Intento de inicio de sesión fallido para el usuario ' . $fila['usuario']
                . ($bloqueo !== null ? '. Cuenta bloqueada por ' . $bloqueoMinutos . ' minutos' : ''),
                'Error',
                'usuarios',
                $fila['id'],
                null,
                null,
                self::comoUsuario($fila)
            );
            return null;
        });

        if ($fila === null) {
            throw $rechazo;
        }

        if ((int) $fila['activo'] !== 1) {
            throw ErrorHttp::sinPermiso('La cuenta se encuentra inactiva. Contacte al administrador.');
        }

        if (password_needs_rehash((string) $fila['password_hash'], PASSWORD_BCRYPT)) {
            Bd::ejecutar(
                'UPDATE usuarios SET password_hash = ? WHERE id = ?',
                [password_hash($password, PASSWORD_BCRYPT), $fila['id']]
            );
        }

        $minutos = (int) Ajustes::obtener('sesion_minutos_inactividad', 15);
        $token = bin2hex(random_bytes(32));
        $csrf = bin2hex(random_bytes(32));

        Bd::ejecutar(
            'INSERT INTO sesiones (id, usuario_id, ip, user_agent, expira_en, csrf_token) VALUES (?,?,?,?,?,?)',
            [$token, $fila['id'], $ip, $agente, date('Y-m-d H:i:s', time() + $minutos * 60), $csrf]
        );

        Bd::ejecutar(
            'UPDATE usuarios SET intentos_fallidos = 0, bloqueado_hasta = NULL, ultimo_acceso = NOW() WHERE id = ?',
            [$fila['id']]
        );

        self::$usuario = self::comoUsuario($fila);
        self::$token = $token;
        self::$csrf = $csrf;
        self::$permisos = self::cargarPermisos((int) $fila['rol_id']);

        Bitacora::registrar(
            'LOGIN',
            'Autenticacion',
            'Inicio de sesión correcto',
            'Info',
            'usuarios',
            $fila['id']
        );

        return [
            'token'             => $token,
            'csrf_token'        => $csrf,
            'usuario'           => self::$usuario,
            'permisos'          => self::$permisos,
            'minutos_sesion'    => $minutos,
            'expira_en'         => date('c', time() + $minutos * 60),
        ];
    }

    private static function comoUsuario(array $fila): array
    {
        return [
            'id'        => (int) $fila['id'],
            'usuario'   => (string) $fila['usuario'],
            'nombre'    => (string) $fila['nombre'],
            'apellido'  => (string) $fila['apellido'],
            'correo'    => (string) $fila['correo'],
            'cargo'     => (string) $fila['cargo'],
            'rol_id'    => (int) $fila['rol_id'],
            'rol'       => (string) ($fila['rol'] ?? ''),
        ];
    }

    private static function cargarPermisos(int $rolId): array
    {
        $filas = Bd::consultar(
            'SELECT p.codigo FROM permisos p
             JOIN rol_permisos rp ON rp.permiso_id = p.id
             WHERE rp.rol_id = ?',
            [$rolId]
        );
        return array_map(static fn (array $f): string => (string) $f['codigo'], $filas);
    }

    public static function autenticar(Peticion $peticion): void
    {
        $token = $peticion->token();
        if ($token === '') {
            throw ErrorHttp::noAutenticado();
        }

        $sesion = Bd::primero(
            'SELECT s.usuario_id, s.cerrada_en, s.ultima_actividad, s.creada_en, s.csrf_token,
                    u.id, u.usuario, u.nombre, u.apellido, u.correo, u.cargo, u.activo, u.rol_id,
                    u.deleted_at, r.nombre AS rol
             FROM sesiones s
             JOIN usuarios u ON u.id = s.usuario_id
             JOIN roles r ON r.id = u.rol_id
             WHERE s.id = ?',
            [$token]
        );

        if ($sesion === null) {
            throw ErrorHttp::noAutenticado('La sesión no existe o ya fue cerrada');
        }

        if ($sesion['cerrada_en'] !== null) {
            throw ErrorHttp::noAutenticado('La sesión fue cerrada');
        }

        if ((int) $sesion['activo'] !== 1 || $sesion['deleted_at'] !== null) {
            self::cerrarSesion($token, 'Forzado');
            throw ErrorHttp::sinPermiso('La cuenta se encuentra inactiva');
        }

        $minutos = (int) Ajustes::obtener('sesion_minutos_inactividad', 15);
        $ultima = strtotime((string) $sesion['ultima_actividad']);

        if (time() - $ultima > $minutos * 60) {
            self::cerrarSesion($token, 'Inactividad');
            Bitacora::registrar(
                'SESION_EXPIRADA',
                'Autenticacion',
                'Sesión cerrada automáticamente por ' . $minutos . ' minutos de inactividad',
                'Sistema',
                'sesiones',
                $token,
                null,
                null,
                self::comoUsuario($sesion)
            );
            throw ErrorHttp::noAutenticado('La sesión expiró por inactividad');
        }

        // Una sesión en uso no vence por inactividad: sin este tope, quien la robara podría mantenerla viva sin fin
        if (time() - strtotime((string) $sesion['creada_en']) > self::HORAS_MAXIMAS * 3600) {
            self::cerrarSesion($token, 'Expiracion');
            throw ErrorHttp::noAutenticado('La sesión cumplió ' . self::HORAS_MAXIMAS . ' horas. Vuelva a iniciar sesión.');
        }

        Bd::ejecutar(
            'UPDATE sesiones SET ultima_actividad = NOW(), expira_en = ? WHERE id = ?',
            [date('Y-m-d H:i:s', time() + $minutos * 60), $token]
        );

        self::$usuario = self::comoUsuario($sesion);
        self::$token = $token;
        self::$csrf = (string) $sesion['csrf_token'];
        self::$permisos = self::cargarPermisos((int) $sesion['rol_id']);
    }

    public static function exigirCsrf(Peticion $peticion): void
    {
        $enviado = $peticion->csrf();
        $esperado = self::$csrf ?? '';

        if ($esperado === '') {
            return;
        }

        if ($enviado === '' || !hash_equals($esperado, $enviado)) {
            Bitacora::registrar(
                'CSRF_RECHAZADO',
                'Seguridad',
                'Petición ' . $peticion->metodo() . ' ' . $peticion->ruta() . ' rechazada por token CSRF inválido',
                'Error',
                'sesiones',
                ''
            );
            throw ErrorHttp::sinPermiso('Token de seguridad inválido o ausente. Recargue la página e intente de nuevo.');
        }
    }

    public static function csrfActual(): string
    {
        return self::$csrf ?? '';
    }

    public static function cerrarSesion(string $token, string $motivo = 'Manual'): void
    {
        Bd::ejecutar(
            'UPDATE sesiones SET cerrada_en = NOW(), motivo_cierre = ? WHERE id = ? AND cerrada_en IS NULL',
            [$motivo, $token]
        );
    }

    public static function purgarSesiones(): int
    {
        $minutos = (int) Ajustes::obtener('sesion_minutos_inactividad', 15);
        return Bd::ejecutar(
            'UPDATE sesiones SET cerrada_en = NOW(), motivo_cierre = ?
             WHERE cerrada_en IS NULL AND ultima_actividad < (NOW() - INTERVAL ? MINUTE)',
            ['Inactividad', $minutos]
        );
    }

    public static function usuarioActual(): ?array
    {
        return self::$usuario;
    }

    public static function tokenActual(): ?string
    {
        return self::$token;
    }

    public static function permisos(): array
    {
        return self::$permisos;
    }

    public static function tienePermiso(string $codigo): bool
    {
        return in_array($codigo, self::$permisos, true);
    }

    public static function exigir(string $codigo): void
    {
        if (self::$usuario === null) {
            throw ErrorHttp::noAutenticado();
        }
        if (!self::tienePermiso($codigo)) {
            Bitacora::registrar(
                'ACCESO_DENEGADO',
                'Autenticacion',
                'Intento de acceder a una función sin el permiso ' . $codigo,
                'Advertencia',
                'permisos',
                $codigo
            );
            throw ErrorHttp::sinPermiso();
        }
    }

    public static function idUsuario(): int
    {
        if (self::$usuario === null) {
            throw ErrorHttp::noAutenticado();
        }
        return (int) self::$usuario['id'];
    }
}
