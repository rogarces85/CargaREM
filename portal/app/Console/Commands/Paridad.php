<?php

namespace App\Console\Commands;

use App\Services\ParidadService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Compara el motor contra el resultado esperado del ValidadorRem (T-F2-25).
 *
 * Cada archivo de entrada se acompaña de un `.esperado.json` con el mismo
 * nombre. El comando valida el archivo y compara. Sin el esperado no se puede
 * hablar de paridad: solo de que el motor corrió.
 */
class Paridad extends Command
{
    protected $signature = 'rem:paridad
                            {carpeta : Carpeta con los .xlsm y sus .esperado.json}';

    protected $description = 'Compara el resultado del motor con el esperado del ValidadorRem.';

    public function handle(ParidadService $paridad): int
    {
        $carpeta = rtrim((string) $this->argument('carpeta'), '/\\');

        if (! is_dir($carpeta)) {
            $this->error("No existe la carpeta: {$carpeta}");

            return self::FAILURE;
        }

        $archivos = glob($carpeta.'/*.{xls,xlsx,xlsm}', GLOB_BRACE) ?: [];

        if ($archivos === []) {
            $this->error('No hay archivos Excel en esa carpeta.');

            return self::FAILURE;
        }

        $filas = [];
        $fallas = 0;
        $sinEsperado = 0;

        foreach ($archivos as $archivo) {
            $nombre = basename($archivo);
            $esperadoRuta = $carpeta.'/'.pathinfo($nombre, PATHINFO_FILENAME).'.esperado.json';

            if (! is_file($esperadoRuta)) {
                $filas[] = [$nombre, '—', '—', 'sin esperado'];
                $sinEsperado++;

                continue;
            }

            $esperado = json_decode((string) file_get_contents($esperadoRuta), true);

            if (! is_array($esperado)) {
                $filas[] = [$nombre, '—', '—', 'esperado ilegible'];
                $fallas++;

                continue;
            }

            try {
                $corrida = $paridad->validarArchivo($archivo);
            } catch (Throwable $e) {
                $filas[] = [$nombre, '—', '—', 'error: '.mb_substr($e->getMessage(), 0, 40)];
                $fallas++;

                continue;
            }

            $resultado = $corrida['resultado'];
            $obtenido = [
                'bloqueantes'  => $resultado->bloqueantes(),
                'advertencias' => $resultado->advertencias(),
                'codigos'      => ParidadService::contarPorCodigo($resultado),
            ];

            [$veredicto, $detalle] = $this->comparar($esperado, $obtenido);

            if ($veredicto !== 'OK') {
                $fallas++;
            }

            $filas[] = [$nombre, $this->resumenEsperado($esperado), $this->resumenObtenido($obtenido), $veredicto.($detalle !== '' ? ': '.$detalle : '')];
        }

        $this->newLine();
        $this->table(['Archivo', 'Esperado', 'Obtenido', 'Veredicto'], $filas);

        $this->newLine();
        $this->line(sprintf(
            '%d archivos · %d coinciden · %d no coinciden · %d sin esperado',
            count($archivos), count($archivos) - $fallas - $sinEsperado, $fallas, $sinEsperado
        ));

        if ($sinEsperado > 0) {
            $this->line('Los archivos sin `.esperado.json` no se comparan: para eso hace falta el resultado del ValidadorRem.');
        }

        return $fallas === 0 && $sinEsperado === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @param  array<string, mixed>  $esperado
     * @param  array<string, mixed>  $obtenido
     * @return array{0: string, 1: string}
     */
    private function comparar(array $esperado, array $obtenido): array
    {
        if (isset($esperado['bloqueantes']) && (int) $esperado['bloqueantes'] !== $obtenido['bloqueantes']) {
            return ['DIFERENTE', "bloqueantes esperados {$esperado['bloqueantes']}, obtenidos {$obtenido['bloqueantes']}"];
        }

        if (isset($esperado['advertencias']) && (int) $esperado['advertencias'] !== $obtenido['advertencias']) {
            return ['DIFERENTE', "advertencias esperadas {$esperado['advertencias']}, obtenidas {$obtenido['advertencias']}"];
        }

        if (isset($esperado['codigos']) && is_array($esperado['codigos'])) {
            foreach ($esperado['codigos'] as $codigo => $cantidad) {
                $real = $obtenido['codigos'][$codigo] ?? 0;

                if ((int) $cantidad !== $real) {
                    return ['DIFERENTE', "{$codigo}: esperado {$cantidad}, obtenido {$real}"];
                }
            }
        }

        return ['OK', ''];
    }

    /** @param array<string, mixed> $esperado */
    private function resumenEsperado(array $esperado): string
    {
        return sprintf(
            '%s bloqueantes / %s advertencias',
            $esperado['bloqueantes'] ?? '—',
            $esperado['advertencias'] ?? '—'
        );
    }

    /** @param array<string, mixed> $obtenido */
    private function resumenObtenido(array $obtenido): string
    {
        return sprintf('%d bloqueantes / %d advertencias', $obtenido['bloqueantes'], $obtenido['advertencias']);
    }
}
