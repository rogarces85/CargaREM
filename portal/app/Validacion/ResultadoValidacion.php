<?php

namespace App\Validacion;

/**
 * Resultado de validar un archivo.
 */
final class ResultadoValidacion
{
    /** @param list<Hallazgo> $hallazgos */
    public function __construct(
        public readonly array $hallazgos,
        public readonly string $estado,
    ) {
    }

    public function bloqueantes(): int
    {
        return count(array_filter($this->hallazgos, fn (Hallazgo $h) => $h->esBloqueante()));
    }

    public function advertencias(): int
    {
        return count($this->hallazgos) - $this->bloqueantes();
    }

    public function esValido(): bool
    {
        return $this->bloqueantes() === 0;
    }
}