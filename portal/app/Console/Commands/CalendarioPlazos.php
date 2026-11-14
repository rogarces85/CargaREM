<?php

namespace App\Console\Commands;

use App\Models\Periodo;
use App\Services\CalendarioService;
use Illuminate\Console\Command;

/**
 * Calendario de plazos del año (D-029).
 *
 * Muestra la fecha límite de cada período —fin del quinto día hábil del mes
 * siguiente— y avisa cuando falta cargar los feriados. Con `--actualizar`
 * corrige la fecha de los períodos que no tienen override manual.
 */
class CalendarioPlazos extends Command
{
    protected $signature = 'rem:calendario
                            {anio? : Año a mostrar (por defecto el actual)}
                            {--actualizar : Corrige la fecha de los períodos sin override manual}';

    protected $description = 'Muestra los plazos mensuales y avisa si faltan feriados.';

    public function handle(CalendarioService $calendario): int
    {
        $anio = (int) ($this->argument('anio') ?: now()->year);

        foreach ([$anio, $anio + 1] as $revisar) {
            if (! $calendario->hayFeriados($revisar)) {
                $this->warn("No hay feriados cargados para {$revisar}: el plazo se calcula solo con fines de semana.");
            }
        }

        $periodos = Periodo::where('anio', $anio)->get()->keyBy('mes');
        $filas = [];

        for ($mes = 1; $mes <= 12; $mes++) {
            $fecha = $calendario->fechaLimite($anio, $mes);
            $periodo = $periodos->get($mes);

            $filas[] = [
                sprintf('%02d/%d', $mes, $anio),
                $fecha->format('d-m-Y'),
                $fecha->translatedFormat('l'),
                $periodo?->estado ?? 'sin crear',
                $periodo
                    ? ($periodo->fecha_limite_manual ? 'sí' : 'no')
                    : '—',
            ];
        }

        $this->table(['Período', 'Fecha límite', 'Día', 'Estado', 'Manual'], $filas);

        if (! $this->option('actualizar')) {
            $this->line('Para crear o corregir los períodos del año: php artisan rem:calendario '.$anio.' --actualizar');

            return self::SUCCESS;
        }

        $creados = 0;
        $actualizados = 0;

        for ($mes = 1; $mes <= 12; $mes++) {
            $periodo = $periodos->get($mes);

            if ($periodo === null) {
                Periodo::create([
                    'anio'   => $anio,
                    'mes'    => $mes,
                    'estado' => Periodo::ABIERTO,
                ]);
                $creados++;

                continue;
            }

            if ($periodo->fecha_limite_manual) {
                continue;
            }

            $periodo->fecha_limite = $calendario->fechaLimite($anio, $mes)->toDateString();
            $periodo->save();
            $actualizados++;
        }

        $this->info("Períodos creados: {$creados}. Fechas actualizadas: {$actualizados}.");

        return self::SUCCESS;
    }
}
