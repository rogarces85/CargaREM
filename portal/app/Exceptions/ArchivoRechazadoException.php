<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Archivo rechazado en la validación V0 (ENV-001 a ENV-004, ENV-029).
 *
 * El código es el del catálogo `ENV-###`, no uno nuevo: el usuario final ve
 * el mismo mensaje que vería con el validador de escritorio.
 */
class ArchivoRechazadoException extends RuntimeException
{
    public function __construct(
        public readonly string $codigo,
        string $mensaje,
    ) {
        parent::__construct($mensaje);
    }

    public static function demasiadoGrande(int $kb, int $maxKb): self
    {
        return new self(
            'ENV-001',
            "El archivo pesa {$kb} KB y el máximo permitido es {$maxKb} KB."
        );
    }

    public static function extension(string $extension, array $permitidas): self
    {
        return new self(
            'ENV-002',
            'Extensión no permitida ('.$extension.'). Use '.implode(', ', $permitidas).'.'
        );
    }

    public static function noEsExcel(string $nombre): self
    {
        return new self(
            'ENV-003',
            "«{$nombre}» no es un archivo Excel válido: la firma del contenido no corresponde."
        );
    }

    public static function duplicado(): self
    {
        return new self(
            'ENV-029',
            'Este archivo ya fue subido en este envío (mismo hash). Suba una versión corregida.'
        );
    }
}
