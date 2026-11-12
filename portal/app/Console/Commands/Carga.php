<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Prueba de carga: validaciones simultáneas (T-F6-07).
 *
 * Lanza N procesos `rem:validar` en paralelo sobre el mismo archivo. Sirve
 * para ver qué pasa cuando dos establecimientos suben a la vez: el semáforo
 * deja pasar dos y el tercero espera.
 *
 * Mide tiempo de pared, no de CPU: es lo que percibe el usuario.
 */
class Carga extends Command
{
    protected $signature = 'rem:carga
                            {archivo : Archivo Excel a validar}
                            {--procesos=2 : Cuántas validaciones a la vez}
                            {--rondas=1 : Cuántas veces repetir la prueba}';

    protected $description = 'Mide validaciones concurrentes sobre un archivo real y reporta tiempos.';

    public function handle(): int
    {
        $archivo = (string) $this->argument('archivo');
        $procesos = max(1, (int) $this->option('procesos'));
        $rondas = max(1, (int) $this->option('rondas'));

        if (! is_file($archivo)) {
            $this->error("No existe: {$archivo}");

            return self::FAILURE;
        }

        $php = PHP_BINARY;
        $artisan = base_path('artisan');

        $this->line(sprintf(
            '%s · %s · %d proceso(s) · %d ronda(s)',
            basename($archivo),
            number_format(filesize($archivo) / 1024, 0).' KB',
            $procesos,
            $rondas
        ));
        $this->line('Límite configurado: '.config('rem.validacion.concurrentes').' validaciones a la vez, espera '.config('rem.validacion.espera_lock_segundos').' s.');
        $this->newLine();

        $filas = [];
        $tiempos = [];

        for ($ronda = 1; $ronda <= $rondas; $ronda++) {
            $inicio = microtime(true);
            $salidas = $this->lanzar($php, $artisan, $archivo, $procesos);
            $total = microtime(true) - $inicio;

            $correctos = count(array_filter($salidas, fn (array $s) => $s['codigo'] === 0));
            $tiempos[] = $total;

            $filas[] = [
                $ronda,
                $procesos,
                $correctos,
                round($total, 1),
                round(max(array_column($salidas, 'duracion')), 1),
                $correctos === $procesos ? 'ok' : 'con fallos',
            ];
        }

        $this->table(
            ['Ronda', 'Procesos', 'Correctos', 'Tiempo total (s)', 'Máximo por proceso (s)', 'Resultado'],
            $filas
        );

        sort($tiempos);
        $p95 = $tiempos[max(0, (int) ceil(count($tiempos) * 0.95) - 1)];

        $this->newLine();
        $this->line(sprintf('p95: %.1f s · límite por validación: %d s', $p95, config('rem.validacion.timeout_segundos')));
        $this->line('Memoria por proceso: '.config('rem.validacion.memoria_mb').' MB configurados.');

        if ($p95 > (int) config('rem.validacion.timeout_segundos')) {
            $this->error('El p95 supera el límite de validación.');

            return self::FAILURE;
        }

        $this->info('Dentro del límite.');

        return self::SUCCESS;
    }

    /**
     * Lanza los procesos a la vez y espera a que terminen todos.
     *
     * @return list<array{codigo: int, duracion: float}>
     */
    private function lanzar(string $php, string $artisan, string $archivo, int $procesos): array
    {
        $abiertos = [];

        for ($i = 0; $i < $procesos; $i++) {
            $inicio = microtime(true);

            $proceso = proc_open(
                [$php, $artisan, 'rem:validar', $archivo],
                [1 => ['file', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null', 'w'],
                 2 => ['file', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null', 'w']],
                $tubos,
                base_path()
            );

            if (! is_resource($proceso)) {
                continue;
            }

            $abiertos[] = ['proceso' => $proceso, 'inicio' => $inicio];
        }

        $salidas = [];

        foreach ($abiertos as $abierto) {
            $codigo = proc_close($abierto['proceso']);
            $salidas[] = ['codigo' => $codigo, 'duracion' => microtime(true) - $abierto['inicio']];
        }

        return $salidas;
    }
}
