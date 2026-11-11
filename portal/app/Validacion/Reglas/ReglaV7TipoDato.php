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

        /*
         * El diccionario incluye columnas que no son datos: la del profesional
         * («Médico/a», «Matrona/ón») o la del tipo de control. Marcarlas como
         * «valor no numérico» produce miles de avisos falsos en cada archivo
         * real.
         *
         * Se decide con la evidencia del propio archivo: si una columna no
         * trae un solo número en toda la hoja, no es una columna de datos y
         * no se le exige número.
         */
        $columnasNumericas = $this->columnasNumericas($contexto);

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
                    if (! ($columnasNumericas[$registro->hoja][$this->letra($valor['celda'])] ?? false)) {
                        continue; // columna de etiqueta, no de dato
                    }

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

    /**
     * Columnas que se comportan como datos numéricos, por hoja.
     *
     * Una columna entra si trae al menos un número. Y se descarta como
     * etiqueta solo con evidencia suficiente: tres o más textos y ningún
     * número. Con una sola celda no se puede afirmar nada, así que se valida.
     *
     * @return array<string, array<string, bool>>
     */
    private function columnasNumericas(ContextoValidacion $contexto): array
    {
        $numeros = [];
        $textos = [];

        foreach ($contexto->datos()->registros as $registro) {
            foreach ($registro->valores as $valor) {
                $vacio = trim((string) $valor['valor']) === '';

                if ($vacio) {
                    continue;
                }

                if (is_numeric($valor['valor'])) {
                    $numeros[$registro->hoja][$this->letra($valor['celda'])] = true;
                } else {
                    $columna = $this->letra($valor['celda']);
                    $textos[$registro->hoja][$columna] = ($textos[$registro->hoja][$columna] ?? 0) + 1;
                }
            }
        }

        $mapa = [];

        foreach ($textos as $hoja => $columnas) {
            foreach ($columnas as $celda => $cantidad) {
                $mapa[$hoja][$celda] = isset($numeros[$hoja][$celda]) || $cantidad < 3;
            }
        }

        foreach ($numeros as $hoja => $columnas) {
            foreach ($columnas as $celda => $_) {
                $mapa[$hoja][$celda] = true;
            }
        }

        return $mapa;
    }

    /** "B11" => "B": la columna, sin la fila. */
    private function letra(string $celda): string
    {
        return (string) preg_replace('/\d+/', '', $celda);
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