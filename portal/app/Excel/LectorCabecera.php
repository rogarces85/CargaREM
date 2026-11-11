<?php

namespace App\Excel;

use App\Models\Plantilla;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Throwable;

/**
 * Lector de la cabecera de un Excel REM (T-F2-15).
 *
 * Usa las coordenadas del validador de escritorio. Si el catálogo trae filas
 * en `plantilla_cabecera`, esas mandan: el Minsal cambia de plantilla y la
 * lectura no debe quedar clavada en el código.
 *
 * El lector abre el archivo con `setReadDataOnly(true)`: no interesan formatos
 * ni estilos, y sin eso un archivo de 2 MB puede consumir cientos.
 */
class LectorCabecera
{
    /** Mapa por defecto, calcado de la hoja "NOMBRE" de Cargas REM. */
    private const POR_DEFECTO = [
        'establecimiento' => ['hoja' => 'NOMBRE', 'celdas' => ['C3', 'D3', 'E3', 'F3', 'G3', 'H3'], 'concatenar' => true],
        'mes'             => ['hoja' => 'NOMBRE', 'celdas' => ['C6', 'D6'], 'concatenar' => true],
        'anio'            => ['hoja' => 'NOMBRE', 'celdas' => ['B7'], 'concatenar' => false],
        'serie'           => ['hoja' => 'NOMBRE', 'celdas' => ['B17'], 'concatenar' => false],
        'version'         => ['hoja' => 'NOMBRE', 'celdas' => ['A9'], 'concatenar' => false],
    ];

    private const MESES = [
        'ENERO' => 1, 'FEBRERO' => 2, 'MARZO' => 3, 'ABRIL' => 4,
        'MAYO' => 5, 'JUNIO' => 6, 'JULIO' => 7, 'AGOSTO' => 8,
        'SEPTIEMBRE' => 9, 'SETIEMBRE' => 9, 'OCTUBRE' => 10,
        'NOVIEMBRE' => 11, 'DICIEMBRE' => 12,
    ];

    public function leer(string $ruta, ?Plantilla $plantilla = null): CabeceraArchivo
    {
        $libro = $this->abrir($ruta);

        try {
            $mapa = $this->mapa($plantilla);

            $hojaNombre = $mapa['serie']['hoja'];
            $hoja = $libro->getSheetByName($hojaNombre) ?? $libro->getSheet(0);

            if ($hoja === null) {
                return new CabeceraArchivo(null, null, null, null, null, $hojaNombre);
            }

            return new CabeceraArchivo(
                establecimiento: $this->texto($this->leerCampo($hoja, $mapa['establecimiento'])),
                anio: $this->entero($this->leerCampo($hoja, $mapa['anio'])),
                mes: $this->mes($this->leerCampo($hoja, $mapa['mes'])),
                serie: $this->serie($this->leerCampo($hoja, $mapa['serie'])),
                version: $this->texto($this->leerCampo($hoja, $mapa['version'])),
                hoja: $hoja->getTitle(),
            );
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

    /** @return array<string, array{hoja: string, celdas: list<string>, concatenar: bool}> */
    private function mapa(?Plantilla $plantilla): array
    {
        $mapa = self::POR_DEFECTO;

        if ($plantilla === null) {
            return $mapa;
        }

        foreach ($plantilla->cabecera as $fila) {
            if (! isset($mapa[$fila->campo]) || $fila->celda === null) {
                continue;
            }

            $mapa[$fila->campo]['celdas'] = [$fila->celda];
            $mapa[$fila->campo]['concatenar'] = false;

            if ($fila->hoja !== null) {
                $mapa[$fila->campo]['hoja'] = $fila->hoja;
            }
        }

        return $mapa;
    }

    /**
     * @param  array{celdas: list<string>, concatenar: bool}  $campo
     */
    private function leerCampo($hoja, array $campo): string
    {
        $partes = [];

        foreach ($campo['celdas'] as $celda) {
            try {
                $valor = $hoja->getCell($celda)->getCalculatedValue();
            } catch (Throwable) {
                // Una fórmula rota no debe tumbar la lectura de la cabecera.
                $valor = $hoja->getCell($celda)->getValue();
            }

            $texto = trim((string) $valor);

            if ($texto !== '') {
                $partes[] = $texto;
            }

            if (! $campo['concatenar'] && $texto !== '') {
                break;
            }
        }

        return trim(implode('', $partes));
    }

    private function texto(string $valor): ?string
    {
        return $valor === '' ? null : $valor;
    }

    private function entero(string $valor): ?int
    {
        if ($valor === '') {
            return null;
        }

        if (preg_match('/\b(19|20)\d{2}\b/', $valor, $m)) {
            return (int) $m[0];
        }

        return is_numeric(trim($valor)) ? (int) trim($valor) : null;
    }

    private function mes(string $valor): ?int
    {
        if ($valor === '') {
            return null;
        }

        $limpio = trim($valor);

        if (is_numeric($limpio)) {
            $n = (int) $limpio;

            return $n >= 1 && $n <= 12 ? $n : null;
        }

        $mayusculas = mb_strtoupper($limpio);

        foreach (self::MESES as $nombre => $numero) {
            if (str_contains($mayusculas, $nombre)) {
                return $numero;
            }
        }

        return null;
    }

    /** "SERIE BS" => "BS" */
    private function serie(string $valor): ?string
    {
        if ($valor === '') {
            return null;
        }

        $codigo = trim(str_ireplace('SERIE', '', $valor));

        return $codigo === '' ? null : mb_substr($codigo, 0, 10);
    }
}
