<?php

namespace App\Console\Commands;

use Database\Seeders\FeriadosSeeder;
use Illuminate\Console\Command;

/**
 * Carga o corrige los feriados de un año (RF-046.2).
 *
 * La lista vive versionada en `FeriadosSeeder`, contrastada con la fuente
 * oficial. El comando existe para el despliegue y para el año nuevo: cargar los
 * feriados es un paso de operación, no de interfaz.
 */
class CargarFeriados extends Command
{
    protected $signature = 'rem:feriados:cargar
                            {anio? : Año a cargar (por defecto todos los verificados)}';

    protected $description = 'Carga los feriados nacionales verificados de un año.';

    public function handle(): int
    {
        $anio = $this->argument('anio') !== null ? (int) $this->argument('anio') : null;

        if ($anio !== null && ! isset(FeriadosSeeder::FERIADOS[$anio])) {
            $this->error("No hay una lista verificada de feriados para {$anio}.");
            $this->line('Años disponibles: ' . implode(', ', FeriadosSeeder::anios()));
            $this->line('Para un año nuevo, agregue la lista en FeriadosSeeder (contrastada con feriados.cl) o cárguelos desde la pantalla de Feriados.');

            return self::FAILURE;
        }

        $seeder = new FeriadosSeeder();

        foreach ($anio !== null ? [$anio] : FeriadosSeeder::anios() as $objetivo) {
            $filas = $seeder->sembrar($objetivo);
            $this->info("{$objetivo}: {$filas} feriado(s) cargados o corregidos.");
        }

        return self::SUCCESS;
    }
}
