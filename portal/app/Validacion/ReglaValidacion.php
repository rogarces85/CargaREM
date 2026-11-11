<?php

namespace App\Validacion;

/**
 * Una regla del pipeline V0–V11 (T-F2-12).
 *
 * Cada regla conoce su etapa y devuelve los hallazgos que encuentra. No
 * escribe en base de datos: de eso se encarga `ValidadorArchivo`, que así
 * puede probarse sin tocar el almacén.
 */
interface ReglaValidacion
{
    /** Etapa del pipeline: V0 … V11. */
    public function etapa(): string;

    /** @return list<Hallazgo> */
    public function evaluar(ContextoValidacion $contexto): array;
}