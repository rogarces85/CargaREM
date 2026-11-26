<?php

namespace App\Validador;

use RuntimeException;

/**
 * El archivo no se puede validar (RF-040).
 *
 * Es distinto de un hallazgo: aquí no hay resultado que mostrar, solo un motivo
 * por el que el archivo no entra —nombre mal formado, libro corrupto, hoja
 * obligatoria ausente o establecimiento fuera de la nómina—.
 */
final class ArchivoNoValidable extends RuntimeException
{
    /** @param  list<string>  $detalles */
    public function __construct(string $mensaje, public readonly array $detalles = [])
    {
        parent::__construct($mensaje);
    }
}
