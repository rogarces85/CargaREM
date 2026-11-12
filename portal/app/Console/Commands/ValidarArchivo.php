<?php

namespace App\Console\Commands;

use App\Services\ParidadService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Valida un archivo REM real contra el catálogo cargado.
 *
 * Comparte el recorrido con `rem:paridad` a través de `ParidadService`: subir
 * y validar debe comportarse igual en los dos sitios, o la comparación mediría
 * otra cosa.
 */
class ValidarArchivo extends Command
{
    protected $signature = 'rem:validar
                            {ruta : Archivo Excel REM}
                            {--detalle : Muestra todos los hallazgos, no solo el resumen}';

    protected $description = 'Sube y valida un archivo REM real, y muestra el reporte del motor.';

    public function handle(ParidadService $paridad): int
    {
        $ruta = (string) $this->argument('ruta');

        if (! is_file($ruta)) {
            $this->error("No existe: {$ruta}");

            return self::FAILURE;
        }

        try {
            $corrida = $paridad->validarArchivo($ruta);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $cabecera = $corrida['cabecera'];
        $resultado = $corrida['resultado'];

        $this->line(sprintf(
            '%s · establecimiento %s · %02d/%d · serie %s',
            basename($ruta), $cabecera->establecimiento, (int) $cabecera->mes, (int) $cabecera->anio, $cabecera->serie
        ));

        if ($corrida['reutilizado']) {
            $this->line('El archivo ya estaba en el envío: se revalida el existente.');
        }

        $this->newLine();
        $this->table(['Bloqueantes', 'Advertencias'], [[
            $resultado->bloqueantes(), $resultado->advertencias(),
        ]]);

        $this->porCodigo($resultado->hallazgos);

        if ($this->option('detalle')) {
            $this->newLine();
            $this->table(
                ['Etapa', 'Código', 'Severidad', 'Hoja', 'Celda', 'Mensaje'],
                array_map(fn ($h) => [
                    $h->etapa, $h->codigo, $h->severidad, $h->hoja ?? '—', $h->celda ?? '—', mb_substr($h->mensaje, 0, 70),
                ], array_slice($resultado->hallazgos, 0, 60))
            );
        }

        return self::SUCCESS;
    }

    /** @param list<\App\Validacion\Hallazgo> $hallazgos */
    private function porCodigo(array $hallazgos): void
    {
        if ($hallazgos === []) {
            $this->info('Sin hallazgos.');

            return;
        }

        $filas = [];

        foreach (ParidadService::contarPorCodigo(new \App\Validacion\ResultadoValidacion($hallazgos, '')) as $codigo => $cantidad) {
            $severidad = collect($hallazgos)->firstWhere('codigo', $codigo)?->severidad ?? '—';
            $filas[] = [$codigo, $severidad, $cantidad];
        }

        $this->table(['Código', 'Severidad', 'Cantidad'], $filas);
    }
}
