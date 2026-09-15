<?php

declare(strict_types=1);

namespace Kerico\Nucleo;

final class Paginador
{
    public const POR_PAGINA = 25;
    public const MAXIMO = 200;

    public static function pagina(array $filtros): int
    {
        $pagina = (int) ($filtros['pagina'] ?? 1);
        return $pagina < 1 ? 1 : $pagina;
    }

    public static function porPagina(array $filtros): int
    {
        $valor = (int) ($filtros['por_pagina'] ?? self::POR_PAGINA);
        if ($valor < 1) {
            return self::POR_PAGINA;
        }
        return min($valor, self::MAXIMO);
    }

    public static function orden(array $filtros, array $permitidas, string $porDefecto): string
    {
        $columna = (string) ($filtros['orden'] ?? '');
        if ($columna === '' || !array_key_exists($columna, $permitidas)) {
            $columna = $porDefecto;
        }
        $expresion = $permitidas[$columna] ?? $permitidas[$porDefecto];

        $direccion = strtoupper((string) ($filtros['direccion'] ?? 'DESC'));
        if ($direccion !== 'ASC' && $direccion !== 'DESC') {
            $direccion = 'DESC';
        }

        return $expresion . ' ' . $direccion;
    }

    public static function consultar(
        string $sqlSelect,
        string $sqlDesde,
        array $parametros,
        array $filtros,
        array $columnasOrden,
        string $ordenPorDefecto,
        callable $normalizar
    ): array {
        $pagina = (int) self::pagina($filtros);
        $porPagina = (int) self::porPagina($filtros);
        $desplazamiento = (int) (($pagina - 1) * $porPagina);

        $total = (int) Bd::valor('SELECT COUNT(*) ' . $sqlDesde, $parametros);

        $orden = self::orden($filtros, $columnasOrden, $ordenPorDefecto);
        $sql = $sqlSelect . ' ' . $sqlDesde . ' ORDER BY ' . $orden
            . ' LIMIT ' . (int) $porPagina . ' OFFSET ' . (int) $desplazamiento;

        $filas = Bd::consultar($sql, $parametros);

        return [
            'items'      => array_map($normalizar, $filas),
            'total'      => $total,
            'pagina'     => $pagina,
            'por_pagina' => $porPagina,
            'paginas'    => $porPagina > 0 ? (int) ceil($total / $porPagina) : 1,
            'orden'      => (string) ($filtros['orden'] ?? $ordenPorDefecto),
            'direccion'  => strtoupper((string) ($filtros['direccion'] ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC',
        ];
    }

    public static function desdePeticion(Peticion $peticion, array $extra = []): array
    {
        return array_merge([
            'pagina'     => $peticion->consulta('pagina', 1),
            'por_pagina' => $peticion->consulta('por_pagina', self::POR_PAGINA),
            'orden'      => $peticion->consulta('orden', ''),
            'direccion'  => $peticion->consulta('direccion', 'DESC'),
        ], $extra);
    }
}
