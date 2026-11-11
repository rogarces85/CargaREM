<?php

namespace App\Validacion\Reglas;

use App\Validacion\ContextoValidacion;
use App\Validacion\Hallazgo;
use App\Validacion\ReglaValidacion;
use App\Validacion\Severidad;

/**
 * V6 — lectura de los datos (ENV-018, ENV-019).
 *
 * ENV-019: un rango del diccionario que no se puede leer. Es bloqueante
 * porque sin ese rango la validación de la hoja queda incompleta y no se
 * puede afirmar nada sobre ella.
 *
 * ENV-018: prestación sin datos. Es advertencia: puede ser un servicio que
 * ese mes no se prestó, y quien revisa decide.
 */
final class ReglaV6Datos implements ReglaValidacion
{
    private const MAX_HALLAZGOS = 40;

    public function etapa(): string
    {
        return 'V6';
    }

    public function evaluar(ContextoValidacion $contexto): array
    {
        $datos = $contexto->datos();
        $hallazgos = [];

        foreach ($datos->errores as $error) {
            $hallazgos[] = new Hallazgo(
                'V6', 'ENV-019', Severidad::BLOQUEANTE,
                "No se pudo leer un rango de la hoja «{$error->hoja}». {$error->detalle}",
                $error->hoja, null, $error->codigo, 'R-V6-01'
            );
        }

        $sinDatos = 0;
        $mostrados = 0;

        foreach ($datos->registros as $registro) {
            if ($registro->tieneDatos()) {
                continue;
            }

            $sinDatos++;

            if ($mostrados < self::MAX_HALLAZGOS) {
                $hallazgos[] = new Hallazgo(
                    'V6', 'ENV-018', Severidad::ADVERTENCIA,
                    "La prestación {$registro->codigo} no tiene datos en la hoja «{$registro->hoja}».",
                    $registro->hoja, null, $registro->codigo, 'R-V6-02'
                );
                $mostrados++;
            }
        }

        if ($sinDatos > $mostrados) {
            $hallazgos[] = new Hallazgo(
                'V6', 'ENV-018', Severidad::ADVERTENCIA,
                'Hay '.($sinDatos - $mostrados).' prestaciones más sin datos que no se detallan.',
                null, null, null, 'R-V6-03'
            );
        }

        return $hallazgos;
    }
}