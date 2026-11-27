<?php

namespace App\Validador;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Lector de Excel para el Validador (RF-040).
 *
 * Deliberadamente tonto: abre el libro una vez, entrega el valor de una celda
 * y la suma de un rango, y lo cierra. **Nunca ejecuta macros** —
 * `setReadDataOnly(true)` descarta fórmulas y estilos.
 *
 * No reutiliza el lector del flujo de envío porque aquel vive atado al modelo
 * `EnvioArchivo` y al diccionario de prestaciones; el Validador trabaja sobre
 * un archivo suelto, sin envío detrás. Esa separación es lo que permite
 * afirmar que el módulo no toca el proceso estadístico.
 */
final class LectorSpreadsheet implements LectorLibro
{
    private ?\PhpOffice\PhpSpreadsheet\Spreadsheet $libro = null;

    /** @var array<string, Worksheet|null> índice por nombre normalizado */
    private array $indice = [];

    public function __construct(private readonly string $ruta)
    {
    }

    /** @return list<string> */
    public function hojas(): array
    {
        return $this->abrir()->getSheetNames();
    }

    public function tieneHoja(string $hoja): bool
    {
        return $this->hoja($hoja) !== null;
    }

    public function celda(string $hoja, string $ref): mixed
    {
        $worksheet = $this->hoja($hoja);

        if ($worksheet === null) {
            return null;
        }

        $celda = $worksheet->getCell(strtoupper($ref));
        $valor = $celda->getValue();

        // Una celda en error (#DIV/0!, #REF!) se trata como sin dato: la regla
        // compara números y un texto de error la haría fallar sin explicación.
        if (is_string($valor) && str_starts_with($valor, '#')) {
            return null;
        }

        return $valor;
    }

    public function sumaRango(string $hoja, string $desde, string $hasta): float
    {
        $worksheet = $this->hoja($hoja);

        if ($worksheet === null) {
            return 0.0;
        }

        try {
            [$colIni, $filaIni] = Coordinate::coordinateFromString(strtoupper($desde));
            [$colFin, $filaFin] = Coordinate::coordinateFromString(strtoupper($hasta));
        } catch (\Throwable) {
            return 0.0;
        }

        $c1 = Coordinate::columnIndexFromString($colIni);
        $c2 = Coordinate::columnIndexFromString($colFin);
        $f1 = (int) $filaIni;
        $f2 = (int) $filaFin;

        // Un rango escrito al revés se lee igual: el usuario no tiene por qué
        // respetar un orden que el Excel tolera.
        if ($c1 > $c2) {
            [$c1, $c2] = [$c2, $c1];
        }

        if ($f1 > $f2) {
            [$f1, $f2] = [$f2, $f1];
        }

        $total = 0.0;

        for ($fila = $f1; $fila <= $f2; $fila++) {
            for ($col = $c1; $col <= $c2; $col++) {
                $valor = $worksheet->getCell(Coordinate::stringFromColumnIndex($col) . $fila)->getValue();

                if ($valor === null || is_string($valor) && str_starts_with($valor, '#')) {
                    continue;
                }

                $total += self::aNumero($valor);
            }
        }

        return $total;
    }

    public function cerrar(): void
    {
        $this->libro?->disconnectWorksheets();
        $this->libro = null;
        $this->indice = [];
    }

    /**
     * Convierte el valor crudo de una celda al número que usa el motor.
     *
     * Criterios, alineados con cómo se leen los REM en la práctica:
     * - Celda ausente, vacía o no numérica → 0.
     * - Número escrito como texto («12») → 12.
     * - Formato chileno «1.234,5» → 1234.5.
     */
    public static function aNumero(mixed $valor): float
    {
        if ($valor === null || $valor === '') {
            return 0.0;
        }

        if (is_bool($valor)) {
            return $valor ? 1.0 : 0.0;
        }

        if (is_int($valor) || is_float($valor)) {
            return is_finite((float) $valor) ? (float) $valor : 0.0;
        }

        $limpio = str_replace(' ', '', trim((string) $valor));

        if ($limpio === '') {
            return 0.0;
        }

        $normalizado = str_contains($limpio, ',') && str_contains($limpio, '.')
            ? str_replace('.', '', $limpio)
            : $limpio;

        $normalizado = str_replace(',', '.', $normalizado);

        return is_numeric($normalizado) ? (float) $normalizado : 0.0;
    }

    private function hoja(string $nombre): ?Worksheet
    {
        $clave = mb_strtoupper(trim($nombre));

        if (array_key_exists($clave, $this->indice)) {
            return $this->indice[$clave];
        }

        $encontrada = null;

        foreach ($this->abrir()->getAllSheets() as $hoja) {
            if (mb_strtoupper(trim($hoja->getTitle())) === $clave) {
                $encontrada = $hoja;
                break;
            }
        }

        return $this->indice[$clave] = $encontrada;
    }

    private function abrir(): \PhpOffice\PhpSpreadsheet\Spreadsheet
    {
        if ($this->libro instanceof \PhpOffice\PhpSpreadsheet\Spreadsheet) {
            return $this->libro;
        }

        $lector = IOFactory::createReaderForFile($this->ruta);
        $lector->setReadDataOnly(true);

        return $this->libro = $lector->load($this->ruta);
    }
}
