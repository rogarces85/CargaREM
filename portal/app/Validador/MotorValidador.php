<?php

namespace App\Validador;

use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Motor de reglas de calidad (RF-040, D-047).
 *
 * **Aislado del flujo.** No conoce `Envio`, ni `EnvioArchivo`, ni el diccionario
 * de prestaciones; recibe una ruta y devuelve hallazgos. Esa separación es lo
 * que permite afirmar —y probar— que usar el Validador no cambia nada del
 * proceso estadístico (R-VAL-01).
 *
 * Cada regla se evalúa contra el libro abierto una sola vez. Un tipo no
 * contemplado aborta la ejecución: nunca se ignora en silencio (R-VAL-02).
 */
final class MotorValidador
{
    /**
     * @return list<HallazgoCalidad>
     */
    public function ejecutar(string $ruta, ?CatalogoCalidad $catalogo = null): array
    {
        $catalogo ??= CatalogoCalidad::desdeArchivo();

        $lector = new LectorLibro($ruta);

        try {
            $hallazgos = [];

            foreach ($catalogo->reglas as $regla) {
                foreach ($this->evaluar($regla, $lector) as $hallazgo) {
                    $hallazgos[] = $hallazgo;
                }
            }

            return $hallazgos;
        } finally {
            $lector->cerrar();
        }
    }

    /**
     * @return list<HallazgoCalidad>
     */
    private function evaluar(ReglaCalidad $regla, LectorLibro $lector): array
    {
        return match ($regla->tipo) {
            'hoja_presente' => $this->hojaPresente($regla, $lector),
            'requerido'     => $this->requerido($regla, $lector),
            'tipo_dato'     => $this->tipoDato($regla, $lector),
            'rango'         => $this->rango($regla, $lector),
            'dominio'       => $this->dominio($regla, $lector),
            'unicidad'      => $this->unicidad($regla, $lector),
            default         => throw new \RuntimeException(
                "La regla {$regla->id} usa un tipo desconocido: «{$regla->tipo}»."
            ),
        };
    }

    /** @return list<HallazgoCalidad> */
    private function hojaPresente(ReglaCalidad $regla, LectorLibro $lector): array
    {
        $hoja = $regla->hoja();

        if ($hoja === null) {
            throw new \RuntimeException("La regla {$regla->id} necesita «hoja».");
        }

        return $lector->tieneHoja($hoja)
            ? []
            : [new HallazgoCalidad($regla->id, $regla->severidad, $regla->mensaje, $hoja, null)];
    }

    /** @return list<HallazgoCalidad> */
    private function requerido(ReglaCalidad $regla, LectorLibro $lector): array
    {
        $hoja = $this->hojaDe($regla, $lector);

        if ($hoja === null) {
            return [];
        }

        $columna = $this->columnaDe($regla);

        if ($columna === null) {
            throw new \RuntimeException("La regla {$regla->id} necesita «columna».");
        }

        $faltantes = [];

        foreach ($lector->columna($hoja, $columna, $regla->desdeFila(), $regla->hastaFila()) as $fila => $valor) {
            if ($valor === null || trim((string) $valor) === '') {
                $faltantes[] = $fila;
            }
        }

        return $this->hallazgosPorFila($regla, $hoja, $columna, $faltantes);
    }

    /** @return list<HallazgoCalidad> */
    private function tipoDato(ReglaCalidad $regla, LectorLibro $lector): array
    {
        $hoja = $this->hojaDe($regla, $lector);

        if ($hoja === null) {
            return [];
        }

        $columna = $this->columnaDe($regla);

        if ($columna === null) {
            throw new \RuntimeException("La regla {$regla->id} necesita «columna».");
        }

        $esperado = (string) ($regla->parametros['esperado'] ?? 'numérico');
        $inválidas = [];

        foreach ($lector->columna($hoja, $columna, $regla->desdeFila(), $regla->hastaFila()) as $fila => $valor) {
            if ($valor === null || trim((string) $valor) === '') {
                continue;
            }

            $cumple = match ($esperado) {
                'numérico', 'numerico' => is_numeric($valor),
                'entero'               => is_numeric($valor) && (float) $valor === (float) (int) $valor,
                'texto'                => ! is_numeric($valor),
                'fecha'                => is_numeric($valor) || strtotime((string) $valor) !== false,
                default                => throw new \RuntimeException(
                    "La regla {$regla->id} pide un tipo de dato desconocido: «{$esperado}»."
                ),
            };

            if (! $cumple) {
                $inválidas[] = $fila;
            }
        }

        return $this->hallazgosPorFila($regla, $hoja, $columna, $inválidas);
    }

    /** @return list<HallazgoCalidad> */
    private function rango(ReglaCalidad $regla, LectorLibro $lector): array
    {
        $hoja = $this->hojaDe($regla, $lector);

        if ($hoja === null) {
            return [];
        }

        $columna = $this->columnaDe($regla);

        if ($columna === null) {
            throw new \RuntimeException("La regla {$regla->id} necesita «columna».");
        }

        $minimo = $regla->parametros['min'] ?? null;
        $maximo = $regla->parametros['max'] ?? null;

        $fuera = [];

        foreach ($lector->columna($hoja, $columna, $regla->desdeFila(), $regla->hastaFila()) as $fila => $valor) {
            if ($valor === null || trim((string) $valor) === '' || ! is_numeric($valor)) {
                continue;
            }

            $numero = (float) $valor;

            if (($minimo !== null && $numero < (float) $minimo) || ($maximo !== null && $numero > (float) $maximo)) {
                $fuera[] = $fila;
            }
        }

        return $this->hallazgosPorFila($regla, $hoja, $columna, $fuera);
    }

    /** @return list<HallazgoCalidad> */
    private function dominio(ReglaCalidad $regla, LectorLibro $lector): array
    {
        $hoja = $this->hojaDe($regla, $lector);

        if ($hoja === null) {
            return [];
        }

        $columna = $this->columnaDe($regla);

        if ($columna === null) {
            throw new \RuntimeException("La regla {$regla->id} necesita «columna».");
        }

        $admitidos = array_map('strval', (array) ($regla->parametros['valores'] ?? []));
        $fuera = [];

        foreach ($lector->columna($hoja, $columna, $regla->desdeFila(), $regla->hastaFila()) as $fila => $valor) {
            if ($valor === null || trim((string) $valor) === '') {
                continue;
            }

            if (! in_array(trim((string) $valor), $admitidos, true)) {
                $fuera[] = $fila;
            }
        }

        return $this->hallazgosPorFila($regla, $hoja, $columna, $fuera);
    }

    /** @return list<HallazgoCalidad> */
    private function unicidad(ReglaCalidad $regla, LectorLibro $lector): array
    {
        $hoja = $this->hojaDe($regla, $lector);

        if ($hoja === null) {
            return [];
        }

        $columna = $this->columnaDe($regla);

        if ($columna === null) {
            throw new \RuntimeException("La regla {$regla->id} necesita «columna».");
        }

        $vistos = [];
        $hallazgos = [];

        foreach ($lector->columna($hoja, $columna, $regla->desdeFila(), $regla->hastaFila()) as $fila => $valor) {
            $clave = trim((string) $valor);

            if ($clave === '') {
                continue;
            }

            if (isset($vistos[$clave])) {
                $hallazgos[] = new HallazgoCalidad(
                    $regla->id,
                    $regla->severidad,
                    "{$regla->mensaje} (repetido en la fila {$fila}, ya aparecía en la {$vistos[$clave]})",
                    $hoja->getTitle(),
                    $columna . $fila,
                );

                continue;
            }

            $vistos[$clave] = $fila;
        }

        return $hallazgos;
    }

    private function hojaDe(ReglaCalidad $regla, LectorLibro $lector): ?Worksheet
    {
        $nombre = $regla->hoja();

        if ($nombre === null) {
            throw new \RuntimeException("La regla {$regla->id} necesita «hoja».");
        }

        // Si la hoja no está, la regla no aplica: el hallazgo de que falta lo
        // da la regla `hoja_presente`, y repetirlo por cada columna sería ruido.
        return $lector->hoja($nombre);
    }

    private function columnaDe(ReglaCalidad $regla): ?string
    {
        return $regla->columna();
    }

    /**
     * Un hallazgo por fila afectada, con su celda. Se limita a las primeras 50
     * filas por regla: un archivo con 5.000 celdas malas no necesita 5.000
     * hallazgos para que el usuario entienda que algo está mal.
     *
     * @param  list<int>  $filas
     * @return list<HallazgoCalidad>
     */
    private function hallazgosPorFila(ReglaCalidad $regla, Worksheet $hoja, string $columna, array $filas): array
    {
        $hallazgos = [];
        $tope = 50;

        foreach (array_slice($filas, 0, $tope) as $fila) {
            $hallazgos[] = new HallazgoCalidad(
                $regla->id,
                $regla->severidad,
                $regla->mensaje,
                $hoja->getTitle(),
                $columna . $fila,
            );
        }

        if (count($filas) > $tope) {
            $hallazgos[] = new HallazgoCalidad(
                $regla->id,
                $regla->severidad,
                $regla->mensaje . ' (y ' . (count($filas) - $tope) . ' celda(s) más con el mismo problema)',
                $hoja->getTitle(),
                null,
            );
        }

        return $hallazgos;
    }
}
