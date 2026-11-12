<?php

namespace App\Console\Commands;

use App\Excel\LectorCabecera;
use App\Models\CatalogoSnapshot;
use App\Models\Diccionario;
use App\Models\Envio;
use App\Models\Establecimiento;
use App\Models\Periodo;
use App\Models\Plantilla;
use App\Models\PlantillaHoja;
use App\Models\Serie;
use App\Models\User;
use App\Services\EnvioService;
use App\Services\EstadoEnvioService;
use App\Services\ObservacionService;
use App\Validacion\ValidadorArchivo;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Simula un mes completo de operación (T-F6-13).
 *
 * Recorre el ciclo entero con datos reales del catálogo: el encargado sube las
 * series, el revisor observa una y aprueba el resto, el encargado corrige y se
 * confirma la carga.
 *
 * No sustituye al UAT con personas: una persona encuentra lo que un script no
 * —un mensaje confuso, una pantalla que no dice qué hacer—. Sirve para llegar
 * al UAT con el sistema probado a escala y para tener datos con qué demostrar.
 */
class SimularMes extends Command
{
    protected $signature = 'rem:simular-mes
                            {--establecimientos=3 : Cuántos establecimientos simular}
                            {--anio= : Año del período (por defecto, el de la plantilla más reciente)}
                            {--mes= : Mes del período}
                            {--filas=200 : Filas de datos por hoja}
                            {--observar=1 : Cuántos envíos se observan para probar el rebote}';

    protected $description = 'Simula un mes completo: carga, validación, revisión, corrección y cierre.';

    public function handle(): int
    {
        $snapshot = CatalogoSnapshot::activo()->latest('id')->first();

        if ($snapshot === null) {
            $this->error('No hay catálogo activo. Importe uno primero.');

            return self::FAILURE;
        }

        $plan = $this->elegirPeriodo();

        if ($plan === null) {
            return self::FAILURE;
        }

        [$anio, $mes] = $plan;

        $this->info("Período simulado: {$mes}/{$anio}");
        $this->newLine();

        Periodo::firstOrCreate(
            ['anio' => $anio, 'mes' => $mes],
            ['estado' => Periodo::ABIERTO, 'fecha_limite' => now()->setDate($anio, $mes, 10)->format('Y-m-d')]
        );

        $encargados = User::role('encargado')->where('activo', true)->get();
        $revisor = User::role('revisor')->where('activo', true)->first();

        if ($encargados->isEmpty() || $revisor === null) {
            $this->error('Faltan usuarios: ejecute php artisan db:seed');

            return self::FAILURE;
        }

        $establecimientos = $this->elegirEstablecimientos($anio, $mes);

        if ($establecimientos->isEmpty()) {
            $this->error('Ningún establecimiento tiene plantilla para ese período.');

            return self::FAILURE;
        }

        $filas = [];
        $observados = 0;
        $limiteObservar = (int) $this->option('observar');
        $errores = [];

        foreach ($establecimientos as $indice => $establecimiento) {
            $this->line('· '.$establecimiento->codigo.' '.$establecimiento->nombre);

            $encargado = $encargados[$indice % $encargados->count()];

            $resultado = $this->simularEnvio($establecimiento, $anio, $mes, $encargado, $revisor, $observados < $limiteObservar);

            if ($resultado['estado'] === 'error') {
                $errores[] = $establecimiento->codigo.': '.$resultado['detalle'];
                $filas[] = [$establecimiento->codigo, $establecimiento->nombre, '—', 'error', $resultado['detalle']];

                continue;
            }

            if ($resultado['observado']) {
                $observados++;
            }

            $filas[] = [
                $establecimiento->codigo,
                mb_substr($establecimiento->nombre, 0, 30),
                $resultado['series'].' / '.$resultado['exigidas'],
                $resultado['estado'],
                $resultado['detalle'],
            ];
        }

        $this->newLine();
        $this->table(['Código', 'Establecimiento', 'Series', 'Estado final', 'Detalle'], $filas);

        $this->newLine();
        $this->line(sprintf(
            '%d establecimientos · %d observados y corregidos · %d con error',
            count($establecimientos), $observados, count($errores)
        ));

        if ($errores !== []) {
            $this->error('Incidencias:');
            foreach ($errores as $error) {
                $this->line('  · '.$error);
            }
        }

        return $errores === [] ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Recorre el ciclo completo de un establecimiento.
     *
     * @return array{estado: string, series: int, exigidas: int, detalle: string, observado: bool}
     */
    private function simularEnvio(
        Establecimiento $establecimiento,
        int $anio,
        int $mes,
        User $encargado,
        User $revisor,
        bool $observar,
    ): array {
        $envios = app(EnvioService::class);
        $estados = app(EstadoEnvioService::class);

        try {
            $envio = $envios->crear($establecimiento, $anio, $mes, $encargado);
        } catch (\Throwable $e) {
            // Si ya existe, se reutiliza: la simulación es repetible.
            $envio = Envio::where('establecimiento_id', $establecimiento->id)
                ->where('anio', $anio)->where('mes', $mes)->first();

            if ($envio === null) {
                return ['estado' => 'error', 'series' => 0, 'exigidas' => 0, 'detalle' => $e->getMessage(), 'observado' => false];
            }
        }

        $exigidas = $establecimiento->series()->wherePivot('obligatoria', true)->orderBy('orden')->get();
        $subidas = 0;
        $primeraSerie = null;

        foreach ($exigidas as $serie) {
            $ruta = $this->generarArchivo($establecimiento, $serie, $anio, $mes);

            if ($ruta === null) {
                continue;
            }

            try {
                $registro = $envios->subirVersion(
                    $envio,
                    $serie,
                    new UploadedFile($ruta, "{$establecimiento->codigo}{$serie->codigo}".str_pad((string) $mes, 2, '0', STR_PAD_LEFT).'.xlsx', null, null, true),
                    $encargado
                );

                app(ValidadorArchivo::class)->validar($registro);
                $estados->recalcular($envio->fresh());
                $subidas++;
                $primeraSerie ??= $registro;
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::channel('validacion')->warning('Simulación: no se pudo subir una serie.', [
                    'envio' => $envio->id,
                    'serie' => $serie->codigo,
                    'error' => $e->getMessage(),
                ]);
            } finally {
                @unlink($ruta);
            }
        }

        $envio = $envio->fresh();

        if ($envio->estado === Envio::CON_ERRORES) {
            return [
                'estado' => 'con errores',
                'series' => $subidas,
                'exigidas' => $exigidas->count(),
                'detalle' => 'La validación dejó bloqueantes',
                'observado' => false,
            ];
        }

        // El encargado envía a revisión.
        $estados->transicionar($envio, Envio::EN_REVISION, $encargado);

        if ($observar && $primeraSerie !== null) {
            // El revisor observa una serie y el encargado corrige.
            app(ObservacionService::class)->registrar(
                $envio->fresh(),
                $revisor,
                'Observación de simulación: revise la hoja A01.',
                $primeraSerie
            );

            $estados->transicionar($envio->fresh(), Envio::OBSERVADO, $revisor, 'Observación de simulación.');

            // El encargado corrige: sube una versión nueva de esa serie. Eso
            // resuelve la observación y devuelve el envío a recibido, igual
            // que lo haría una persona desde la pantalla.
            $serieObservada = Serie::find($primeraSerie->serie_id);
            $rutaCorregida = $this->generarArchivo($establecimiento, $serieObservada, $anio, $mes, 'corregido-'.now()->timestamp);

            if ($rutaCorregida !== null) {
                try {
                    $corregido = $envios->subirVersion(
                        $envio->fresh(),
                        $serieObservada,
                        new UploadedFile($rutaCorregida, "{$establecimiento->codigo}{$serieObservada->codigo}".str_pad((string) $mes, 2, '0', STR_PAD_LEFT).'.xlsx', null, null, true),
                        $encargado
                    );

                    app(ValidadorArchivo::class)->validar($corregido);
                } finally {
                    @unlink($rutaCorregida);
                }
            }

            $envio->refresh();
            $estados->recalcular($envio);

            if ($envio->fresh()->estado !== Envio::VALIDADO) {
                return [
                    'estado' => $envio->fresh()->estado,
                    'series' => $subidas,
                    'exigidas' => $exigidas->count(),
                    'detalle' => 'Quedó trabado después de la corrección',
                    'observado' => true,
                ];
            }

            $estados->transicionar($envio->fresh(), Envio::EN_REVISION, $encargado);
        }

        $estados->aprobar($envio->fresh(), $revisor);
        $estados->confirmarCarga($envio->fresh(), $revisor, 'SIMULACION');

        return [
            'estado' => $envio->fresh()->estado,
            'series' => $subidas,
            'exigidas' => $exigidas->count(),
            'detalle' => $observar ? 'Observado, corregido, aprobado y cargado' : 'Aprobado y cargado',
            'observado' => $observar,
        ];
    }

    /** Genera un Excel con la forma que la plantilla exige. */
    private function generarArchivo(Establecimiento $establecimiento, Serie $serie, int $anio, int $mes, string $marca = ''): ?string
    {
        $plantilla = Plantilla::where('anio', $anio)->where('mes', $mes)->where('serie_id', $serie->id)->first();

        if ($plantilla === null) {
            return null;
        }

        $libro = new Spreadsheet();
        $nombre = $libro->getActiveSheet();
        $nombre->setTitle('NOMBRE');
        $nombre->setCellValue('C3', $establecimiento->codigo);
        $nombre->setCellValue('C6', str_pad((string) $mes, 2, '0', STR_PAD_LEFT));
        $nombre->setCellValue('B7', (string) $anio);
        $nombre->setCellValue('B17', (string) $plantilla->serie_excel);
        $nombre->setCellValue('A9', (string) $plantilla->version);

        if ($marca !== '') {
            // Cambia el hash sin cambiar el contenido útil: es una versión.
            $nombre->setCellValue('Z99', $marca);
        }

        $hojas = PlantillaHoja::where('anio', $anio)->where('serie_interna', $plantilla->serie_interna)->pluck('hoja');
        $limite = (int) $this->option('filas');

        foreach ($hojas as $hojaNombre) {
            $hoja = $libro->createSheet();
            $hoja->setTitle(mb_substr((string) $hojaNombre, 0, 31));

            $filas = Diccionario::where('snapshot_id', $plantilla->snapshot_id)
                ->where('anio', $anio)
                ->where('serie', $plantilla->serie_interna)
                ->where('hoja', $hojaNombre)
                ->orderBy('linea')
                ->limit($limite)
                ->get();

            foreach ($filas as $numero => $fila) {
                $desde = (int) $fila->inicio;
                $hasta = (int) $fila->fin;

                for ($columna = $desde; $columna <= $hasta; $columna++) {
                    $hoja->setCellValue(Coordinate::stringFromColumnIndex($columna).$fila->linea, ($numero + $columna) % 9);
                }
            }
        }

        $ruta = tempnam(sys_get_temp_dir(), 'sim').'.xlsx';
        (new Xlsx($libro))->save($ruta);
        $libro->disconnectWorksheets();

        return $ruta;
    }

    /** @return array{0: int, 1: int}|null */
    private function elegirPeriodo(): ?array
    {
        $anio = $this->option('anio');
        $mes = $this->option('mes');

        if ($anio !== null && $mes !== null) {
            return [(int) $anio, (int) $mes];
        }

        $plantilla = Plantilla::orderByDesc('anio')->orderByDesc('mes')->first();

        if ($plantilla === null) {
            $this->error('El catálogo no tiene plantillas: no se puede simular.');

            return null;
        }

        return [(int) $plantilla->anio, (int) $plantilla->mes];
    }

    /** @return \Illuminate\Support\Collection<int, Establecimiento> */
    private function elegirEstablecimientos(int $anio, int $mes)
    {
        $limite = max(1, (int) $this->option('establecimientos'));

        $conPlantilla = Plantilla::where('anio', $anio)->where('mes', $mes)->distinct()->pluck('serie_id');

        return Establecimiento::where('activo', true)
            ->whereHas('series', fn ($q) => $q->whereIn('series.id', $conPlantilla))
            ->orderBy('codigo')
            ->limit($limite)
            ->get();
    }
}
