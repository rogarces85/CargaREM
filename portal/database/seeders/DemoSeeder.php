<?php

namespace Database\Seeders;

use App\Models\Envio;
use App\Models\EnvioArchivo;
use App\Models\Establecimiento;
use App\Models\Periodo;
use App\Models\Serie;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Datos de demostración para un período.
 *
 * **No forma parte de `DatabaseSeeder`, y es a propósito.** Un `db:seed` normal
 * no debe inventar estadísticas. Este seeder se ejecuta aparte y solo en local:
 *
 *     php artisan db:seed --class=DemoSeeder
 *
 * Deja el período 10/2026 con envíos repartidos por los cinco estados de la
 * matriz de completitud, para que el semáforo y el gráfico se vean con color
 * desde el primer minuto de una demostración.
 *
 * Es **idempotente y no destructivo**: antes de crear, borra solo los envíos que
 * él mismo marcó con `MARCA` en `observacion_general`. Los envíos reales no se
 * tocan.
 *
 * Para retirar los datos: volver a ejecutarlo (los reemplaza) o borrar los
 * envíos cuya `observacion_general` sea la marca.
 */
class DemoSeeder extends Seeder
{
    /**
     * Marca que identifica lo que creó este seeder.
     *
     * Va en `observacion_general`, que la interfaz no muestra, así que no
     * ensucia ninguna pantalla. Si algún día se muestra, se explica sola.
     */
    public const MARCA = 'Datos de demostración. No son estadísticas reales.';

    /** Período que se puebla. */
    public const ANIO = 2026;
    public const MES = 10;

    /**
     * Qué hacer con cada establecimiento.
     *
     * `estado`  estado final del envío.
     * `tomar`   cuántas series reciben archivo. `null` = todas las exigidas.
     * `error_en` cuántos de esos archivos quedan con errores bloqueantes.
     *
     * `null` en la fila = ese establecimiento no crea envío, para que la matriz
     * tenga también celdas «sin envío».
     *
     * @var array<string, array{estado: string, tomar: ?int, error_en?: int, dias: int, fuera_de_plazo?: bool}|null>
     */
    private const GUION = [
        '123100' => ['estado' => Envio::APROBADO,    'tomar' => null, 'dias' => 3],
        '123101' => ['estado' => Envio::EN_REVISION, 'tomar' => null, 'dias' => 4],
        '123102' => ['estado' => Envio::CON_ERRORES, 'tomar' => 4,    'dias' => 5],
        '123103' => ['estado' => Envio::CON_ERRORES, 'tomar' => null, 'error_en' => 1, 'dias' => 2],
        '123104' => ['estado' => Envio::OBSERVADO,   'tomar' => null, 'dias' => 9, 'fuera_de_plazo' => true],
        '123105' => ['estado' => Envio::VALIDADO,    'tomar' => null, 'dias' => 5],
        '123106' => null,
    ];

    public function run(): void
    {
        // Solo en local. En cualquier otro entorno esto ensuciaría datos reales.
        if (! app()->environment('local')) {
            $this->command?->error('DemoSeeder solo corre en APP_ENV=local. No se hizo nada.');

            return;
        }

        $periodo = Periodo::where('anio', self::ANIO)->where('mes', self::MES)->first();

        if ($periodo === null) {
            $this->command?->error(
                'No existe el período ' . self::MES . '/' . self::ANIO . '. ' .
                'Créelo primero en Administración → Calendario de plazos.'
            );

            return;
        }

        $this->limpiar();

        $creados = 0;

        foreach (self::GUION as $codigo => $plan) {
            $establecimiento = Establecimiento::where('codigo', $codigo)->first();

            if ($establecimiento === null) {
                $this->command?->warn("  Establecimiento {$codigo} no existe: se omite.");

                continue;
            }

            if ($plan === null) {
                $this->command?->line("  {$codigo}  sin envío (a propósito)");

                continue;
            }

            $this->crearEnvio($establecimiento, $periodo, $plan);
            $creados++;
        }

        $this->command?->info("DemoSeeder: {$creados} envíos creados para el período " . self::MES . '/' . self::ANIO . '.');
        $this->command?->line('  Para retirarlos, vuelva a ejecutar el seeder o borre los envíos marcados.');
    }

    /**
     * Borra los envíos que creó este seeder y sus archivos en disco.
     *
     * Se borra explícitamente en vez de confiar en las cascadas: las
     * validaciones del envío (V11) cuelgan de `envio_id`, no del archivo.
     */
    private function limpiar(): void
    {
        $ids = DB::table('envios')
            ->where('anio', self::ANIO)
            ->where('mes', self::MES)
            ->where('observacion_general', self::MARCA)
            ->pluck('id')
            ->all();

        Storage::disk('local')->deleteDirectory('envios/demo');

        if ($ids === []) {
            return;
        }

        DB::table('validaciones')->whereIn('envio_id', $ids)->delete();
        DB::table('envio_archivos')->whereIn('envio_id', $ids)->delete();
        DB::table('envios')->whereIn('id', $ids)->delete();

        $this->command?->line('  Limpiados ' . count($ids) . ' envíos de demostración anteriores.');
    }

    /**
     * @param  array{estado: string, tomar: ?int, error_en?: int, dias: int, fuera_de_plazo?: bool}  $plan
     */
    private function crearEnvio(Establecimiento $establecimiento, Periodo $periodo, array $plan): void
    {
        $encargado = $this->encargadoDe($establecimiento);
        $revisor = User::role('revisor')->where('activo', true)->first();

        $fechaEnvio = now()
            ->setDate(self::ANIO, self::MES + 1, min($plan['dias'], 28))
            ->setTime(9, 30);

        $envio = Envio::create([
            'establecimiento_id'  => $establecimiento->id,
            'anio'                => self::ANIO,
            'mes'                 => self::MES,
            'estado'              => $plan['estado'],
            'enviado_por'         => $encargado?->id,
            'revisor_id'          => in_array($plan['estado'], [Envio::EN_REVISION, Envio::OBSERVADO, Envio::APROBADO], true)
                ? $revisor?->id
                : null,
            'observacion_general' => self::MARCA,
            'fecha_envio'         => $fechaEnvio,
            'fecha_revision'      => in_array($plan['estado'], [Envio::EN_REVISION, Envio::OBSERVADO, Envio::APROBADO], true)
                ? $fechaEnvio->copy()->addDay()
                : null,
            'fecha_aprobacion'    => $plan['estado'] === Envio::APROBADO
                ? $fechaEnvio->copy()->addDays(2)
                : null,
            'fecha_limite'        => $periodo->fecha_limite,
            'fuera_de_plazo'      => $plan['fuera_de_plazo'] ?? null,
        ]);

        $exigidas = $establecimiento->series()
            ->where('series.activo', true)
            ->wherePivot('obligatoria', true)
            ->orderBy('orden')
            ->get();

        $conArchivo = $plan['tomar'] === null
            ? $exigidas
            : $exigidas->take($plan['tomar']);

        $conError = $plan['error_en'] ?? 0;
        $posicion = 0;

        foreach ($conArchivo as $serie) {
            $posicion++;
            $this->archivo($envio, $serie, $posicion <= $conError, $plan['estado']);
        }

        // Las series que la matriz exige y no llegaron dejan su hallazgo V11.
        $faltantes = $exigidas->pluck('codigo')->diff($conArchivo->pluck('codigo'))->values();

        if ($faltantes->isNotEmpty()) {
            $this->hallazgoEnvio($envio, [
                'etapa'     => 'V11',
                'codigo'    => 'ENV-025',
                'severidad' => 'bloqueante',
                'regla'     => 'R-V11-01',
                'mensaje'   => 'Faltan series obligatorias: ' . $faltantes->implode(', ') . '.',
            ]);
        }

        $this->transiciones($envio, $encargado, $revisor);

        if ($plan['estado'] === Envio::OBSERVADO) {
            $this->observacion($envio, $revisor, $conArchivo->first());
        }

        $this->command?->line(sprintf(
            '  %s  %-12s %d/%d series%s',
            $establecimiento->codigo,
            $plan['estado'],
            $conArchivo->count(),
            $exigidas->count(),
            $plan['fuera_de_plazo'] ?? false ? '  (fuera de plazo)' : ''
        ));
    }

    /**
     * Escribe el archivo en disco y crea su fila.
     *
     * El archivo es real y pequeño: así el botón «Descargar» del detalle
     * funciona y no aparece un 404 en medio de la demostración.
     */
    private function archivo(Envio $envio, Serie $serie, bool $conError, string $estadoEnvio): void
    {
        $etiqueta = sprintf(
            'DEMOSTRACION - %s - %s - %02d/%d - envio %d',
            $envio->establecimiento->codigo,
            $serie->codigo,
            $envio->mes,
            $envio->anio,
            $envio->id
        );

        $libro = new Spreadsheet();
        $hoja = $libro->getActiveSheet();
        $hoja->setTitle('NOMBRE');
        $hoja->setCellValue('A1', $etiqueta);
        $hoja->setCellValue('A2', 'Archivo generado por DemoSeeder. No contiene datos reales.');

        $temporal = tempnam(sys_get_temp_dir(), 'demo') . '.xlsx';
        (new Xlsx($libro))->save($temporal);
        $contenido = file_get_contents($temporal);
        $libro->disconnectWorksheets();

        $ruta = sprintf('envios/demo/%d/%02d/%s/%s.xlsx', $envio->anio, $envio->mes, $envio->establecimiento->codigo, $serie->codigo);

        Storage::disk('local')->put($ruta, $contenido);
        @unlink($temporal);

        $archivo = $envio->archivos()->create([
            'serie_id'        => $serie->id,
            'version'         => 1,
            'es_vigente'      => true,
            'nombre_original' => sprintf('%s%s%02d.xlsx', $envio->establecimiento->codigo, $serie->codigo, $envio->mes),
            'ruta'            => $ruta,
            'disco'           => 'local',
            'sha256'          => hash('sha256', $contenido),
            'tamano_bytes'    => strlen($contenido),
            'mime'            => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'estado'          => $conError
                ? EnvioArchivo::CON_ERRORES
                : ($estadoEnvio === Envio::APROBADO ? EnvioArchivo::APROBADO : EnvioArchivo::VALIDO),
            'filas_leidas'    => 120,
            'filas_validas'   => $conError ? 118 : 120,
            'filas_error'     => $conError ? 2 : 0,
            'validado_en'     => $envio->fecha_envio,
            'subido_por'      => $envio->enviado_por ?? $envio->establecimiento->usuarios()->first()?->id,
        ]);

        if (! $conError) {
            return;
        }

        // Códigos reales del catálogo: uno bloqueante y una advertencia, para
        // que la tabla de hallazgos del detalle tenga algo que mostrar.
        DB::table('validaciones')->insert([
            [
                'envio_id'         => null,
                'envio_archivo_id' => $archivo->id,
                'etapa'            => 'V5',
                'codigo'           => 'ENV-016',
                'severidad'        => 'bloqueante',
                'regla'            => 'R-V5-01',
                'mensaje'          => 'Falta la hoja obligatoria «PERSONAS».',
                'hoja'             => null,
                'celda'            => null,
                'valor'            => null,
                'created_at'       => now(),
            ],
            [
                'envio_id'         => null,
                'envio_archivo_id' => $archivo->id,
                'etapa'            => 'V9',
                'codigo'           => 'ENV-023',
                'severidad'        => 'advertencia',
                'regla'            => 'R-V9-01',
                'mensaje'          => 'Prestación duplicada en el archivo: 01010101.',
                'hoja'             => 'NOMBRE',
                'celda'            => 'B24',
                'valor'            => '01010101',
                'created_at'       => now(),
            ],
        ]);
    }

    /** Hallazgo que pertenece al envío completo, no a un archivo (V11). */
    private function hallazgoEnvio(Envio $envio, array $datos): void
    {
        DB::table('validaciones')->insert($datos + [
            'envio_id'         => $envio->id,
            'envio_archivo_id' => null,
            'hoja'             => null,
            'celda'            => null,
            'valor'            => null,
            'created_at'       => now(),
        ]);
    }

    /** Historial coherente con el estado final. */
    private function transiciones(Envio $envio, ?User $encargado, ?User $revisor): void
    {
        $pasos = [[null, Envio::RECIBIDO, $encargado, 'Envío creado.']];

        if (in_array($envio->estado, [Envio::VALIDADO, Envio::EN_REVISION, Envio::OBSERVADO, Envio::APROBADO], true)) {
            $pasos[] = [Envio::RECIBIDO, Envio::VALIDADO, null, 'Recalculado por el sistema.'];
        }

        if ($envio->estado === Envio::CON_ERRORES) {
            $pasos[] = [Envio::RECIBIDO, Envio::CON_ERRORES, null, 'La validación dejó errores bloqueantes.'];
        }

        if (in_array($envio->estado, [Envio::EN_REVISION, Envio::OBSERVADO, Envio::APROBADO], true)) {
            $pasos[] = [Envio::VALIDADO, Envio::EN_REVISION, $encargado, 'Envío a revisión.'];
        }

        if ($envio->estado === Envio::OBSERVADO) {
            $pasos[] = [Envio::EN_REVISION, Envio::OBSERVADO, $revisor, 'Falta la hoja PERSONAS en la serie A.'];
        }

        if ($envio->estado === Envio::APROBADO) {
            $pasos[] = [Envio::EN_REVISION, Envio::APROBADO, $revisor, 'Estadística completa.'];
        }

        $momento = $envio->fecha_envio ?? now();

        foreach ($pasos as $indice => [$anterior, $nuevo, $actor, $motivo]) {
            DB::table('envio_transiciones')->insert([
                'envio_id'        => $envio->id,
                'estado_anterior' => $anterior,
                'estado_nuevo'    => $nuevo,
                'actor_id'        => $actor?->id,
                'motivo'          => $motivo,
                'created_at'      => $momento->copy()->addHours($indice * 6),
            ]);
        }
    }

    private function observacion(Envio $envio, ?User $revisor, ?Serie $serie): void
    {
        if ($revisor === null) {
            return;
        }

        $archivo = $serie === null
            ? null
            : $envio->archivosVigentes()->where('serie_id', $serie->id)->first();

        DB::table('observaciones')->insert([
            'envio_id'         => $envio->id,
            'envio_archivo_id' => $archivo?->id,
            'autor_id'         => $revisor->id,
            'texto'            => 'Falta la hoja PERSONAS en la serie ' . ($serie?->codigo ?? 'A') . '. Súbala corregida.',
            'resuelta'         => false,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);
    }

    /** Quien carga en ese establecimiento, para que el historial tenga autor. */
    private function encargadoDe(Establecimiento $establecimiento): ?User
    {
        return $establecimiento->usuarios()->where('activo', true)->first()
            ?? User::role('encargado')->where('activo', true)->first();
    }
}
