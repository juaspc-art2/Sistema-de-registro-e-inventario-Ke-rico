<?php

declare(strict_types=1);

namespace Kerico\Nucleo;

final class Sanitizador
{
    public static function texto(string $valor): string
    {
        $limpio = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $valor) ?? '';
        $limpio = preg_replace('#<\s*(script|style|iframe|object|embed|link|meta)\b[\s\S]*?(?:</\s*\1\s*>|$)#i', '', $limpio) ?? '';
        $limpio = strip_tags($limpio);
        $limpio = preg_replace('/\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $limpio) ?? '';
        $limpio = preg_replace('/javascript\s*:/i', '', $limpio) ?? '';
        return trim($limpio);
    }

    public static function celdaCsv(string $valor): string
    {
        $limpio = self::texto($valor);
        if ($limpio !== '' && in_array($limpio[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'" . $limpio;
        }
        return $limpio;
    }

    public static function html(string $valor): string
    {
        return htmlspecialchars($valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
