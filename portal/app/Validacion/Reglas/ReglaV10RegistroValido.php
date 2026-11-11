<?php

namespace App\Validacion\Reglas;

use App\Validacion\ContextoValidacion;
use App\Validacion\Hallazgo;
use App\Validacion\ReglaValidacion;
use App\Validacion\Severidad;

/**
 * V10 — al menos un registro válido (ENV-024).
 *
 * Un archivo sin un solo valor es un archivo vacío con nombre correcto. Es
 * bloqueante: no hay nada que consolidar y aceptarlo ensuciaría la matriz del
 * período.
 */
final class ReglaV10RegistroValido implements ReglaValidacion
{
    public function etapa(): string
    {
        return 'V10';
    }

    public function evaluar(ContextoValidacion $contexto): array
    {
        $datos = $contexto->datos();

        if (! $datos->leyo || $datos->registros === []) {
            return []; // V3 o V5 ya reportaron por qué no hay datos que leer
        }

        if ($datos->hayAlgunDato()) {
            return [];
        }

        return [new Hallazgo(
            'V10', 'ENV-024', Severidad::BLOQUEANTE,
            'El archivo no tiene ningún registro con datos.',
            null, null, null, 'R-V10-01'
        )];
    }
}