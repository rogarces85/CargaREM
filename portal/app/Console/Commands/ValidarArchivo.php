<?php

namespace App\Console\Commands;

use App\Excel\LectorCabecera;
use App\Models\CatalogoSnapshot;
use App\Models\Envio;
use App\Models\Establecimiento;
use App\Models\Periodo;
use App\Models\Serie;
use App\Models\Servicio;
use App\Models\User;
use App\Services\EnvioService;
use App\Validacion\ValidadorArchivo;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Throwable;

/**
 * Valida un archivo REM real contra el catálogo cargado.
 *
 * Es la herramienta de paridad: se le pasa un archivo del Minsal y muestra qué
 * encuentra el motor. Los datos del envío salen del propio archivo, no se
 * inventan, para que el resultado sea el que tendría una carga real.
 */
class ValidarArchivo extends Command
{
    protected $signature = 'rem:validar
                            {ruta : Archivo Excel REM}
                            {--detalle : Muestra todos los hallazgos, no solo el resumen}';

    protected $description = 'Sube y valida un archivo REM real, y muestra el reporte del motor.';

    public function handle(LectorCabecera $cabeceras): int
    {
        $ruta = (string) $this->argument('ruta');

        if (! is_file($ruta)) {
            $this->error("No existe: {$ruta}");

            return self::FAILURE;
        }

        $snapshot = CatalogoSnapshot::activo()->latest('id')->first();

        if ($snapshot === null) {
            $this->error('No hay catálogo activo. Importe uno primero.');

            return self::FAILURE;
        }

        try {
            $cabecera = $cabeceras->leer($ruta);
        } catch (Throwable $e) {
            $this->error('No se pudo leer la cabecera: '.$e->getMessage());

            return self::FAILURE;
        }

        if (! $cabecera->identificable()) {
            $this->error('La cabecera no identifica establecimiento, serie, año y mes.');

            return self::FAILURE;
        }

        $this->line(sprintf(
            '%s · establecimiento %s · %02d/%d · serie %s',
            basename($ruta), $cabecera->establecimiento, (int) $cabecera->mes, (int) $cabecera->anio, $cabecera->serie
        ));

        $serie = Serie::where('codigo', strtoupper((string) $cabecera->serie))->first();

        if ($serie === null) {
            $this->error("La serie {$cabecera->serie} no está en el catálogo.");

            return self::FAILURE;
        }

        $servicio = Servicio::firstOrCreate(
            ['codigo' => '99'],
            ['nombre' => 'Servicio de prueba', 'activo' => true]
        );

        $establecimiento = Establecimiento::firstOrCreate(
            ['codigo' => (string) $cabecera->establecimiento],
            ['nombre' => "Establecimiento {$cabecera->establecimiento}", 'servicio_id' => $servicio->id, 'activo' => true]
        );

        // Solo la serie del archivo: la completitud no debe tapar el resultado.
        $establecimiento->series()->sync([$serie->id => ['obligatoria' => true]]);

        Periodo::firstOrCreate(
            ['anio' => (int) $cabecera->anio, 'mes' => (int) $cabecera->mes],
            ['estado' => Periodo::ABIERTO]
        );

        $usuario = User::where('email', 'admin@ssosorno.cl')->first() ?? User::first();

        $envio = Envio::firstOrCreate(
            [
                'establecimiento_id' => $establecimiento->id,
                'anio'               => (int) $cabecera->anio,
                'mes'                => (int) $cabecera->mes,
            ],
            ['estado' => Envio::RECIBIDO, 'enviado_por' => $usuario->id, 'fecha_envio' => now()]
        );

        // Si el archivo ya está en el envío (mismo hash) se revalida el que
        // existe en vez de re-subirlo: el comando es para inspeccionar, y
        // revalidar no debe chocar con la protección de duplicados.
        $hash = hash_file('sha256', $ruta);

        $registro = $envio->archivos()
            ->where('serie_id', $serie->id)
            ->where('sha256', $hash)
            ->latest('version')
            ->first();

        if ($registro !== null) {
            $this->line('El archivo ya estaba en el envío: se revalida la versión '.$registro->version.'.');
        } else {
            $archivo = new UploadedFile($ruta, basename($ruta), null, null, true);

            try {
                $registro = app(EnvioService::class)->subirVersion($envio, $serie, $archivo, $usuario);
            } catch (Throwable $e) {
                $this->error('Rechazado en la subida: '.$e->getMessage());

                return self::FAILURE;
            }
        }

        $inicio = microtime(true);
        $resultado = app(ValidadorArchivo::class)->validar($registro);
        $segundos = round(microtime(true) - $inicio, 2);

        $this->newLine();
        $this->table(['Bloqueantes', 'Advertencias', 'Tiempo'], [[
            $resultado->bloqueantes(), $resultado->advertencias(), "{$segundos} s",
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

        $conteo = [];

        foreach ($hallazgos as $hallazgo) {
            $clave = $hallazgo->codigo.'|'.$hallazgo->severidad;
            $conteo[$clave] = ($conteo[$clave] ?? 0) + 1;
        }

        ksort($conteo);

        $filas = [];

        foreach ($conteo as $clave => $cantidad) {
            [$codigo, $severidad] = explode('|', $clave);
            $filas[] = [$codigo, $severidad, $cantidad];
        }

        $this->table(['Código', 'Severidad', 'Cantidad'], $filas);
    }
}
