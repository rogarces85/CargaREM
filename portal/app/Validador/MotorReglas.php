<?php

namespace App\Validador;

/**
 * Evaluación de una regla contra un libro (RF-040).
 *
 * Toda regla del catálogo está planteada **en positivo**: describe la
 * situación que se espera encontrar. Si la comparación no se cumple, se
 * levanta un hallazgo.
 *
 * Orden de evaluación, y cada paso existe por un falso positivo real:
 *
 * 1. Resolver las dos expresiones (celda ausente o vacía = 0).
 * 2. Condición previa incumplida con `omitir_si_condicion_no_cumple` → OMITIDA.
 * 3. Regla con `omitir_si_sin_actividad` y lado izquierdo en 0 → OMITIDA: le
 *    exige registro al establecimiento titular de la sección, y un mes sin
 *    actividad no es un hallazgo.
 * 4. Ambos lados en 0 y ambos leídos del libro → OMITIDA: no hay datos que
 *    comparar, y exigir el cuadre marcaría como error toda sección sin
 *    actividad. **No** aplica si el lado derecho es un literal: en `F11 == 0`
 *    el cero es justamente lo que la regla comprueba.
 * 5. Comparar. Verdadero → CUMPLE. Falso → HALLAZGO.
 *
 * El paso 4 es el que más se pierde al reimplementar un motor como este: sin
 * él, un servicio que no hizo ninguna de las actividades de una sección
 * aparece con decenas de hallazgos el primer mes.
 */
final class MotorReglas
{
    /** @var array<string, Expresion|ExpresionInvalida> */
    private array $cacheExpresiones = [];

    /**
     * ¿La regla corresponde a este establecimiento?
     *
     * Traduce la columna APLICA del Excel maestro. Es un filtro puro: una regla
     * fuera de alcance no se evalúa, no cuenta para el porcentaje y no aparece
     * en el reporte.
     */
    public function aplica(ReglaCalidad $regla, ContextoEstablecimiento $contexto): bool
    {
        // Excluir gana sobre cualquier inclusión, por código o por tipo.
        if ($regla->excluirA !== null && in_array($contexto->codigo, $regla->excluirA, true)) {
            return false;
        }

        if ($regla->excluirTipo !== null && in_array($contexto->tipo, $regla->excluirTipo, true)) {
            return false;
        }

        $limitaPorCodigo = $regla->aplicarA !== null && $regla->aplicarA !== [];
        $limitaPorTipo = $regla->aplicarATipo !== null && $regla->aplicarATipo !== [];

        if (! $limitaPorCodigo && ! $limitaPorTipo) {
            return true;
        }

        // Los dos criterios se combinan con OR: una regla dirigida a
        // «HQUI,HMSJ,SAPU,SUR» alcanza tanto a esos hospitales por código como
        // a cualquier SAPU o SUR por tipo.
        $porCodigo = $limitaPorCodigo && in_array($contexto->codigo, $regla->aplicarA, true);
        $porTipo = $limitaPorTipo && in_array($contexto->tipo, $regla->aplicarATipo, true);

        return $porCodigo || $porTipo;
    }

    /** Evalúa una regla y devuelve su resultado, sin lanzar por errores de expresión. */
    public function evaluar(ReglaCalidad $regla, LectorLibro $libro): ResultadoRegla
    {
        $hoja1 = $regla->hoja;
        $hoja2 = $regla->hoja2 ?? $regla->hoja;

        $ast1 = $this->compilar($regla->expresion1);
        $ast2 = $this->compilarLado2($regla->expresion2);

        $base = [
            'reglaId'    => $regla->id,
            'severidad'  => $regla->severidad,
            'mensaje'    => $regla->mensaje,
            'hoja'       => $hoja1,
            'expresion1' => $regla->expresion1,
            'operador'   => $regla->operador,
            'expresion2' => $regla->expresion2ComoTexto(),
            'hoja2'      => $regla->hoja2,
        ];

        if ($ast1 instanceof ExpresionInvalida || $ast2 instanceof ExpresionInvalida) {
            $error = $ast1 instanceof ExpresionInvalida ? $ast1 : $ast2;

            return new ResultadoRegla(
                ...$base,
                estado: ResultadoRegla::ERROR_TECNICO,
                valor1: null,
                valor2: null,
                evidencia: 'No se pudo interpretar la regla: ' . $error->getMessage(),
            );
        }

        $celdas = array_values(array_unique(array_merge(
            $ast1->referencias($hoja1),
            $ast2->referencias($hoja2),
        )));

        try {
            $valor1 = $this->evaluarNodo($ast1->raiz, $libro, $hoja1);
            $valor2 = $this->evaluarNodo($ast2->raiz, $libro, $hoja2);
        } catch (\Throwable $e) {
            return new ResultadoRegla(
                ...$base,
                estado: ResultadoRegla::ERROR_TECNICO,
                valor1: null,
                valor2: null,
                celdas: $celdas,
                evidencia: 'Falla al leer el archivo: ' . $e->getMessage(),
            );
        }

        $comun = [...$base, 'valor1' => $valor1, 'valor2' => $valor2, 'celdas' => $celdas];

        // 2. Condición previa.
        if ($regla->condicionPrevia !== null) {
            $astCondicion = $this->compilar((string) $regla->condicionPrevia['expresion']);
            $valorCondicion = $astCondicion instanceof ExpresionInvalida
                ? 0.0
                : $this->evaluarNodo($astCondicion->raiz, $libro, $hoja1);

            $seCumple = $this->comparar(
                $valorCondicion,
                (string) $regla->condicionPrevia['operador'],
                (float) $regla->condicionPrevia['valor'],
            );

            if (! $seCumple && $regla->omitirSiCondicionNoCumple) {
                return new ResultadoRegla(
                    ...$comun,
                    estado: ResultadoRegla::OMITIDA,
                    motivoOmision: ResultadoRegla::OMISION_CONDICION_PREVIA,
                    evidencia: sprintf(
                        'No se evalúa: la condición previa %s %s %s no se cumple (vale %s), así que no hay datos que comparar.',
                        $regla->condicionPrevia['expresion'],
                        $regla->condicionPrevia['operador'],
                        $regla->condicionPrevia['valor'],
                        $valorCondicion,
                    ),
                );
            }
        }

        // 3. Sección sin actividad.
        if ($regla->omitirSiSinActividad && $valor1 === 0.0) {
            return new ResultadoRegla(
                ...$comun,
                estado: ResultadoRegla::OMITIDA,
                motivoOmision: ResultadoRegla::OMISION_SECCION_SIN_ACTIVIDAD,
                evidencia: 'No se evalúa: esta sección le corresponde a este establecimiento, pero no trae registro este mes.',
            );
        }

        // 4. Sin datos en ninguno de los dos lados.
        $comparaContraElLibro = $ast2->raiz->tipo !== 'literal';

        if ($comparaContraElLibro && $valor1 === 0.0 && $valor2 === 0.0) {
            return new ResultadoRegla(
                ...$comun,
                estado: ResultadoRegla::OMITIDA,
                diferencia: 0.0,
                motivoOmision: ResultadoRegla::OMISION_AMBOS_CERO,
                evidencia: 'No se evalúa: ambos lados de la comparación están en 0 o vacíos.',
            );
        }

        // 5. Comparación.
        $cumple = $this->comparar($valor1, $regla->operador, $valor2);

        return new ResultadoRegla(
            ...$comun,
            estado: $cumple ? ResultadoRegla::CUMPLE : ResultadoRegla::HALLAZGO,
            diferencia: $valor1 - $valor2,
            evidencia: sprintf(
                '%s = %s; %s = %s. Se esperaba %s %s %s.',
                $regla->expresion1,
                self::formatear($valor1),
                $regla->expresion2ComoTexto(),
                self::formatear($valor2),
                self::formatear($valor1),
                $regla->operador,
                self::formatear($valor2),
            ),
        );
    }

    public function comparar(float $izq, string $operador, float $der): bool
    {
        return match ($operador) {
            '==' => abs($izq - $der) < 0.0001,
            '!=' => abs($izq - $der) >= 0.0001,
            '>'  => $izq > $der,
            '<'  => $izq < $der,
            '>=' => $izq >= $der - 0.0001,
            '<=' => $izq <= $der + 0.0001,
            default => false,
        };
    }

    /** Evalúa un nodo del árbol contra el libro. */
    public function evaluarNodo(NodoExpresion $nodo, LectorLibro $libro, string $hojaPorDefecto): float
    {
        return match ($nodo->tipo) {
            'literal' => $nodo->valor ?? 0.0,

            'celda' => LectorSpreadsheet::aNumero(
                $libro->celda($nodo->hoja ?? $hojaPorDefecto, (string) $nodo->ref)
            ),

            'rango' => $libro->sumaRango(
                $nodo->hoja ?? $hojaPorDefecto,
                (string) $nodo->desde,
                (string) $nodo->hasta,
            ),

            'suma' => array_reduce(
                $nodo->argumentos ?? [],
                fn (float $total, NodoExpresion $arg) => $total + $this->evaluarNodo($arg, $libro, $hojaPorDefecto),
                0.0,
            ),

            'negacion' => -$this->evaluarNodo($nodo->argumento, $libro, $hojaPorDefecto),

            'binario' => match ($nodo->operador) {
                '+' => $this->evaluarNodo($nodo->izq, $libro, $hojaPorDefecto)
                    + $this->evaluarNodo($nodo->der, $libro, $hojaPorDefecto),
                '-' => $this->evaluarNodo($nodo->izq, $libro, $hojaPorDefecto)
                    - $this->evaluarNodo($nodo->der, $libro, $hojaPorDefecto),
                '*' => $this->evaluarNodo($nodo->izq, $libro, $hojaPorDefecto)
                    * $this->evaluarNodo($nodo->der, $libro, $hojaPorDefecto),
                // División por cero = 0: no propaga NaN ni Infinity al resultado.
                '/' => ($der = $this->evaluarNodo($nodo->der, $libro, $hojaPorDefecto)) === 0.0
                    ? 0.0
                    : $this->evaluarNodo($nodo->izq, $libro, $hojaPorDefecto) / $der,
                default => 0.0,
            },

            default => 0.0,
        };
    }

    private function compilar(string $expresion): Expresion|ExpresionInvalida
    {
        if (isset($this->cacheExpresiones[$expresion])) {
            return $this->cacheExpresiones[$expresion];
        }

        try {
            $compilada = Expresion::compilar($expresion);
        } catch (ExpresionInvalida $e) {
            $compilada = $e;
        }

        return $this->cacheExpresiones[$expresion] = $compilada;
    }

    /** `expresion_2` admite tanto un número literal como una expresión de celdas. */
    private function compilarLado2(string|float|int $valor): Expresion|ExpresionInvalida
    {
        if (is_int($valor) || is_float($valor)) {
            return Expresion::literal((float) $valor);
        }

        $texto = trim($valor);

        return $texto === '' ? Expresion::literal(0.0) : $this->compilar($texto);
    }

    private static function formatear(float $valor): string
    {
        if (abs($valor - round($valor)) < 0.0001) {
            return (string) (int) round($valor);
        }

        return rtrim(rtrim(number_format($valor, 2, '.', ''), '0'), '.');
    }
}
