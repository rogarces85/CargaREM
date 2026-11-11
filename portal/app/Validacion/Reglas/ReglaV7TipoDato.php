<?php

namespace App\Validacion\Reglas;

use App\Validacion\ContextoValidacion;
use App\Validacion\Hallazgo;
use App\Validacion\ReglaValidacion;
use App\Validacion\Severidad;

/**
 * V7 — tipo y precisión del dato (ENV-020, ENV-021, ENV-031).
 *
 * La regla de decimales viene del validador de escritorio: solo las hojas A09
 * y A21 y las series BS y D aceptan un decimal; el resto exige enteros.
 *
 * ENV-031: una celda con fórmula cuyo resultado no está calculado. El archivo
 * se generó sin recalcular, así que el valor real no está en el disco.
 */
final class ReglaV7TipoDato implements ReglaValidacion
{
    private const MAX_POR_CODIGO = 15;

    private const HOJAS_CON_DECIMAL = ['A09', 'A21'];

    private const SERIES_CON_DECIMAL = ['BS', 'D'];

    public function etapa(): string
    {
        return 'V7';
    }

    public function evaluar(ContextoValidacion $contexto): array
    {
        $datos = $contexto->datos();
        $hallazgos = [];

        $aceptaDecimal = $this->aceptaDecimal($contexto);
        $porCodigo = [];

        foreach ($datos->registros as $registro) {
            foreach ($registro->valores as $valor) {
                if (trim((string) $valor['valor']) === '') {
                    continue;
                }

                if ($valor['formula'] && $valor['calculado'] === null) {
                    $hallazgos[] = new Hallazgo(
                        'V7', 'ENV-031', Severidad::ADVERTENCIA,
                        "La celda {$valor['celda']} tiene una fórmula sin valor calculado. Guarde el archivo con Excel antes de subirlo.",
                        $registro->hoja, $valor['celda'], null, 'R-V7-01'
                    );

                    continue;
                }

                if (! is_numeric($valor['valor'])) {
                    $clave = $registro->codigo ?? $registro->hoja;

                    if (($porCodigo[$clave] ?? 0) < self::MAX_POR_CODIGO) {
                        $hallazgos[] = new Hallazgo(
                            'V7', 'ENV-020', Severidad::ADVERTENCIA,
                            "El valor de la celda {$valor['celda']} no es numérico: se registra vacío.",
                            $registro->hoja, $valor['celda'], (string) $valor['valor'], 'R-V7-02'
                        );
                        $porCodigo[$clave] = ($porCodigo[$clave] ?? 0) + 1;
                    }

                    continue;
                }

                if (! $aceptaDecimal && $this->tieneDecimal((string) $valor['valor'])) {
                    $hallazgos[] = new Hallazgo(
                        'V7', 'ENV-021', Severidad::ADVERTENCIA,
                        "La celda {$valor['celda']} tiene decimales y esta hoja o serie exige enteros.",
                        $registro->hoja, $valor['celda'], (string) $valor['valor'], 'R-V7-03'
                    );
                }
            }
        }

        return $hallazgos;
    }

    private function tieneDecimal(string $valor): bool
    {
        $normalizado = str_replace(',', '.', $valor);

        return str_contains($normalizado, '.') && fmod((float) $normalizado, 1.0) !== 0.0;
    }

    private function aceptaDecimal(ContextoValidacion $contexto): bool
    {
        if (in_array($contexto->serie->codigo, self::SERIES_CON_DECIMAL, true)) {
            return true;
        }

        foreach ($contexto->datos()->registros as $registro) {
            if (in_array(mb_strtoupper($registro->hoja), self::HOJAS_CON_DECIMAL, true)) {
                return true;
            }
        }

        return false;
    }
}