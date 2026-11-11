<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Error de importación de catálogos.
 *
 * Lleva código propio (APP-CAT-###) porque la Constitución exige que todo
 * error visible al usuario tenga código y acción sugerida.
 */
class CatalogoInvalidoException extends RuntimeException
{
    public function __construct(
        public readonly string $codigo,
        string $mensaje,
        public readonly string $sugerencia = '',
    ) {
        parent::__construct($mensaje);
    }

    public static function archivoFaltante(string $archivo): self
    {
        return new self(
            'APP-CAT-001',
            "Falta el archivo obligatorio «{$archivo}».",
            'Genere el snapshot con 01-TOOLS/rem-export y vuelva a subirlo completo.',
        );
    }

    public static function columnas(string $archivo, string $esperadas, string $encontradas): self
    {
        return new self(
            'APP-CAT-002',
            "Las columnas de «{$archivo}» no coinciden. Se esperaba: {$esperadas}. Se encontró: {$encontradas}.",
            'Use el exportador del proyecto; no edite los CSV a mano.',
        );
    }

    public static function codificacion(string $archivo): self
    {
        return new self(
            'APP-CAT-003',
            "El archivo «{$archivo}» no está en UTF-8 válido.",
            'Vuelva a exportarlo; no lo abra ni lo guarde con Excel.',
        );
    }

    public static function duplicado(string $archivo, string $clave, int $linea): self
    {
        return new self(
            'APP-CAT-004',
            "Clave duplicada en «{$archivo}»: {$clave} (línea {$linea}).",
            'El catálogo del Minsal no debería traer duplicados: revise el origen antes de importar.',
        );
    }

    public static function conteo(string $archivo, int $esperado, int $encontrado): self
    {
        return new self(
            'APP-CAT-005',
            "«{$archivo}» declara {$esperado} filas en el manifiesto pero trae {$encontrado}.",
            'El snapshot está incompleto o se mezclaron versiones. Genérelo de nuevo.',
        );
    }

    public static function sinSeries(): self
    {
        return new self(
            'APP-CAT-006',
            'No hay series en el snapshot.',
            'Revise que series.csv traiga los pares Serie/SerieExcel.',
        );
    }
}
