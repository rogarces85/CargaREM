<?php

namespace Tests\Feature;

use App\Models\CatalogoSnapshot;
use App\Models\Envio;
use App\Models\EnvioArchivo;
use App\Models\Establecimiento;
use App\Models\Periodo;
use App\Models\Plantilla;
use App\Models\PlantillaHoja;
use App\Models\Serie;
use App\Models\Servicio;
use App\Models\User;
use App\Services\EnvioService;
use App\Validacion\ValidadorArchivo;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * V11 — completitud del envío (T-F2-22).
 */
class ValidadorV11Test extends TestCase
{
    use RefreshDatabase;

    private Envio $envio;
    private Establecimiento $establecimiento;
    private User $usuario;
    private CatalogoSnapshot $snapshot;
    private Serie $serieA;
    private Serie $serieBS;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Storage::fake('local');

        $servicio = Servicio::create(['codigo' => '23', 'nombre' => 'Osorno']);
        $this->establecimiento = Establecimiento::create([
            'codigo' => '123100', 'nombre' => 'Hospital Base', 'servicio_id' => $servicio->id,
        ]);
        Periodo::create(['anio' => 2026, 'mes' => 9, 'estado' => Periodo::ABIERTO]);
        $this->snapshot = CatalogoSnapshot::create(['origen' => 'prueba', 'activo' => true]);

        $this->usuario = User::factory()->create(['activo' => true]);
        $this->usuario->assignRole('encargado');
        $this->usuario->establecimientos()->attach($this->establecimiento->id);
        $this->actingAs($this->usuario);

        $this->serieA = Serie::create(['codigo' => 'A', 'nombre' => 'SERIE A']);
        $this->serieBS = Serie::create(['codigo' => 'BS', 'nombre' => 'SERIE BS']);

        // La matriz exige ambas series.
        $this->establecimiento->series()->attach($this->serieA->id, ['obligatoria' => true]);
        $this->establecimiento->series()->attach($this->serieBS->id, ['obligatoria' => true]);

        PlantillaHoja::create(['anio' => 2026, 'serie_interna' => 'SERIE A1', 'hoja' => 'NOMBRE', 'obligatoria' => true]);

        $this->envio = app(EnvioService::class)->crear($this->establecimiento, 2026, 9, $this->usuario);
    }

    private function archivoDe(Serie $serie, string $codigoSerie, ?string $estado = null): EnvioArchivo
    {
        $libro = new Spreadsheet();
        $nombre = $libro->getActiveSheet();
        $nombre->setTitle('NOMBRE');
        foreach (['C3' => '123100', 'C6' => '09', 'B7' => '2026', 'B17' => "SERIE {$codigoSerie}", 'A9' => 'Version 1.2'] as $c => $v) {
            $nombre->setCellValue($c, $v);
        }

        $tmp = tempnam(sys_get_temp_dir(), 'rem').'.xlsx';
        (new Xlsx($libro))->save($tmp);
        $libro->disconnectWorksheets();

        $contenido = file_get_contents($tmp);
        $ruta = "envios/2026/09/123100/v1/{$codigoSerie}.xlsx";
        Storage::disk('local')->put($ruta, $contenido);

        return $this->envio->archivos()->create([
            'serie_id'        => $serie->id,
            'version'         => 1,
            'es_vigente'      => true,
            'nombre_original' => "123100{$codigoSerie}09.xlsx",
            'ruta'            => $ruta,
            'disco'           => 'local',
            'sha256'          => hash('sha256', $contenido),
            'tamano_bytes'    => strlen($contenido),
            'mime'            => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'estado'          => $estado ?? EnvioArchivo::SUBIDO,
            'snapshot_id'     => $this->snapshot->id,
            'subido_por'      => $this->usuario->id,
        ]);
    }

    /** @return list<string> códigos de los hallazgos de V11 del envío */
    private function hallazgosV11(EnvioArchivo $archivo): array
    {
        app(ValidadorArchivo::class)->validar($archivo);

        return DB::table('validaciones')
            ->where('envio_id', $this->envio->id)
            ->where('etapa', 'V11')
            ->orderBy('codigo')
            ->pluck('codigo')
            ->all();
    }

    public function test_un_envio_incompleto_bloquea_con_env_025(): void
    {
        $archivo = $this->archivoDe($this->serieA, 'A');

        $this->assertSame(['ENV-025'], $this->hallazgosV11($archivo));
    }

    public function test_un_envio_con_todas_las_series_no_reporta_faltantes(): void
    {
        $this->archivoDe($this->serieBS, 'BS');
        $archivoA = $this->archivoDe($this->serieA, 'A');

        $this->assertSame([], $this->hallazgosV11($archivoA));
    }

    public function test_una_serie_con_archivo_con_errores_da_env_030(): void
    {
        $this->archivoDe($this->serieBS, 'BS', EnvioArchivo::CON_ERRORES);
        $archivoA = $this->archivoDe($this->serieA, 'A');

        $this->assertSame(['ENV-030'], $this->hallazgosV11($archivoA));
    }

    public function test_una_serie_no_exigida_avisa_con_env_026(): void
    {
        $serieD = Serie::create(['codigo' => 'D', 'nombre' => 'SERIE D']);
        $this->archivoDe($serieD, 'D');
        $this->archivoDe($this->serieBS, 'BS');
        $archivoA = $this->archivoDe($this->serieA, 'A');

        $this->assertSame(['ENV-026'], $this->hallazgosV11($archivoA));
    }

    public function test_los_hallazgos_de_v11_se_guardan_sin_archivo(): void
    {
        $archivo = $this->archivoDe($this->serieA, 'A');
        $this->hallazgosV11($archivo);

        $this->assertDatabaseHas('validaciones', [
            'envio_id'         => $this->envio->id,
            'etapa'            => 'V11',
            'envio_archivo_id' => null,
        ]);
    }

    public function test_revalidar_no_duplica_los_hallazgos_de_v11(): void
    {
        $archivo = $this->archivoDe($this->serieA, 'A');

        $this->hallazgosV11($archivo);
        $primera = DB::table('validaciones')->where('etapa', 'V11')->count();

        app(EnvioService::class)->revalidar($this->envio);

        $this->assertSame($primera, DB::table('validaciones')->where('etapa', 'V11')->count());
    }
}
