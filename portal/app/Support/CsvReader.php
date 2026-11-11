<?php

namespace App\Support;

use App\Exceptions\CatalogoInvalidoException;
use Generator;

/**
 * Lector de CSV con las garantías que el catálogo real exige.
 *
 * El diccionario del Minsal tiene 923 filas con saltos de línea dentro de un
 * campo entrecomillado (186.449 líneas físicas para 185.526 registros). Leer
 * línea a línea corrompe el catálogo; fgetcsv sabe manejarlo.
 */
final class CsvReader
{
    /**
     * @param  list<string>  $columnasEsperadas
     * @return Generator<int, array<string, string>>
     */
    public static function leer(string $ruta, array $columnasEsperadas): Generator
    {
        if (! is_readable($ruta)) {
            throw CatalogoInvalidoException::archivoFaltante(basename($ruta));
        }

        $contenido = file_get_contents($ruta);

        if ($contenido !== false && ! mb_check_encoding($contenido, 'UTF-8')) {
            throw CatalogoInvalidoException::codificacion(basename($ruta));
        }

        $fh = fopen($ruta, 'rb');

        if ($fh === false) {
            throw CatalogoInvalidoException::archivoFaltante(basename($ruta));
        }

        try {
            $cabecera = fgetcsv($fh, 0, ',', '"', '\\');

            if ($cabecera === false) {
                throw CatalogoInvalidoException::archivoFaltante(basename($ruta));
            }

            $cabecera = array_map(
                fn ($c) => trim((string) $c, "\xEF\xBB\xBF \t\n\r\0\x0B"),
                $cabecera
            );

            if ($cabecera !== $columnasEsperadas) {
                throw CatalogoInvalidoException::columnas(
                    basename($ruta),
                    implode(',', $columnasEsperadas),
                    implode(',', $cabecera)
                );
            }

            $numero = 1;

            while (($fila = fgetcsv($fh, 0, ',', '"', '\\')) !== false) {
                $numero++;

                if ($fila === [null]) {
                    continue;
                }

                if (count($fila) !== count($columnasEsperadas)) {
                    throw CatalogoInvalidoException::columnas(
                        basename($ruta)." línea {$numero}",
                        (string) count($columnasEsperadas).' campos',
                        (string) count($fila).' campos'
                    );
                }

                $registro = [];

                foreach ($columnasEsperadas as $i => $columna) {
                    // TRIM obligatorio: el Access trae relleno de espacios
                    // ("Osorno" + 44 espacios). Sin esto las comparaciones fallan.
                    $registro[$columna] = trim((string) $fila[$i]);
                }

                yield $numero => $registro;
            }
        } finally {
            fclose($fh);
        }
    }
}
