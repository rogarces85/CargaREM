<?php

namespace App\Validador;

/**
 * Contexto de un establecimiento para decidir el alcance de una regla.
 *
 * `tipo` es obligatorio a propósito: sin él, las reglas dirigidas a un tipo de
 * recinto quedarían fuera de alcance en silencio. El motor rechaza el archivo
 * antes de llegar aquí si el código no está en la nómina del Servicio.
 */
final class ContextoEstablecimiento
{
    public function __construct(
        public readonly string $codigo,
        public readonly string $tipo,
    ) {
    }
}
