<?php

declare(strict_types=1);

namespace Kerico\Nucleo;

final class Correo
{
    // En base64 el adjunto pesa un tercio más, y los proveedores suelen cortar entre 20 y 25 MB
    public const MAXIMO_ADJUNTO = 10 * 1024 * 1024;

    // Logotipo a 360 px (el doble de los 180 px a los que se muestra) y por debajo de los 40 KB del manual
    private const LOGO = __DIR__ . '/../recursos/logo-correo.png';

    private static array $config = [];
    private static array $cola = [];

    public static function configurar(array $config): void
    {
        self::$config = $config;
    }

    public static function activo(): bool
    {
        return (self::$config['host'] ?? '') !== '';
    }

    /**
     * $para admite varias direcciones separadas por coma.
     * $cuerpo: bloques en orden. Un texto es un párrafo; ['titulo' => ...], ['datos' => [etiqueta => valor]]
     *          y ['codigo' => ...] se resaltan en la versión HTML.
     * $adjunto: ['nombre' => string, 'tipo' => string, 'contenido' => string]
     * $firmante: ['nombre' => string, 'cargo' => string] de quien envía; sin él firma el sistema.
     */
    public static function enviar(string $para, string $asunto, array|string $cuerpo, ?array $adjunto = null, ?array $firmante = null): void
    {
        if (!self::activo()) {
            throw ErrorHttp::conflicto('El envío de correo no está configurado en el servidor');
        }

        $destinatarios = preg_split('/[\s,;]+/', $para, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        foreach ($destinatarios as $direccion) {
            if (filter_var($direccion, FILTER_VALIDATE_EMAIL) === false) {
                throw ErrorHttp::validacion('El correo ' . $direccion . ' no tiene un formato válido', ['correo' => 'Formato inválido']);
            }
        }
        if ($destinatarios === []) {
            throw ErrorHttp::validacion('Escriba el correo de destino', ['correo' => 'Obligatorio']);
        }
        if ($adjunto !== null) {
            self::exigirTamano(strlen($adjunto['contenido']));
        }

        $remitente = (string) (self::$config['remitente'] ?: self::$config['usuario']);
        if (filter_var($remitente, FILTER_VALIDATE_EMAIL) === false) {
            throw new ErrorHttp('Falta el correo remitente: defina KERICO_SMTP_FROM en el servidor', 500);
        }

        $emisor = Ajustes::emisor();
        $bloques = is_string($cuerpo) ? [$cuerpo] : $cuerpo;
        $firma = [
            'nombre'    => trim((string) ($firmante['nombre'] ?? '')) ?: $emisor['nombre'],
            'cargo'     => trim((string) ($firmante['cargo'] ?? '')) ?: 'Sistema de Registro e Inventario',
            'telefono'  => $emisor['telefono'],
            'direccion' => $emisor['direccion'],
        ];

        // El mismo contenido en dos versiones: HTML con la identidad de la marca y texto plano
        // para los lectores que no muestran HTML. El logotipo viaja dentro del mensaje (cid).
        $logo = is_file(self::LOGO) ? (string) file_get_contents(self::LOGO) : '';
        $html = self::parte('text/html; charset=UTF-8', self::comoHtml($asunto, $bloques, $firma, $emisor['nombre'], $logo !== ''));
        if ($logo !== '') {
            $html = self::multiparte('related', [
                $html,
                self::parte('image/png', $logo, "Content-ID: <logo-kerico>\r\nContent-Disposition: inline; filename=\"logo-kerico.png\"\r\n"),
            ]);
        }
        $contenido = self::multiparte('alternative', [
            self::parte('text/plain; charset=UTF-8', self::comoTexto($bloques, $firma)),
            $html,
        ]);
        if ($adjunto !== null) {
            $nombre = preg_replace('/[^A-Za-z0-9._-]+/', '_', (string) $adjunto['nombre']) ?? 'archivo';
            $contenido = self::multiparte('mixed', [
                $contenido,
                self::parte(
                    $adjunto['tipo'] . '; name="' . $nombre . '"',
                    $adjunto['contenido'],
                    'Content-Disposition: attachment; filename="' . $nombre . "\"\r\n"
                ),
            ]);
        }

        $cabeceras = [
            'Date: ' . date('r'),
            'From: =?UTF-8?B?' . base64_encode($emisor['nombre']) . '?= <' . $remitente . '>',
            'To: ' . implode(', ', $destinatarios),
            'Subject: ' . mb_encode_mimeheader(
                preg_replace('/[\r\n]+/', ' ', $emisor['nombre'] . ' · ' . $asunto) ?? '',
                'UTF-8',
                'B',
                "\r\n"
            ),
            'Message-ID: <' . bin2hex(random_bytes(16)) . strstr($remitente, '@') . '>',
            'MIME-Version: 1.0',
        ];

        self::transmitir($remitente, $destinatarios, implode("\r\n", $cabeceras) . "\r\n" . $contenido);
    }

    public static function exigirTamano(int $bytes): void
    {
        if ($bytes > self::MAXIMO_ADJUNTO) {
            throw ErrorHttp::validacion(
                'El archivo pesa ' . number_format($bytes / 1048576, 1, ',', '.')
                . ' MB y el máximo por correo es ' . (self::MAXIMO_ADJUNTO / 1048576) . ' MB. Descárguelo en su lugar.',
                ['correo' => 'Archivo demasiado grande']
            );
        }
    }

    // Una parte del mensaje. Todo viaja en base64: ninguna línea empieza por punto ni supera el largo que admite SMTP
    private static function parte(string $tipo, string $contenido, string $cabecerasExtra = ''): string
    {
        return 'Content-Type: ' . $tipo . "\r\nContent-Transfer-Encoding: base64\r\n" . $cabecerasExtra . "\r\n"
            . chunk_split(base64_encode($contenido));
    }

    // Varias partes bajo un mismo límite; el resultado es a su vez una parte y se puede anidar
    private static function multiparte(string $subtipo, array $partes): string
    {
        $limite = 'kerico-' . bin2hex(random_bytes(12));
        return 'Content-Type: multipart/' . $subtipo . '; boundary="' . $limite . "\"\r\n\r\n"
            . '--' . $limite . "\r\n" . implode('--' . $limite . "\r\n", $partes) . '--' . $limite . "--\r\n";
    }

    private static function comoTexto(array $bloques, array $firma): string
    {
        $parrafos = [];
        foreach ($bloques as $bloque) {
            if (is_string($bloque)) {
                $parrafos[] = $bloque;
            } elseif (isset($bloque['datos'])) {
                $lineas = [];
                foreach ($bloque['datos'] as $etiqueta => $valor) {
                    $lineas[] = $etiqueta . ': ' . $valor;
                }
                $parrafos[] = implode("\n", $lineas);
            } else {
                $parrafos[] = (string) ($bloque['titulo'] ?? $bloque['codigo'] ?? '');
            }
        }
        return implode("\n\n", $parrafos) . "\n\n-- \n" . implode("\n", array_filter($firma));
    }

    // Sigue el manual de identidad (C.09): Calibri o Arial, nombre en Carbón, cargo en Naranja, datos en
    // Piedra, filete con el degradado corporativo y logotipo a 180 px. Tablas y estilos en línea porque
    // los lectores de correo ignoran las hojas de estilo.
    private static function comoHtml(string $asunto, array $bloques, array $firma, string $marca, bool $conLogo): string
    {
        $e = static fn ($texto): string => htmlspecialchars((string) $texto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $cuerpo = '';
        foreach ($bloques as $bloque) {
            if (is_string($bloque)) {
                $cuerpo .= '<p style="margin:0 0 14px;">' . nl2br($e($bloque)) . '</p>';
            } elseif (isset($bloque['titulo'])) {
                $cuerpo .= '<p style="margin:20px 0 4px;font-size:15px;font-weight:bold;color:#1e1b18;">' . $e($bloque['titulo']) . '</p>';
            } elseif (isset($bloque['codigo'])) {
                $cuerpo .= '<p style="margin:0 0 14px;padding:14px 16px;background:#fbf2ed;border:1px dashed #e1bfb3;'
                    . 'border-radius:8px;font-family:Consolas,\'Courier New\',monospace;font-size:15px;color:#1e1b18;'
                    . 'word-break:break-all;">' . $e($bloque['codigo']) . '</p>';
            } elseif (isset($bloque['datos'])) {
                $filas = '';
                foreach ($bloque['datos'] as $etiqueta => $valor) {
                    $filas .= '<tr><td style="padding:7px 18px 7px 0;border-bottom:1px solid #efe6e2;color:#8d7166;'
                        . 'white-space:nowrap;vertical-align:top;">' . $e($etiqueta) . '</td>'
                        . '<td style="padding:7px 0;border-bottom:1px solid #efe6e2;color:#1e1b18;font-weight:bold;">'
                        . $e($valor) . '</td></tr>';
                }
                $cuerpo .= '<table role="presentation" cellpadding="0" cellspacing="0" width="100%" '
                    . 'style="margin:0 0 16px;border-top:1px solid #efe6e2;font-size:14px;">' . $filas . '</table>';
            }
        }

        $logotipo = $conLogo
            ? '<img src="cid:logo-kerico" width="180" alt="' . $e($marca) . '" style="display:block;margin-top:12px;border:0;width:180px;height:auto;">'
            : '<div style="margin-top:10px;font-size:20px;font-weight:bold;color:#eb5e15;">' . $e($marca) . '</div>';
        $degradado = 'background:#eb5e15;background:linear-gradient(%s,#eb5e15,#edca34);font-size:0;line-height:0;';

        return '<!doctype html><html lang="es"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1"><title>' . $e($asunto) . '</title></head>'
            . '<body style="margin:0;padding:0;background:#fff8f5;">'
            . '<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="background:#fff8f5;">'
            . '<tr><td align="center" style="padding:24px 12px;">'
            . '<table role="presentation" cellpadding="0" cellspacing="0" width="600" style="width:100%;max-width:600px;'
            . 'background:#ffffff;border:1px solid #e1bfb3;font-family:Calibri,Arial,sans-serif;">'
            . '<tr><td height="6" bgcolor="#eb5e15" style="height:6px;' . sprintf($degradado, '90deg') . '">&nbsp;</td></tr>'
            . '<tr><td style="padding:28px 28px 26px;font-size:15px;line-height:1.55;color:#594138;">'
            . '<h1 style="margin:0 0 18px;font-size:21px;line-height:1.3;font-weight:bold;color:#1e1b18;">' . $e($asunto) . '</h1>'
            . $cuerpo
            . '<table role="presentation" cellpadding="0" cellspacing="0" style="margin-top:26px;"><tr>'
            . '<td width="2" bgcolor="#eb5e15" style="width:2px;' . sprintf($degradado, '180deg') . '">&nbsp;</td>'
            . '<td style="padding-left:14px;">'
            . '<div style="font-size:13px;font-weight:bold;color:#1e1b18;">' . $e($firma['nombre']) . '</div>'
            . '<div style="font-size:11px;color:#eb5e15;">' . $e($firma['cargo']) . '</div>'
            . '<div style="padding-top:6px;font-size:11px;line-height:1.5;color:#8d7166;">'
            . $e($firma['telefono']) . '<br>' . $e($firma['direccion']) . '</div>'
            . $logotipo
            . '</td></tr></table>'
            . '</td></tr></table>'
            . '</td></tr></table></body></html>';
    }

    // Avisos que nadie espera en pantalla: se envían con despachar() después de responder
    public static function encolar(string $para, string $asunto, array|string $cuerpo): void
    {
        if (self::activo() && trim($para) !== '') {
            self::$cola[$para][] = [$asunto, is_string($cuerpo) ? [$cuerpo] : $cuerpo];
        }
    }

    public static function pendientes(): bool
    {
        return self::$cola !== [];
    }

    // Un solo correo por destinatario; los fallos quedan en la bitácora porque ya no hay a quién mostrarlos
    public static function despachar(): void
    {
        $cola = self::$cola;
        self::$cola = [];

        foreach ($cola as $para => $avisos) {
            $varios = count($avisos) > 1;
            $asunto = $varios ? count($avisos) . ' avisos del sistema' : $avisos[0][0];
            $cuerpo = [];
            foreach ($avisos as [$titulo, $bloques]) {
                $cuerpo = array_merge($cuerpo, $varios ? [['titulo' => $titulo]] : [], $bloques);
            }
            // Una frase que se repite en varios avisos (dónde revisarlos) se deja solo al final
            $cuerpo = array_values(array_filter(
                $cuerpo,
                static fn ($bloque, int $i): bool => !is_string($bloque) || !in_array($bloque, array_slice($cuerpo, $i + 1), true),
                ARRAY_FILTER_USE_BOTH
            ));

            try {
                self::enviar((string) $para, $asunto, $cuerpo);
            } catch (\Throwable $e) {
                Bitacora::registrar(
                    'CORREO_FALLIDO',
                    'Sistema',
                    'No se pudo enviar «' . $asunto . '»: ' . $e->getMessage(),
                    'Error',
                    'correo',
                    ''
                );
            }
        }
    }

    private static function transmitir(string $remitente, array $destinatarios, string $mensaje): void
    {
        $host = (string) self::$config['host'];
        $puerto = (int) self::$config['puerto'];
        $usuario = (string) self::$config['usuario'];

        // El puerto 465 cifra desde el primer byte; en los demás se pide STARTTLS si el servidor lo ofrece
        $cifrado = $puerto === 465;
        $socket = @stream_socket_client(($cifrado ? 'ssl://' : 'tcp://') . $host . ':' . $puerto, $numero, $detalle, 10);
        if ($socket === false) {
            throw new ErrorHttp(
                'No fue posible conectar con el servidor de correo ' . $host . ':' . $puerto
                . ($detalle !== '' ? ' (' . trim((string) $detalle) . ')' : ''),
                502
            );
        }
        stream_set_timeout($socket, 15);

        try {
            $anfitrion = gethostname() ?: 'localhost';
            self::orden($socket, null, 220);
            $ofrece = self::orden($socket, 'EHLO ' . $anfitrion, 250);

            if (!$cifrado && stripos($ofrece, 'STARTTLS') !== false) {
                self::orden($socket, 'STARTTLS', 220);
                if (@stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT) !== true) {
                    throw new ErrorHttp('No fue posible cifrar la conexión con el servidor de correo', 502);
                }
                $cifrado = true;
                self::orden($socket, 'EHLO ' . $anfitrion, 250);
            }

            if ($usuario !== '') {
                if (!$cifrado) {
                    throw new ErrorHttp('El servidor de correo no ofrece cifrado TLS: no se envían las credenciales sin cifrar', 502);
                }
                self::orden($socket, 'AUTH LOGIN', 334);
                self::orden($socket, base64_encode($usuario), 334);
                self::orden($socket, base64_encode((string) self::$config['password']), 235);
            }

            self::orden($socket, 'MAIL FROM:<' . $remitente . '>', 250);
            foreach ($destinatarios as $direccion) {
                self::orden($socket, 'RCPT TO:<' . $direccion . '>', 250);
            }
            self::orden($socket, 'DATA', 354);
            self::orden($socket, $mensaje . '.', 250);
            // El mensaje ya fue aceptado: la despedida es cortesía y su respuesta no cambia el resultado
            fwrite($socket, "QUIT\r\n");
        } finally {
            fclose($socket);
        }
    }

    // Envía una orden (o solo lee, si $texto es null) y exige el código de respuesta esperado
    private static function orden($socket, ?string $texto, int $esperado): string
    {
        // En una conexión lenta fwrite puede aceptar solo una parte: se insiste hasta enviar el mensaje completo
        $pendiente = $texto === null ? '' : $texto . "\r\n";
        while ($pendiente !== '') {
            $enviados = fwrite($socket, $pendiente);
            if ($enviados === false || $enviados === 0) {
                throw new ErrorHttp('Se perdió la conexión con el servidor de correo', 502);
            }
            $pendiente = substr($pendiente, $enviados);
        }

        $respuesta = '';
        do {
            $linea = fgets($socket, 1024);
            if ($linea === false) {
                throw new ErrorHttp('El servidor de correo no respondió a tiempo', 504);
            }
            $respuesta .= $linea;
        } while (($linea[3] ?? ' ') === '-');

        if ((int) substr($linea, 0, 3) !== $esperado) {
            throw new ErrorHttp('El servidor de correo rechazó el envío: ' . trim($linea), 502);
        }
        return $respuesta;
    }
}
