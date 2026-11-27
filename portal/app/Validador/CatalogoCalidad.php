<?php

namespace App\Validador;

/**
 * El catálogo de reglas de calidad (RF-040, D-047).
 *
 * El archivo lo mantiene la Unidad de Estadísticas en su Validador de
 * escritorio y está **agrupado por hoja REM**:
 *
 *     { "A01": [ {regla}, … ], "P2": [ … ], … }
 *
 * Cada regla ya trae su `rem_sheet`, así que la agrupación es redundante; se
 * conserva para no reescribir el archivo que ellos generan. La versión y el
 * recuento viajan con cada ejecución para poder repetir una revisión con el
 * mismo criterio.
 *
 * `reglasDeSerie()` filtra por el prefijo de la hoja (A01, A02… son la serie A;
 * P1, P11… la serie P), que es cómo el escritorio reparte el catálogo.
 */
final class CatalogoCalidad
{
    /**
     * @param  array<string, list<ReglaCalidad>>  $reglasPorHoja
     */
    public function __construct(
        public readonly string $version,
        public readonly array $reglasPorHoja,
        public readonly string $ruta,
    ) {
    }

    public static function desdeArchivo(?string $ruta = null): self
    {
        $ruta ??= self::rutaPorDefecto();

        if (! is_readable($ruta)) {
            throw new \RuntimeException(
                'No se encontró el archivo de reglas del Validador en ' . $ruta . '.'
            );
        }

        $contenido = json_decode((string) file_get_contents($ruta), true);

        if (! is_array($contenido)) {
            throw new \RuntimeException('El archivo de reglas del Validador no es un JSON válido.');
        }

        $porHoja = [];

        foreach ($contenido as $hoja => $reglas) {
            if (! is_array($reglas)) {
                throw new \RuntimeException("La hoja «{$hoja}» del catálogo no contiene una lista de reglas.");
            }

            foreach ($reglas as $indice => $datos) {
                if (! is_array($datos)) {
                    throw new \RuntimeException("La regla #{$indice} de la hoja {$hoja} no es un objeto.");
                }

                $porHoja[$hoja][] = ReglaCalidad::desdeArreglo($datos, (int) $indice, (string) $hoja);
            }
        }

        if ($porHoja === []) {
            throw new \RuntimeException('El catálogo del Validador no declara ninguna regla.');
        }

        return new self(
            version: self::leerVersion($ruta),
            reglasPorHoja: $porHoja,
            ruta: $ruta,
        );
    }

    /** Total de reglas del catálogo. */
    public function total(): int
    {
        return array_sum(array_map('count', $this->reglasPorHoja));
    }

    /**
     * Reglas de una serie, por el prefijo de la hoja.
     *
     * La serie A son las hojas A01, A02…; la P son P1, P2, P11… Se compara el
     * prefijo y se descarta la coincidencia exacta invertida («P1» no debe
     * atrapar «P11» por accidente). La comprobación real la hace el nombre del
     * archivo, que ya dice a qué serie pertenece.
     *
     * @return list<ReglaCalidad>
     */
    public function reglasDeSerie(string $serie): array
    {
        $prefijo = strtoupper(trim($serie));
        $reglas = [];

        foreach ($this->reglasPorHoja as $hoja => $lista) {
            $nombreHoja = strtoupper($hoja);

            if (! str_starts_with($nombreHoja, $prefijo)) {
                continue;
            }

            // «A» atrapa A01 y A19b; «P» atrapa P1 y P11. La hoja NOMBRE no
            // empieza por la serie y no tiene reglas de negocio propias, así
            // que no hace falta excluirla.
            foreach ($lista as $regla) {
                $reglas[] = $regla;
            }
        }

        return $reglas;
    }

    /** @return list<string> */
    public function hojas(): array
    {
        return array_keys($this->reglasPorHoja);
    }

    public static function rutaPorDefecto(): string
    {
        return config_path('validador/reglas.json');
    }

    /**
     * La versión no viene dentro del archivo de reglas: se toma del manifiesto
     * del catálogo, que es lo que genera la herramienta de la Unidad. Si no
     * está, se usa «sin versión» en vez de inventar un número.
     */
    private static function leerVersion(string $rutaReglas): string
    {
        $manifiesto = dirname($rutaReglas) . DIRECTORY_SEPARATOR . 'catalogo-meta.json';

        if (! is_readable($manifiesto)) {
            return 'sin versión';
        }

        $datos = json_decode((string) file_get_contents($manifiesto), true);

        if (! is_array($datos)) {
            return 'sin versión';
        }

        $reglas = (int) ($datos['reglas'] ?? 0);
        $hojas = (int) ($datos['hojas'] ?? 0);

        return sprintf('%d reglas / %d hojas', $reglas, $hojas);
    }
}
