<?php

namespace App\Excel;

/**
 * Resultado de leer las celdas de datos de un archivo.
 */
final class DatosArchivo
{
    /** @param list<RegistroPrestacion> $registros @param list<ErrorLectura> $errores */
    public function __construct(
        public readonly array $registros = [],
        public readonly array $errores = [],
        public readonly bool $leyo = true,
    ) {
    }

    /** @return list<RegistroPrestacion> */
    public function deHoja(string $hoja): array
    {
        return array_values(array_filter($this->registros, fn (RegistroPrestacion $r) => $r->hoja === $hoja));
    }

    public function hayAlgunDato(): bool
    {
        foreach ($this->registros as $registro) {
            if ($registro->tieneDatos()) {
                return true;
            }
        }

        return false;
    }
}