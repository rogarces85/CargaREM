<?php

namespace App\Console\Commands;

use App\Models\Periodo;
use App\Services\CalendarioService;
use App\Support\Plazos;
use Illuminate\Console\Command;

/**
 * Calendario de plazos del año (D-029, RF-046).
 *
 * Muestra la fecha límite de cada período —fin del quinto día hábil del mes
 * siguiente— y avisa cuando falta cargar los feriados.
 *
 * Tres modos, y ninguno depende del otro:
 *
 * - sin opciones: solo muestra;
 * - `--actualizar`: crea los períodos que faltan y corrige las fechas que no
 *   sean manuales;
 * - `--verificar`: compara lo guardado con lo calculado y devuelve código de
 *   salida ≠ 0 si hay diferencias. Es el modo que sirve para una verificación
 *   automática (CI) sin cambiar nada.
 */
class CalendarioPlazos extends Command
{
    protected $signature = 'rem:calendario
                            {anio? : Año a mostrar (por defecto el actual)}
                            {--actualizar : Crea los períodos que faltan y corrige las fechas sin override manual}
                            {--verificar : Compara lo guardado con lo calculado y falla si hay diferencias}';

    protected $description = 'Muestra los plazos mensuales, los actualiza o los verifica contra el calendario.';

    public function handle(CalendarioService $calendario): int
    {
        $anio = (int) ($this->argument('anio') ?: now()->year);

        foreach ([$anio, $anio + 1] as $revisar) {
            if (! $calendario->hayFeriados($revisar)) {
                $this->warn("No hay feriados cargados para {$revisar}: el plazo se calcula solo con fines de semana.");
                $this->line("  Cárguelos con: php artisan rem:feriados:cargar {$revisar}");
            }
        }

        $periodos = Periodo::where('anio', $anio)->get()->keyBy('mes');
        $filas = [];
        $diferencias = 0;

        for ($mes = 1; $mes <= 12; $mes++) {
            $fecha = $calendario->fechaLimite($anio, $mes);
            $periodo = $periodos->get($mes);

            $guardada = $periodo?->fecha_limite?->toDateString();
            $calculada = $fecha->toDateString();

            $manual = $periodo?->fecha_limite_manual === true;
            $coincide = $guardada === null || $guardada === $calculada || $manual;

            if (! $coincide) {
                $diferencias++;
            }

            $filas[] = [
                sprintf('%02d/%d', $mes, $anio),
                $fecha->format('d-m-Y'),
                $fecha->translatedFormat('l'),
                $periodo?->estado ?? 'sin crear',
                $manual ? 'sí' : 'no',
                $coincide ? '✓' : '≠ ' . ($guardada ?? '—'),
            ];
        }

        $this->table(['Período', 'Fecha calculada', 'Día', 'Estado', 'Manual', 'Guardada'], $filas);

        if ($this->option('verificar')) {
            if ($diferencias > 0) {
                $this->error("Hay {$diferencias} período(s) cuya fecha guardada no coincide con el calendario.");
                $this->line('Para corregirlas: php artisan rem:calendario ' . $anio . ' --actualizar');

                return self::FAILURE;
            }

            $this->info('El calendario guardado coincide con el calculado.');

            return self::SUCCESS;
        }

        if (! $this->option('actualizar')) {
            $this->line('Para crear o corregir los períodos del año: php artisan rem:calendario ' . $anio . ' --actualizar');
            $this->line('Para verificar sin cambiar nada: php artisan rem:calendario ' . $anio . ' --verificar');

            return self::SUCCESS;
        }

        $resultado = app(Plazos::class)->generar($anio);

        $this->info("Períodos creados: {$resultado['creados']}. Fechas actualizadas: {$resultado['actualizados']}.");

        return self::SUCCESS;
    }
}
