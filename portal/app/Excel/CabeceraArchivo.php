<?php

namespace App\Excel;

/**
 * Datos que el archivo declara en su cabecera.
 *
 * En el validador de escritorio se leen de celdas fijas de la hoja "NOMBRE":
 * el establecimiento concatenado desde C3:H3, el mes de C6+D6, el año de B7,
 * la serie de B17 y la versión de A9.
 */
final class CabeceraArchivo
{
    public function __construct(
        public readonly ?string $establecimiento,
        public readonly ?int $anio,
        public readonly ?int $mes,
        public readonly ?string $serie,
        public readonly ?string $version,
        public readonly string $hoja,
    ) {
    }

    public function identificable(): bool
    {
        return $this->establecimiento !== null
            && $this->anio !== null
            && $this->mes !== null
            && $this->serie !== null;
    }
}
