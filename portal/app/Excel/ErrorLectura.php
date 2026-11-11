<?php

namespace App\Excel;

/**
 * Un rango que no se pudo leer (ENV-019).
 */
final class ErrorLectura
{
    public function __construct(
        public readonly string $hoja,
        public readonly ?string $codigo,
        public readonly string $detalle,
    ) {
    }
}