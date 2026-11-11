<?php

namespace App\Validacion\Reglas;

use App\Models\Serie;
use App\Validacion\ContextoValidacion;
use App\Validacion\Hallazgo;
use App\Validacion\ReglaValidacion;
use App\Validacion\Severidad;

/**
 * V1 — el nombre del archivo (ENV-005 a ENV-008).
 *
 * Convención del Minsal: `CODIGO` + `SERIE` + `MES` (dos dígitos).
 * Ejemplo: `123100A09.xlsx`.
 *
 * Se aceptan sufijos de corrección —`(1)`, `_corregido`, ` - copia`— porque
 * los establecimientos los usan y el ValidadorRem los tolera; rechazarlos
 * convertiría una corrección legítima en un error de forma.
 */
final class ReglaV1Nombre implements ReglaValidacion
{
    public function etapa(): string
    {
        return 'V1';
    }

    public function evaluar(ContextoValidacion $contexto): array
    {
        $nombre = (string) $contexto->archivo->nombre_original;
        $sinExtension = pathinfo($nombre, PATHINFO_FILENAME);

        // Sufijos tolerados, quitados antes de analizar.
        $base = preg_replace('/\s*[\(\[]\d+[\)\]]\s*$|([\s_-]+corregid[oa])\s*$|([\s_-]+copia)\s*$/i', '', $sinExtension);
        $base = trim((string) $base);

        if (! preg_match('/^(\d{5,6})([A-Za-z]{1,2})(\d{2})$/', $base, $partes)) {
            return [new Hallazgo(
                'V1', 'ENV-005', Severidad::BLOQUEANTE,
                'El nombre no sigue el formato código–serie–mes (ej.: 123100A09.xlsx).',
                null, null, $nombre, 'R-V1-01'
            )];
        }

        [, $codigo, $serieTexto, $mesTexto] = $partes;
        $hallazgos = [];

        $serie = Serie::where('codigo', strtoupper($serieTexto))->first();

        if ($serie === null) {
            $hallazgos[] = new Hallazgo(
                'V1', 'ENV-006', Severidad::BLOQUEANTE,
                'La serie del nombre no existe en el catálogo.',
                null, null, $serieTexto, 'R-V1-02'
            );
        }

        if ((int) $mesTexto !== $contexto->mes()) {
            $hallazgos[] = new Hallazgo(
                'V1', 'ENV-007', Severidad::BLOQUEANTE,
                "El mes del nombre ({$mesTexto}) no coincide con el período del envío ({$contexto->mes()}).",
                null, null, $mesTexto, 'R-V1-03'
            );
        }

        if ($codigo !== $contexto->codigoEstablecimiento()) {
            $hallazgos[] = new Hallazgo(
                'V1', 'ENV-008', Severidad::BLOQUEANTE,
                'El código del nombre no corresponde al establecimiento del envío.',
                null, null, $codigo, 'R-V1-04'
            );
        }

        return $hallazgos;
    }
}
