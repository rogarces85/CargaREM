<?php

namespace App\Console\Commands;

use App\Models\CatalogoSnapshot;
use App\Models\Diccionario;
use App\Models\Excepcion;
use App\Models\Plantilla;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Purga catálogos viejos (riesgo anotado en el manual de operación).
 *
 * Cada importación guarda el diccionario completo: 185.526 filas. Tras tres
 * importaciones el respaldo pasó de 27 MB a 80 MB sin que nadie lo notara.
 *
 * No se borra nunca el snapshot activo, ni uno referenciado por un archivo ya
 * validado: ese archivo se validó con ese catálogo y la referencia tiene que
 * sobrevivir para poder reconstruir el veredicto.
 */
class PurgarCatalogos extends Command
{
    protected $signature = 'catalogos:purgar
                            {--conservar=1 : Cuántos snapshots inactivos conservar además del activo}
                            {--force : Ejecuta sin pedir confirmación}';

    protected $description = 'Borra catálogos inactivos y sus diccionarios, conservando los que tienen archivos asociados.';

    public function handle(): int
    {
        $conservar = max(0, (int) $this->option('conservar'));

        $snapshots = CatalogoSnapshot::orderByDesc('id')->get();

        if ($snapshots->count() <= 1) {
            $this->info('Solo hay un catálogo: no hay nada que purgar.');

            return self::SUCCESS;
        }

        $activo = $snapshots->firstWhere('activo', true);

        // Se conservan: el activo y los N inactivos más recientes.
        $inactivos = $snapshots->where('activo', false)->sortByDesc('id')->values();
        $protegidos = collect([$activo?->id])->filter()->merge($inactivos->take($conservar)->pluck('id'))->all();

        $candidatos = $inactivos->reject(fn ($s) => in_array($s->id, $protegidos, true));

        if ($candidatos->isEmpty()) {
            $this->info('No hay catálogos inactivos que se puedan borrar.');

            return self::SUCCESS;
        }

        $filas = [];
        $borrables = [];

        foreach ($candidatos as $snapshot) {
            $archivos = \App\Models\EnvioArchivo::where('snapshot_id', $snapshot->id)->count();
            $filas[] = [
                $snapshot->id,
                $snapshot->origen,
                $snapshot->created_at?->format('d-m-Y'),
                Diccionario::where('snapshot_id', $snapshot->id)->count(),
                $archivos,
                $archivos > 0 ? 'se conserva (tiene archivos)' : 'se puede borrar',
            ];

            if ($archivos === 0) {
                $borrables[] = $snapshot;
            }
        }

        $this->table(['ID', 'Origen', 'Importado', 'Diccionario', 'Archivos', 'Decisión'], $filas);

        if ($borrables === []) {
            $this->warn('Ninguno se puede borrar: todos tienen archivos validados con ellos.');

            return self::SUCCESS;
        }

        $liberadas = array_sum(array_map(
            fn (CatalogoSnapshot $s) => Diccionario::where('snapshot_id', $s->id)->count(),
            $borrables
        ));

        $this->newLine();
        $this->line(sprintf(
            'Se borrarán %d catálogos y %s filas de diccionario.',
            count($borrables),
            number_format($liberadas)
        ));

        if (! $this->option('force') && ! $this->confirm('¿Continuar?', false)) {
            $this->info('Cancelado.');

            return self::SUCCESS;
        }

        $borrados = 0;

        foreach ($borrables as $snapshot) {
            DB::transaction(function () use ($snapshot) {
                Excepcion::where('snapshot_id', $snapshot->id)->delete();
                Plantilla::where('snapshot_id', $snapshot->id)->delete();
                Diccionario::where('snapshot_id', $snapshot->id)->delete();
                $snapshot->delete();
            });

            $borrados++;
        }

        $this->info("Purga terminada: {$borrados} catálogos borrados.");

        return self::SUCCESS;
    }
}
