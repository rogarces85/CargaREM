<?php

namespace App\Validacion\Reglas;

use App\Models\EnvioArchivo;
use App\Validacion\ContextoValidacion;
use App\Validacion\Hallazgo;
use App\Validacion\ReglaValidacion;
use App\Validacion\Severidad;

/**
 * V11 — completitud del envío (ENV-025).
 *
 * Es la única regla que no mira el archivo sino el conjunto: compara las
 * series que la matriz exige al establecimiento con las que efectivamente
 * llegaron. Un envío con siete series donde faltan dos no está completo
 * aunque cada archivo suelto esté impecable.
 *
 * Corre siempre, sin parada de cadena: si un archivo falló, las demás series
 * igual se pueden contar.
 */
final class ReglaV11Completitud implements ReglaValidacion
{
    public function etapa(): string
    {
        return 'V11';
    }

    public function evaluar(ContextoValidacion $contexto): array
    {
        $envio = $contexto->envio;

        // Series que la matriz exige y que hoy están vigentes.
        $exigidas = $envio->establecimiento->series()
            ->wherePivot('obligatoria', true)
            ->where(function ($q) {
                $q->whereNull('establecimiento_serie.vigente_desde')
                    ->orWhere('establecimiento_serie.vigente_desde', '<=', now()->toDateString());
            })
            ->where(function ($q) {
                $q->whereNull('establecimiento_serie.vigente_hasta')
                    ->orWhere('establecimiento_serie.vigente_hasta', '>=', now()->toDateString());
            })
            ->get();

        if ($exigidas->isEmpty()) {
            return [new Hallazgo(
                'V11', 'ENV-025', Severidad::ADVERTENCIA,
                'La matriz no exige ninguna serie a este establecimiento: no se puede verificar la completitud.',
                null, null, null, 'R-V11-00'
            )];
        }

        $vigentes = $envio->archivosVigentes()->get()->keyBy('serie_id');
        $hallazgos = [];

        foreach ($exigidas as $serie) {
            $archivo = $vigentes->get($serie->id);

            if ($archivo === null) {
                $hallazgos[] = new Hallazgo(
                    'V11', 'ENV-025', Severidad::BLOQUEANTE,
                    "Falta el archivo de la serie {$serie->codigo}, que la matriz exige para este establecimiento.",
                    null, null, $serie->codigo, 'R-V11-01'
                );

                continue;
            }

            // Hay archivo, pero no sirve. El catálogo no tiene un código
            // aparte para «llegó mal»: ENV-025 es «faltan series obligatorias»,
            // y una serie con errores bloqueantes es una serie que falta a
            // efectos de la consolidación. El mensaje distingue los dos casos.
            if ($archivo->estado === EnvioArchivo::CON_ERRORES) {
                $hallazgos[] = new Hallazgo(
                    'V11', 'ENV-025', Severidad::BLOQUEANTE,
                    "La serie {$serie->codigo} está exigida y su archivo tiene errores bloqueantes: cuenta como faltante.",
                    null, null, $serie->codigo, 'R-V11-02'
                );
            }
        }

        /*
         * Las series que no exige la matriz no generan hallazgo: el catálogo
         * de códigos no contempla ese caso y ENV-026 está reservado para otra
         * cosa («ya existe una carga aprobada para el período»). Se ven en la
         * matriz de completitud, que es su lugar.
         */

        return $hallazgos;
    }
}
