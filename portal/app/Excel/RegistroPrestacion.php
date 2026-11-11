<?php

namespace App\Excel;

/**
 * Una prestación del diccionario, leída de una hoja.
 *
 * `valores` trae las celdas del rango `inicio..fin` en la línea indicada, en
 * orden. Una celda vacía viaja como null: distinguir "vacío" de "cero" es lo
 * que permite detectar un dato faltante en un total que debería sumar.
 */
final class RegistroPrestacion
{
    /**
     * @param  list<array{celda: string, valor: mixed, formula: bool, calculado: mixed}>  $valores
     */
    public function __construct(
        public readonly string $hoja,
        public readonly int $linea,
        public readonly ?string $codigo,
        public readonly ?string $texto,
        public readonly ?string $tipoDato,
        public readonly array $valores,
    ) {
    }

    public function tieneDatos(): bool
    {
        foreach ($this->valores as $v) {
            if ($v['valor'] !== null && trim((string) $v['valor']) !== '') {
                return true;
            }
        }

        return false;
    }

    /** @return list<array{celda: string, valor: mixed, formula: bool, calculado: mixed}> */
    public function valoresConDatos(): array
    {
        return array_values(array_filter($this->valores, fn (array $v) => trim((string) $v['valor']) !== ''));
    }
}