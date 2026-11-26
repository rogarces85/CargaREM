<?php

namespace App\Validador;

use RuntimeException;

/**
 * Una expresión del catálogo no se pudo interpretar.
 *
 * Lleva la expresión y la posición para que el mensaje sirva: «error en la
 * regla A02-VAL001» no dice nada, y el catálogo lo edita la Unidad, no
 * nosotros. Una expresión rota se reporta como error técnico de esa regla y
 * **no** se ignora: una regla que no corre en silencio produce un resultado
 * limpio sobre datos que nadie revisó (R-VAL-02).
 */
final class ExpresionInvalida extends RuntimeException
{
    public function __construct(
        string $mensaje,
        public readonly string $expresion,
        public readonly int $posicion,
    ) {
        parent::__construct("{$mensaje} (en «{$expresion}», posición {$posicion})");
    }
}
