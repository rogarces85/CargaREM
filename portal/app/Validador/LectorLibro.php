<?php

namespace App\Validador;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Lectura de un Excel para el Validador (RF-040).
 *
 * Deliberadamente tonto: abre el libro con `setReadDataOnly(true)`, entrega las
 * hojas y el valor de una celda, y lo cierra. **Nunca ejecuta macros.** No
 * reutiliza el lector del flujo de envío porque aquel vive atado al modelo
 * `EnvioArchivo` y al diccionario; el Validador trabaja sobre un archivo
 * suelto, sin envío detrás.
 */
final class LectorLibro
{
    private ?Spreadsheet $libro = null;

    public function __construct(private readonly string $ruta)
    {
    }

    /** @return list<string> */
    public function hojas(): array
    {
        return $this->abrir()->getSheetNames();
    }

    public function tieneHoja(string $nombre): bool
    {
        foreach ($this->hojas() as $hoja) {
            if (mb_strtolower(trim($hoja)) === mb_strtolower(trim($nombre))) {
                return true;
            }
        }

        return false;
    }

    public function hoja(string $nombre): ?Worksheet
    {
        $libro = $this->abrir();

        foreach ($libro->getAllSheets() as $hoja) {
            if (mb_strtolower(trim($hoja->getTitle())) === mb_strtolower(trim($nombre))) {
                return $hoja;
            }
        }

        return null;
    }

    /**
     * Valores de una columna, desde `$desdeFila` hasta la última fila con datos.
     *
     * @return array<int, mixed> fila => valor
     */
    public function columna(Worksheet $hoja, string $columna, int $desdeFila, ?int $hastaFila = null): array
    {
        $ultima = $hastaFila ?? $hoja->getHighestDataRow();
        $valores = [];

        for ($fila = $desdeFila; $fila <= $ultima; $fila++) {
            $valores[$fila] = $hoja->getCell($columna . $fila)->getValue();
        }

        return $valores;
    }

    public function cerrar(): void
    {
        $this->libro?->disconnectWorksheets();
        $this->libro = null;
    }

    private function abrir(): Spreadsheet
    {
        if ($this->libro instanceof Spreadsheet) {
            return $this->libro;
        }

        $lector = IOFactory::createReaderForFile($this->ruta);
        $lector->setReadDataOnly(true);

        return $this->libro = $lector->load($this->ruta);
    }
}
