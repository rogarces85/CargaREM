<?php

namespace App\Console\Commands;

use App\Excel\LectorCabecera;
use App\Excel\LectorHojas;
use Illuminate\Console\Command;
use Throwable;

/**
 * Inspecciona un archivo REM sin subirlo al portal.
 *
 * Sirve para saber qué declara un archivo antes de cargarlo: establecimiento,
 * período, serie, versión y hojas. Es la herramienta para revisar los archivos
 * de referencia contra los que se mide la paridad.
 */
class InspeccionarArchivo extends Command
{
    protected $signature = 'rem:inspeccionar {ruta : Archivo o carpeta}';

    protected $description = 'Lee la cabecera y las hojas de un Excel REM sin subirlo.';

    public function handle(LectorCabecera $cabeceras, LectorHojas $hojas): int
    {
        $ruta = (string) $this->argument('ruta');

        if (! file_exists($ruta)) {
            $this->error("No existe: {$ruta}");

            return self::FAILURE;
        }

        $archivos = is_dir($ruta)
            ? glob(rtrim($ruta, '/\\').'/*.{xls,xlsx,xlsm}', GLOB_BRACE)
            : [$ruta];

        if ($archivos === [] || $archivos === false) {
            $this->error('No hay archivos Excel en esa ruta.');

            return self::FAILURE;
        }

        $filas = [];

        foreach ($archivos as $archivo) {
            try {
                $cabecera = $cabeceras->leer($archivo);
                $nombres = $hojas->nombres($archivo);
            } catch (Throwable $e) {
                $filas[] = [basename($archivo), '—', '—', '—', 'ERROR: '.$e->getMessage()];

                continue;
            }

            $filas[] = [
                basename($archivo),
                $cabecera->establecimiento ?? '—',
                $cabecera->anio !== null ? sprintf('%02d/%d', (int) $cabecera->mes, $cabecera->anio) : '—',
                $cabecera->serie ?? '—',
                count($nombres).' hojas',
                $cabecera->version ?? '—',
            ];
        }

        $this->table(['Archivo', 'Establecimiento', 'Período', 'Serie', 'Hojas', 'Versión'], $filas);

        return self::SUCCESS;
    }
}
