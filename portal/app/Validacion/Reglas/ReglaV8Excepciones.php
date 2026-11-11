<?php

namespace App\Validacion\Reglas;

use App\Models\Excepcion;
use App\Validacion\ContextoValidacion;
use App\Validacion\Hallazgo;
use App\Validacion\ReglaValidacion;
use App\Validacion\Severidad;

/**
 * V8 — columnas que no aplican a una prestación (ENV-022).
 *
 * `Excep` y `colexcep` del Minsal marcan celdas que deben venir vacías para
 * una prestación concreta. Si traen valor, el archivo está informando algo que
 * no corresponde.
 *
 * La semántica exacta de `colexcep` sigue sin confirmar (P6), así que se
 * aceptan las dos formas que aparecen en los datos: letra de columna ("E") o
 * número de columna dentro del rango (5). Se resuelve por forma, no por
 * posición, para no fallar en silencio si el Minsal cambia.
 */
final class ReglaV8Excepciones implements ReglaValidacion
{
    public function etapa(): string
    {
        return 'V8';
    }

    public function evaluar(ContextoValidacion $contexto): array
    {
        $serieInterna = $contexto->plantilla?->serie_interna;

        if ($serieInterna === null) {
            return [];
        }

        $excepciones = Excepcion::query()
            ->where('snapshot_id', $contexto->snapshotId)
            ->where('anio', $contexto->anio())
            ->where('serie', $serieInterna)
            ->whereNotNull('codigo_prestacion')
            ->whereNotNull('columna')
            ->get();

        if ($excepciones->isEmpty()) {
            return [];
        }

        $porPrestacion = $excepciones->groupBy(fn (Excepcion $e) => $e->hoja.'|'.$e->codigo_prestacion);

        $hallazgos = [];

        foreach ($contexto->datos()->registros as $registro) {
            $clave = $registro->hoja.'|'.$registro->codigo;
            $aplicables = $porPrestacion->get($clave);

            if ($aplicables === null) {
                continue;
            }

            foreach ($aplicables as $excepcion) {
                $columna = trim((string) $excepcion->columna);

                foreach ($registro->valores as $indice => $valor) {
                    if (trim((string) $valor['valor']) === '') {
                        continue;
                    }

                    $coincide = ctype_alpha($columna)
                        ? strtoupper(substr($valor['celda'], 0, -strlen((string) $registro->linea))) === strtoupper($columna)
                        : $indice + 1 === (int) $columna;

                    if (! $coincide) {
                        continue;
                    }

                    $hallazgos[] = new Hallazgo(
                        'V8', 'ENV-022', Severidad::ADVERTENCIA,
                        "La columna {$columna} no aplica a la prestación {$registro->codigo} y trae valor.",
                        $registro->hoja, $valor['celda'], (string) $valor['valor'], 'R-V8-01'
                    );
                }
            }
        }

        return $hallazgos;
    }
}
