<?php

namespace App\Validador;

/**
 * Una regla de calidad tal como viene del archivo de configuración.
 *
 * No es una regla «ejecutable»: es el dato. El motor decide qué hacer con el
 * tipo. Se separa a propósito porque el archivo lo escribe la Unidad de
 * Estadísticas y el motor lo escribe el portal: si estuvieran mezclados, un
 * cambio de redacción en el JSON obligaría a tocar código.
 */
final class ReglaCalidad
{
    /**
     * Tipos que el motor sabe ejecutar. Un tipo fuera de esta lista aborta la
     * ejecución con su identificador (R-VAL-02): una regla ignorada en silencio
     * es peor que una ejecución fallida, porque el resultado parecería limpio.
     */
    public const TIPOS = [
        'hoja_presente',
        'requerido',
        'tipo_dato',
        'rango',
        'dominio',
        'unicidad',
    ];

    /**
     * @param  array<string, mixed>  $parametros
     */
    public function __construct(
        public readonly string $id,
        public readonly string $tipo,
        public readonly string $severidad,
        public readonly string $mensaje,
        public readonly array $parametros,
    ) {
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public static function desdeArreglo(array $datos, int $indice): self
    {
        $id = trim((string) ($datos['id'] ?? ''));

        if ($id === '') {
            throw new \RuntimeException("La regla #{$indice} del archivo del Validador no tiene «id».");
        }

        $tipo = trim((string) ($datos['tipo'] ?? ''));

        if ($tipo === '') {
            throw new \RuntimeException("La regla {$id} no declara «tipo».");
        }

        $severidad = (string) ($datos['severidad'] ?? 'advertencia');

        if (! in_array($severidad, ['bloqueante', 'advertencia', 'info'], true)) {
            throw new \RuntimeException("La regla {$id} declara una severidad desconocida: «{$severidad}».");
        }

        $mensaje = trim((string) ($datos['mensaje'] ?? ''));

        if ($mensaje === '') {
            throw new \RuntimeException("La regla {$id} no tiene mensaje: el hallazgo no se podría explicar.");
        }

        return new self($id, $tipo, $severidad, $mensaje, $datos);
    }

    public function esBloqueante(): bool
    {
        return $this->severidad === 'bloqueante';
    }

    public function hoja(): ?string
    {
        $hoja = $this->parametros['hoja'] ?? null;

        return $hoja !== null && $hoja !== '' ? (string) $hoja : null;
    }

    public function columna(): ?string
    {
        $columna = $this->parametros['columna'] ?? null;

        return $columna !== null && $columna !== '' ? strtoupper((string) $columna) : null;
    }

    public function desdeFila(): int
    {
        return max(1, (int) ($this->parametros['desde_fila'] ?? 2));
    }

    public function hastaFila(): ?int
    {
        $hasta = $this->parametros['hasta_fila'] ?? null;

        return $hasta !== null && $hasta !== '' ? max(1, (int) $hasta) : null;
    }
}
