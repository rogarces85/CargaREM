<?php

namespace App\Excel;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Throwable;

/**
 * Lista de hojas de un libro, sin cargar su contenido.
 *
 * Abre con `setReadDataOnly(true)` y devuelve los títulos. Es lo único que V5
 * necesita: comparar qué hojas trae el archivo con las que la plantilla exige.
 */
class LectorHojas
{
    /** @return list<string> */
    public function nombres(string $ruta): array
    {
        $libro = $this->abrir($ruta);

        try {
            return $libro->getSheetNames();
        } finally {
            $libro->disconnectWorksheets();
        }
    }

    private function abrir(string $ruta): Spreadsheet
    {
        $lector = IOFactory::createReaderForFile($ruta);
        $lector->setReadDataOnly(true);

        return $lector->load($ruta);
    }
}
