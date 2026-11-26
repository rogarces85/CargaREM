<?php

namespace App\Validador;

/**
 * Expresión aritmética de una regla de calidad (RF-040).
 *
 * El Excel maestro de la Unidad escribe las reglas con expresiones, no con
 * «celda X debe ser mayor que cero». Ejemplos reales del catálogo:
 *
 *     F11
 *     C19+C20+C21+C22+C23+C24+C25+C26+F36+F37+F38
 *     SUM(C25:C58)
 *     SUM(H17:U17)+SUM(V22:AG22)-C38
 *     B60*4
 *     C12+(F12-G12)
 *
 * Esto es un parser de descenso recursivo con la misma gramática que el
 * validador de escritorio, para que una regla escrita para aquel se lea igual
 * aquí. Es la pieza que más se prueba, porque un parser con un error de
 * precedencia produce resultados plausibles y equivocados.
 *
 * Gramática:
 *
 *     expresion  := termino (('+' | '-') termino)*
 *     termino    := factor (('*' | '/') factor)*
 *     factor     := ('+' | '-') factor | primario
 *     primario   := '(' expresion ')'
 *                 | SUM '(' expresion (',' expresion)* ')'
 *                 | numero
 *                 | referencia
 *     referencia := [hoja '!'] celda [':' celda]
 */
final class Expresion
{
    private const REF_CELDA = '/^\$?[A-Za-z]{1,3}\$?\d{1,7}$/';

    private const SIGNOS = ['+', '-', '*', '/', '(', ')', ',', ':', '!'];

    /** @var list<array{tipo: string, valor: mixed, pos: int}> */
    private array $tokens = [];

    private int $i = 0;

    private function __construct(
        private readonly string $texto,
        public readonly NodoExpresion $raiz,
    ) {
    }

    /** Compila una expresión. Lanza `ExpresionInvalida` si no se entiende. */
    public static function compilar(string $entrada): self
    {        $texto = trim($entrada);

        if ($texto === '') {
            throw new ExpresionInvalida('La expresión está vacía.', $entrada, 0);
        }

        $parser = new self($texto, new NodoExpresion('literal', 0));
        $parser->tokenizar();
        $raiz = $parser->parseExpresion();

        if (! $parser->fin()) {
            $sobrante = $parser->actual();
            throw new ExpresionInvalida(
                'Sobra «' . $sobrante['valor'] . '» al final.',
                $texto,
                $sobrante['pos'],
            );
        }

        return new self($texto, $raiz);
    }

    /** Expresión de un solo número, sin pasar por el parser. */
    public static function literal(float $valor): self
    {
        return new self((string) $valor, new NodoExpresion('literal', $valor));
    }

    /**
     * Referencias que la expresión necesita leer, en formato `HOJA!REF`.
     *
     * Se obtienen del árbol y no por búsqueda de texto: así un rango o un
     * `SUM` anidado también quedan reflejados, y una celda escrita dos veces
     * no aparece dos veces.
     *
     * @return list<string>
     */
    public function referencias(string $hojaPorDefecto): array
    {
        $salida = [];

        $recorrer = function (NodoExpresion $n) use (&$recorrer, &$salida, $hojaPorDefecto): void {
            switch ($n->tipo) {
                case 'celda':
                    $salida[] = ($n->hoja ?? $hojaPorDefecto) . '!' . $n->ref;
                    break;

                case 'rango':
                    $salida[] = ($n->hoja ?? $hojaPorDefecto) . '!' . $n->desde . ':' . $n->hasta;
                    break;

                case 'suma':
                    // `foreach` y no `array_walk`: aquel pasa cada elemento por
                    // referencia, y los argumentos de un nodo son readonly.
                    foreach ($n->argumentos ?? [] as $argumento) {
                        $recorrer($argumento);
                    }
                    break;

                case 'binario':
                    $recorrer($n->izq);
                    $recorrer($n->der);
                    break;

                case 'negacion':
                    $recorrer($n->argumento);
                    break;
            }
        };

        $recorrer($this->raiz);

        return array_values(array_unique($salida));
    }

    // ─── Lexer ──────────────────────────────────────────────────────────────

    private function tokenizar(): void
    {
        $texto = $this->texto;
        $largo = strlen($texto);
        $i = 0;

        while ($i < $largo) {
            $c = $texto[$i];

            if (ctype_space($c)) {
                $i++;

                continue;
            }

            if (in_array($c, self::SIGNOS, true)) {
                $this->tokens[] = ['tipo' => 'signo', 'valor' => $c, 'pos' => $i];
                $i++;

                continue;
            }

            // Nombre de hoja entre comillas simples: 'Hoja con espacios'!A1
            if ($c === "'") {
                $cierre = strpos($texto, "'", $i + 1);

                if ($cierre === false) {
                    throw new ExpresionInvalida('Comilla simple sin cerrar.', $texto, $i);
                }

                $this->tokens[] = [
                    'tipo'  => 'ident',
                    'valor' => substr($texto, $i + 1, $cierre - $i - 1),
                    'pos'   => $i,
                ];
                $i = $cierre + 1;

                continue;
            }

            if (ctype_digit($c)) {
                if (! preg_match('/^\d+(?:[.,]\d+)?/', substr($texto, $i), $m)) {
                    throw new ExpresionInvalida('Número mal formado.', $texto, $i);
                }

                $siguiente = $texto[$i + strlen($m[0])] ?? '';

                if ($siguiente !== '' && ctype_alpha($siguiente)) {
                    throw new ExpresionInvalida(
                        'Referencia mal formada cerca de «' . $m[0] . '».',
                        $texto,
                        $i,
                    );
                }

                $this->tokens[] = [
                    'tipo'  => 'numero',
                    'valor' => (float) str_replace(',', '.', $m[0]),
                    'pos'   => $i,
                ];
                $i += strlen($m[0]);

                continue;
            }

            if (ctype_alpha($c) || $c === '_' || $c === '$') {
                if (! preg_match('/^[A-Za-z_$][A-Za-z0-9_$]*/', substr($texto, $i), $m)) {
                    throw new ExpresionInvalida('Identificador mal formado.', $texto, $i);
                }

                $this->tokens[] = ['tipo' => 'ident', 'valor' => $m[0], 'pos' => $i];
                $i += strlen($m[0]);

                continue;
            }

            throw new ExpresionInvalida('Carácter no admitido «' . $c . '».', $texto, $i);
        }
    }

    // ─── Parser ─────────────────────────────────────────────────────────────

    private function fin(): bool
    {
        return $this->i >= count($this->tokens);
    }

    /** @return array{tipo: string, valor: mixed, pos: int} */
    private function actual(): array
    {
        return $this->tokens[$this->i] ?? ['tipo' => 'fin', 'valor' => '', 'pos' => strlen($this->texto)];
    }

    private function esSigno(string $valor): bool
    {
        $t = $this->actual();

        return $t['tipo'] === 'signo' && $t['valor'] === $valor;
    }

    private function consumirSigno(string $valor, string $contexto): void
    {
        if (! $this->esSigno($valor)) {
            throw new ExpresionInvalida(
                'Se esperaba «' . $valor . '» ' . $contexto . '.',
                $this->texto,
                $this->actual()['pos'],
            );
        }

        $this->i++;
    }

    private function parseExpresion(): NodoExpresion
    {
        $nodo = $this->parseTermino();

        while ($this->esSigno('+') || $this->esSigno('-')) {
            $operador = $this->actual()['valor'];
            $this->i++;
            $nodo = new NodoExpresion('binario', null, $operador, $nodo, $this->parseTermino());
        }

        return $nodo;
    }

    private function parseTermino(): NodoExpresion
    {
        $nodo = $this->parseFactor();

        while ($this->esSigno('*') || $this->esSigno('/')) {
            $operador = $this->actual()['valor'];
            $this->i++;
            $nodo = new NodoExpresion('binario', null, $operador, $nodo, $this->parseFactor());
        }

        return $nodo;
    }

    private function parseFactor(): NodoExpresion
    {
        if ($this->esSigno('+')) {
            $this->i++;

            return $this->parseFactor();
        }

        if ($this->esSigno('-')) {
            $this->i++;

            return new NodoExpresion('negacion', null, null, null, null, $this->parseFactor());
        }

        return $this->parsePrimario();
    }

    private function parsePrimario(): NodoExpresion
    {
        $token = $this->actual();

        if ($token['tipo'] === 'fin') {
            throw new ExpresionInvalida(
                'La expresión termina antes de tiempo.',
                $this->texto,
                strlen($this->texto),
            );
        }

        if ($token['tipo'] === 'signo' && $token['valor'] === '(') {
            $this->i++;
            $nodo = $this->parseExpresion();
            $this->consumirSigno(')', 'para cerrar el paréntesis');

            return $nodo;
        }

        if ($token['tipo'] === 'numero') {
            $this->i++;

            return new NodoExpresion('literal', (float) $token['valor']);
        }

        if ($token['tipo'] === 'ident') {
            $siguiente = $this->tokens[$this->i + 1] ?? null;

            if (strtoupper((string) $token['valor']) === 'SUM'
                && $siguiente !== null
                && $siguiente['tipo'] === 'signo'
                && $siguiente['valor'] === '(') {
                $this->i += 2;
                $argumentos = [$this->parseExpresion()];

                while ($this->esSigno(',')) {
                    $this->i++;
                    $argumentos[] = $this->parseExpresion();
                }

                $this->consumirSigno(')', 'para cerrar SUM(');

                return new NodoExpresion('suma', null, null, null, null, null, $argumentos);
            }

            return $this->parseReferencia();
        }

        throw new ExpresionInvalida(
            'Token inesperado «' . $token['valor'] . '».',
            $this->texto,
            $token['pos'],
        );
    }

    private function parseReferencia(): NodoExpresion
    {
        $primero = $this->actual();
        $this->i++;

        $hoja = null;
        $refToken = $primero;

        if ($this->esSigno('!')) {
            $this->i++;
            $hoja = (string) $primero['valor'];
            $siguiente = $this->actual();

            if ($siguiente['tipo'] !== 'ident') {
                throw new ExpresionInvalida(
                    'Falta la celda después de «' . $hoja . '!».',
                    $this->texto,
                    $siguiente['pos'],
                );
            }

            $refToken = $siguiente;
            $this->i++;
        }

        if (! preg_match(self::REF_CELDA, (string) $refToken['valor'])) {
            throw new ExpresionInvalida(
                '«' . $refToken['valor'] . '» no es una referencia de celda válida.',
                $this->texto,
                $refToken['pos'],
            );
        }

        if ($this->esSigno(':')) {
            $this->i++;
            $hasta = $this->actual();

            if ($hasta['tipo'] !== 'ident' || ! preg_match(self::REF_CELDA, (string) $hasta['valor'])) {
                throw new ExpresionInvalida(
                    'El rango necesita una celda de término válida.',
                    $this->texto,
                    $hasta['pos'],
                );
            }

            $this->i++;

            return new NodoExpresion(
                'rango',   // tipo
                null,      // valor
                null,      // operador
                null,      // izq
                null,      // der
                null,      // argumento
                null,      // argumentos
                $hoja,     // hoja
                null,      // ref — un rango no tiene una sola celda
                self::normalizarRef((string) $refToken['valor']), // desde
                self::normalizarRef((string) $hasta['valor']),    // hasta
            );
        }

        return new NodoExpresion(
            'celda',
            null,
            null,
            null,
            null,
            null,
            null,
            $hoja,
            self::normalizarRef((string) $refToken['valor']),
        );
    }

    /** Quita los `$` de las referencias absolutas: `A1` y `$A$1` son la misma celda. */
    private static function normalizarRef(string $ref): string
    {
        return strtoupper(str_replace('$', '', $ref));
    }
}
