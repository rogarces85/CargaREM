<?php

namespace App\Validacion\Reglas;

use App\Excel\LectorCabecera;
use App\Validacion\ContextoValidacion;
use App\Validacion\Hallazgo;
use App\Validacion\ReglaValidacion;
use App\Validacion\Severidad;
use Throwable;

/**
 * V2 — la cabecera interna del archivo (ENV-009 a ENV-013).
 *
 * Detecta qué declara el Excel sobre sí mismo y lo compara con el envío. Un
 * archivo puede llamarse «123100A09.xlsx» y decir por dentro que es de otro
 * establecimiento: el nombre es una promesa, la cabecera es el dato.
 */
final class ReglaV2Cabecera implements ReglaValidacion
{
    public function __construct(private readonly LectorCabecera $lector = new LectorCabecera())
    {
    }

    public function etapa(): string
    {
        return 'V2';
    }

    public function evaluar(ContextoValidacion $contexto): array
    {
        try {
            $cabecera = $this->lector->leer($contexto->rutaAbsoluta, $contexto->plantilla);
        } catch (Throwable $e) {
            report($e);

            return [new Hallazgo(
                'V2', 'ENV-009', Severidad::BLOQUEANTE,
                'No se pudo leer la cabecera del archivo: no se reconoce la plantilla.',
                null, null, null, 'R-V2-00'
            )];
        }

        if (! $cabecera->identificable()) {
            return [new Hallazgo(
                'V2', 'ENV-009', Severidad::BLOQUEANTE,
                'No se pudo identificar la cabecera. Debe traer establecimiento, serie, año y mes en las primeras filas.',
                $cabecera->hoja, null, null, 'R-V2-01'
            )];
        }

        $hallazgos = [];

        if (strtoupper((string) $cabecera->serie) !== strtoupper($contexto->serie->codigo)) {
            $hallazgos[] = new Hallazgo(
                'V2', 'ENV-010', Severidad::BLOQUEANTE,
                "La serie declarada en el archivo ({$cabecera->serie}) no coincide con la serie subida ({$contexto->serie->codigo}).",
                $cabecera->hoja, null, (string) $cabecera->serie, 'R-V2-02'
            );
        }

        if ($cabecera->anio !== $contexto->anio()) {
            $hallazgos[] = new Hallazgo(
                'V2', 'ENV-011', Severidad::BLOQUEANTE,
                "El año del archivo ({$cabecera->anio}) no coincide con el período ({$contexto->anio()}).",
                $cabecera->hoja, null, (string) $cabecera->anio, 'R-V2-03'
            );
        }

        if ($cabecera->mes !== $contexto->mes()) {
            $hallazgos[] = new Hallazgo(
                'V2', 'ENV-012', Severidad::BLOQUEANTE,
                "El mes del archivo ({$cabecera->mes}) no coincide con el período ({$contexto->mes()}).",
                $cabecera->hoja, null, (string) $cabecera->mes, 'R-V2-04'
            );
        }

        if ($cabecera->establecimiento !== $contexto->codigoEstablecimiento()) {
            $hallazgos[] = new Hallazgo(
                'V2', 'ENV-013', Severidad::BLOQUEANTE,
                "El establecimiento del archivo ({$cabecera->establecimiento}) no corresponde al del envío ({$contexto->codigoEstablecimiento()}).",
                $cabecera->hoja, null, (string) $cabecera->establecimiento, 'R-V2-05'
            );
        }

        return $hallazgos;
    }
}
