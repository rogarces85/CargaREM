<?php

namespace App\Validacion\Reglas;

use App\Validacion\ContextoValidacion;
use App\Validacion\Hallazgo;
use App\Validacion\ReglaValidacion;
use App\Validacion\Severidad;

/**
 * V9 — prestaciones repetidas dentro del archivo (ENV-023).
 *
 * Si el diccionario declara la misma prestación dos veces en la misma hoja,
 * el archivo trae dos filas con el mismo código y no se sabe cuál vale. Es
 * advertencia porque el dato existe; el revisor decide cuál se conserva.
 */
final class ReglaV9Duplicados implements ReglaValidacion
{
    public function etapa(): string
    {
        return 'V9';
    }

    public function evaluar(ContextoValidacion $contexto): array
    {
        $vistos = [];
        $hallazgos = [];

        foreach ($contexto->datos()->registros as $registro) {
            if ($registro->codigo === null) {
                continue;
            }

            $clave = $registro->hoja.'|'.$registro->codigo;

            if (isset($vistos[$clave])) {
                $hallazgos[] = new Hallazgo(
                    'V9', 'ENV-023', Severidad::ADVERTENCIA,
                    "La prestación {$registro->codigo} aparece más de una vez en la hoja «{$registro->hoja}».",
                    $registro->hoja, (string) $registro->linea, $registro->codigo, 'R-V9-01'
                );

                continue;
            }

            $vistos[$clave] = true;
        }

        return $hallazgos;
    }
}