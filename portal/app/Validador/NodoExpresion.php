<?php

namespace App\Validador;

/**
 * Nodo del árbol de una expresión de regla.
 *
 * Es una clase de datos y no un `array` a propósito: el evaluador recorre el
 * árbol muchas veces por archivo (144 reglas × 2 lados), y el acceso a
 * propiedades es más legible que un `array['izq']['der']` de tres niveles.
 */
final class NodoExpresion
{
    /**
     * @param  'literal'|'celda'|'rango'|'suma'|'binario'|'negacion'  $tipo
     * @param  list<NodoExpresion>|null  $argumentos
     */
    public function __construct(
        public readonly string $tipo,
        public readonly ?float $valor = null,
        public readonly ?string $operador = null,
        public readonly ?self $izq = null,
        public readonly ?self $der = null,
        public readonly ?self $argumento = null,
        public readonly ?array $argumentos = null,
        public readonly ?string $hoja = null,
        public readonly ?string $ref = null,
        public readonly ?string $desde = null,
        public readonly ?string $hasta = null,
    ) {
    }
}
