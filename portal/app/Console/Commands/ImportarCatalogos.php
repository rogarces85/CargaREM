<?php

namespace App\Console\Commands;

use App\Exceptions\CatalogoInvalidoException;
use App\Services\CatalogoService;
use Illuminate\Console\Command;

class ImportarCatalogos extends Command
{
    protected $signature = 'catalogos:importar
                            {ruta : Carpeta con los CSV o un ZIP generado por 01-TOOLS/rem-export}
                            {--activar : Activa el snapshot al terminar}
                            {--usuario= : Id del usuario que importa}';

    protected $description = 'Importa un snapshot de catálogos REM desde CSV o ZIP';

    public function handle(CatalogoService $servicio): int
    {
        $ruta = (string) $this->argument('ruta');
        $usuarioId = $this->option('usuario') !== null ? (int) $this->option('usuario') : null;

        try {
            $this->info("Importando desde {$ruta}...");
            $inicio = microtime(true);

            $snapshot = is_dir($ruta)
                ? $servicio->importarDirectorio($ruta, $usuarioId)
                : $servicio->importarZip($ruta, $usuarioId);

            $segundos = round(microtime(true) - $inicio, 1);

            $this->table(
                ['Snapshot', 'Estado', 'Diccionario', 'Establecimientos', 'Segundos'],
                [[
                    $snapshot->id,
                    $snapshot->activo ? 'activo' : 'inactivo',
                    number_format((int) $snapshot->filas_diccionario, 0, ',', '.'),
                    number_format((int) $snapshot->filas_establecimientos, 0, ',', '.'),
                    $segundos,
                ]]
            );

            if ($this->option('activar')) {
                $servicio->activar($snapshot);
                $this->info('Snapshot activado.');
            } else {
                $this->warn('El snapshot quedó inactivo. Actívelo con --activar o desde el panel.');
            }

            return self::SUCCESS;
        } catch (CatalogoInvalidoException $e) {
            $this->error("{$e->codigo}: {$e->getMessage()}");

            if ($e->sugerencia !== '') {
                $this->line("Sugerencia: {$e->sugerencia}");
            }

            return self::FAILURE;
        }
    }
}
