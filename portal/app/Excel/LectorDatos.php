<?php

namespace App\Excel;

use App\Models\Diccionario;
use App\Models\PlantillaHoja;
use App\Validacion\ContextoValidacion;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Throwable;

/**
 * Lee las celdas de datos de un archivo REM (T-F2-19).
 *
 * El diccionario dice, para cada prestación, en qué hoja, en qué línea y entre
 * qué columnas están sus valores. Aquí se recorren esas coordenadas una sola
 * vez y se devuelven como datos; las reglas V6–V10 trabajan sobre eso y no
 * vuelven a abrir el archivo.
 */
class LectorDatos
{
    public function leer(ContextoValidacion $contexto): DatosArchivo
    {
        $serieInterna = $contexto->plantilla?->serie_interna;

        if ($serieInterna === null) {
            return new DatosArchivo([], [], false);
        }

        $hojas = PlantillaHoja::query()
            ->where('anio', $contexto->anio())
            ->where('serie_interna', $serieInterna)
            ->pluck('hoja')
            ->all();

        if ($hojas === []) {
            return new DatosArchivo([], [], false);
        }

        try {
            $libro = $this->abrir($contexto->rutaAbsoluta);
        } catch (Throwable) {
            return new DatosArchivo([], [], false);
        }

        $registros = [];
        $errores = [];

        try {
            foreach ($hojas as $nombreHoja) {
                $hoja = $libro->getSheetByName($nombreHoja);

                if ($hoja === null) {
                    continue; // V5 ya reporta la hoja faltante
                }

                $filas = Diccionario::query()
                    ->where('snapshot_id', $contexto->snapshotId)
                    ->where('anio', $contexto->anio())
                    ->where('serie', $serieInterna)
                    ->where('hoja', $nombreHoja)
                    ->orderBy('linea')
                    ->get();

                foreach ($filas as $fila) {
                    try {
                        $valores = $this->leerRango($hoja, (string) $fila->inicio, (string) $fila->fin, (int) $fila->linea);
                    } catch (Throwable $e) {
                        $errores[] = new ErrorLectura($nombreHoja, $fila->codigo_prestacion, $e->getMessage());

                        continue;
                    }

                    $registros[] = new RegistroPrestacion(
                        hoja: $nombreHoja,
                        linea: (int) $fila->linea,
                        codigo: $fila->codigo_prestacion,
                        texto: $fila->texto_prestacion,
                        tipoDato: $fila->tipo_dato,
                        valores: $valores,
                    );
                }
            }
        } finally {
            $libro->disconnectWorksheets();
        }

        return new DatosArchivo($registros, $errores);
    }

    /**
     * @return list<array{celda: string, valor: mixed, formula: bool, calculado: mixed}>
     */
    private function leerRango(Worksheet $hoja, string $inicio, string $fin, int $linea): array
    {
        $desde = $this->columna($inicio);
        $hasta = $this->columna($fin);

        if ($hasta < $desde) {
            throw new \RuntimeException("El rango {$inicio}..{$fin} está invertido.");
        }

        /*
         * Se lee la fila entera de una vez: pedir celda por celda cuesta una
         * llamada por columna y multiplica el tiempo de un archivo real de
         * treinta hojas por varias decenas de segundos.
         */
        $rango = Coordinate::stringFromColumnIndex($desde).$linea.':'.Coordinate::stringFromColumnIndex($hasta).$linea;
        $fila = $hoja->rangeToArray($rango, null, false, false, false)[0] ?? [];

        $valores = [];

        foreach (array_values($fila) as $indice => $valor) {
            $celda = Coordinate::stringFromColumnIndex($desde + $indice).$linea;

            // Una fórmula sin calcular llega como el texto de la fórmula.
            $esFormula = is_string($valor) && str_starts_with(trim($valor), '=');

            $valores[] = [
                'celda'     => $celda,
                'valor'     => $esFormula ? null : $valor,
                'formula'   => $esFormula,
                'calculado' => null,
            ];
        }

        return $valores;
    }

    /**
     * Convierte una columna del diccionario a número.
     *
     * El Access guarda índices numéricos (2, 37), no letras (B, AK). Se aceptan
     * las dos formas porque no todas las versiones del catálogo usan la misma.
     */
    private function columna(string $valor): int
    {
        $valor = trim($valor);

        if ($valor === '') {
            throw new \RuntimeException('La columna de inicio o fin está vacía en el diccionario.');
        }

        if (ctype_digit($valor)) {
            $indice = (int) $valor;

            if ($indice < 1 || $indice > 16384) {
                throw new \RuntimeException("La columna {$valor} está fuera del rango de una hoja.");
            }

            return $indice;
        }

        if (! ctype_alpha($valor)) {
            throw new \RuntimeException("La columna «{$valor}» no es válida.");
        }

        return Coordinate::columnIndexFromString(strtoupper($valor));
    }

    private function abrir(string $ruta): Spreadsheet
    {
        $lector = IOFactory::createReaderForFile($ruta);
        $lector->setReadDataOnly(false); // las fórmulas se necesitan para ENV-031

        return $lector->load($ruta);
    }
}
