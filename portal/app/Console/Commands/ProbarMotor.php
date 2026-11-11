<?php

namespace App\Console\Commands;

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
use App\Validacion\ValidadorArchivo;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Genera un envío de prueba y lo valida, para poder ver el motor funcionando
 * sin esperar a las pantallas.
 *
 * No toca datos reales: crea un establecimiento de prueba si no encuentra uno
 * y usa el catálogo ya importado para armar un Excel con la forma correcta.
 */
class ProbarMotor extends Command
{
    protected $signature = 'rem:probar
                            {--serie=A : Serie a probar}
                            {--filas=300 : Cuántas prestaciones rellenar por hoja}
                            {--establecimiento= : Código de establecimiento (por defecto uno de prueba)}
                            {--sucio : Genera el archivo con errores a propósito}
                            {--guardar= : Guarda una copia del Excel en esta ruta}';

    protected $description = 'Crea un envío de prueba, genera su Excel y muestra el reporte de validación.';

    public function handle(): int
    {
        $snapshot = CatalogoSnapshot::activo()->latest('id')->first();

        if ($snapshot === null) {
            $this->error('No hay catálogo activo. Importe uno primero:');
            $this->line('  php artisan catalogos:importar <ruta> --activar');

            return self::FAILURE;
        }

        $serieCodigo = strtoupper((string) $this->option('serie'));
        $serie = Serie::where('codigo', $serieCodigo)->first();

        if ($serie === null) {
            $this->error("La serie {$serieCodigo} no existe en el catálogo.");

            return self::FAILURE;
        }

        $plantilla = Plantilla::where('serie_id', $serie->id)->orderByDesc('anio')->orderByDesc('mes')->first();

        if ($plantilla === null) {
            $this->error("El catálogo no tiene plantilla de la serie {$serieCodigo}. No se puede armar el archivo.");

            return self::FAILURE;
        }

        $anio = (int) $plantilla->anio;
        $mes = (int) $plantilla->mes;

        $periodo = Periodo::firstOrCreate(
            ['anio' => $anio, 'mes' => $mes],
            ['estado' => Periodo::ABIERTO]
        );

        if ($periodo->estado === Periodo::CERRADO) {
            $periodo->update(['estado' => Periodo::ABIERTO]);
        }

        $establecimiento = $this->establecimiento($serie);
        $usuario = User::where('email', 'admin@ssosorno.cl')->first() ?? User::first();

        if ($usuario === null) {
            $this->error('No hay usuarios. Ejecute: php artisan db:seed');

            return self::FAILURE;
        }

        $this->line("Período {$mes}/{$anio} · serie {$serieCodigo} · establecimiento {$establecimiento->codigo}");

        $envio = Envio::firstOrCreate(
            ['establecimiento_id' => $establecimiento->id, 'anio' => $anio, 'mes' => $mes],
            ['estado' => Envio::RECIBIDO, 'enviado_por' => $usuario->id, 'fecha_envio' => now()]
        );

        $ruta = $this->generarExcel($plantilla, $establecimiento, $anio, $mes, (int) $this->option('filas'));
        $nombre = "{$establecimiento->codigo}{$serieCodigo}".str_pad((string) $mes, 2, '0', STR_PAD_LEFT).'.xlsx';

        $archivo = new UploadedFile($ruta, $nombre, null, null, true);

        $servicio = app(EnvioService::class);

        try {
            $registro = $servicio->subirVersion($envio, $serie, $archivo, $usuario);
        } catch (\Throwable $e) {
            $this->error('No se pudo subir: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->line('Archivo subido: '.$registro->ruta);

        $resultado = app(ValidadorArchivo::class)->validar($registro);
        $estado = app(EstadoEnvioService::class)->recalcular($envio->fresh());

        $this->newLine();
        $this->info('Resultado: '.($resultado->esValido() ? 'sin bloqueantes' : 'con bloqueantes'));
        $this->line("  Bloqueantes: {$resultado->bloqueantes()} · Advertencias: {$resultado->advertencias()}");
        $this->line("  Estado del envío: {$estado}");

        if ($resultado->hallazgos !== []) {
            $this->newLine();
            $this->table(
                ['Etapa', 'Código', 'Severidad', 'Mensaje'],
                array_map(fn ($h) => [
                    $h->etapa, $h->codigo, $h->severidad, mb_substr($h->mensaje, 0, 70),
                ], array_slice($resultado->hallazgos, 0, 25))
            );

            if (count($resultado->hallazgos) > 25) {
                $this->line('… y '.(count($resultado->hallazgos) - 25).' hallazgos más en la base.');
            }
        }

        $this->newLine();
        $this->line('Para ver la bitácora:');
        $this->line('  php artisan tinker --execute="dump(App\Models\Auditoria::latest(\'id\')->take(5)->get([\'evento\',\'entidad\',\'entidad_id\'])->toArray());"');

        $destino = (string) $this->option('guardar');

        if ($destino !== '') {
            $carpeta = dirname($destino);

            if (! is_dir($carpeta)) {
                mkdir($carpeta, 0775, true);
            }

            copy($ruta, $destino);
            $this->info("Excel guardado en {$destino}");
            $this->line('Súbalo desde el navegador en: '.route('envios.index'));
        }

        Storage::disk('local')->delete($ruta);

        return self::SUCCESS;
    }

    /**
     * Establecimiento de prueba: exige SOLO la serie que se está probando.
     *
     * Con uno real, V11 bloquearía por las otras cinco series y el reporte
     * quedaría tapado por la completitud. Para probar contra uno real:
     *   php artisan rem:probar --establecimiento=123100
     */
    private function establecimiento(Serie $serie): Establecimiento
    {
        $codigo = (string) ($this->option('establecimiento') ?: '999999');

        $servicio = \App\Models\Servicio::firstOrCreate(
            ['codigo' => '99'],
            ['nombre' => 'Servicio de prueba', 'activo' => true]
        );

        $establecimiento = Establecimiento::firstOrCreate(
            ['codigo' => $codigo],
            ['nombre' => "Establecimiento de prueba {$codigo}", 'servicio_id' => $servicio->id, 'activo' => true]
        );

        if ($codigo === '999999') {
            $establecimiento->series()->sync([$serie->id => ['obligatoria' => true]]);
        }

        return $establecimiento;
    }

    /** El Access guarda índices numéricos de columna; se aceptan letras también. */
    private function columna(string $valor): int
    {
        $valor = trim($valor);

        return ctype_digit($valor)
            ? (int) $valor
            : Coordinate::columnIndexFromString(strtoupper($valor));
    }

    /** Genera un .xlsx con la cabecera y las hojas que la plantilla exige. */
    private function generarExcel(Plantilla $plantilla, Establecimiento $establecimiento, int $anio, int $mes, int $limite): string
    {
        $libro = new Spreadsheet();
        $nombre = $libro->getActiveSheet();
        $nombre->setTitle('NOMBRE');
        $nombre->setCellValue('C3', $establecimiento->codigo);
        $nombre->setCellValue('C6', str_pad((string) $mes, 2, '0', STR_PAD_LEFT));
        $nombre->setCellValue('B7', (string) $anio);
        $nombre->setCellValue('B17', $plantilla->serie_excel);
        $nombre->setCellValue('A9', (string) $plantilla->version);

        $sucio = (bool) $this->option('sucio');

        $hojas = PlantillaHoja::where('anio', $anio)
            ->where('serie_interna', $plantilla->serie_interna)
            ->pluck('hoja');

        foreach ($hojas as $nombreHoja) {
            // Siempre una hoja nueva: la de NOMBRE ya existe y es la que V2
            // necesita para leer la cabecera.
            $hoja = $libro->createSheet();
            $hoja->setTitle(mb_substr((string) $nombreHoja, 0, 31));

            $filas = Diccionario::where('snapshot_id', $plantilla->snapshot_id)
                ->where('anio', $anio)
                ->where('serie', $plantilla->serie_interna)
                ->where('hoja', $nombreHoja)
                ->orderBy('linea')
                ->limit($limite)
                ->get();

            foreach ($filas as $numero => $fila) {
                $desde = $this->columna((string) $fila->inicio);
                $hasta = $this->columna((string) $fila->fin);

                for ($columna = $desde; $columna <= $hasta; $columna++) {
                    $celda = Coordinate::stringFromColumnIndex($columna).$fila->linea;

                    // En modo sucio: una celda con texto en el primer dato, para
                    // ver ENV-020 en el reporte.
                    if ($sucio && $numero === 0 && $columna === $desde) {
                        $hoja->setCellValue($celda, 'sin dato');

                        continue;
                    }

                    $hoja->setCellValue($celda, ($numero + $columna) % 7);
                }
            }
        }

        $ruta = tempnam(sys_get_temp_dir(), 'rem_probar').'.xlsx';
        (new Xlsx($libro))->save($ruta);
        $libro->disconnectWorksheets();

        return $ruta;
    }
}
