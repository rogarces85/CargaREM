<?php

namespace Tests\Feature;

use App\Exceptions\EnvioInvalidoException;
use App\Models\CatalogoSnapshot;
use App\Models\Envio;
use App\Models\EnvioArchivo;
use App\Models\Establecimiento;
use App\Models\Observacion;
use App\Models\Periodo;
use App\Models\Serie;
use App\Models\Servicio;
use App\Models\User;
use App\Services\EnvioService;
use App\Services\ObservacionService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * Observaciones y su resolución al reenviar (T-F3-01, T-F3-02).
 */
class ObservacionTest extends TestCase
{
    use RefreshDatabase;

    private Envio $envio;
    private User $encargado;
    private User $revisor;
    private Serie $serieA;
    private Serie $serieBS;
    private EnvioArchivo $archivoA;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Storage::fake('local');

        $servicio = Servicio::create(['codigo' => '23', 'nombre' => 'Osorno']);
        $establecimiento = Establecimiento::create([
            'codigo' => '123100', 'nombre' => 'Hospital Base', 'servicio_id' => $servicio->id,
        ]);
        Periodo::create(['anio' => 2026, 'mes' => 9, 'estado' => Periodo::ABIERTO]);
        $snapshot = CatalogoSnapshot::create(['origen' => 'prueba', 'activo' => true]);

        $this->encargado = User::factory()->create(['activo' => true]);
        $this->encargado->assignRole('encargado');
        $this->encargado->establecimientos()->attach($establecimiento->id);

        $this->revisor = User::factory()->create(['activo' => true, 'servicio_id' => $servicio->id]);
        $this->revisor->assignRole('revisor');

        $this->serieA = Serie::create(['codigo' => 'A', 'nombre' => 'SERIE A']);
        $this->serieBS = Serie::create(['codigo' => 'BS', 'nombre' => 'SERIE BS']);

        $this->actingAs($this->encargado);
        $this->envio = app(EnvioService::class)->crear($establecimiento, 2026, 9, $this->encargado);

        $this->archivoA = $this->envio->archivos()->create([
            'serie_id' => $this->serieA->id, 'version' => 1, 'es_vigente' => true,
            'nombre_original' => '123100A09.xlsx', 'ruta' => 'envios/x.xlsx', 'disco' => 'local',
            'sha256' => str_repeat('a', 64), 'tamano_bytes' => 100, 'mime' => 'application/vnd.ms-excel',
            'estado' => EnvioArchivo::VALIDO, 'snapshot_id' => $snapshot->id,
            'subido_por' => $this->encargado->id,
        ]);
    }

    private function observaciones(): ObservacionService
    {
        return app(ObservacionService::class);
    }

    public function test_registra_una_observacion_del_envio(): void
    {
        $observacion = $this->observaciones()->registrar($this->envio, $this->revisor, 'Falta el reporte de agosto');

        $this->assertFalse($observacion->resuelta);
        $this->assertNull($observacion->envio_archivo_id);
        $this->assertSame($this->revisor->id, $observacion->autor_id);
        $this->assertDatabaseHas('auditoria', ['evento' => 'envio.observado']);
    }

    public function test_registra_una_observacion_de_un_archivo(): void
    {
        $observacion = $this->observaciones()->registrar(
            $this->envio, $this->revisor, 'La columna 12 viene vacía', $this->archivoA
        );

        $this->assertSame($this->archivoA->id, $observacion->envio_archivo_id);
    }

    public function test_no_acepta_texto_vacio(): void
    {
        try {
            $this->observaciones()->registrar($this->envio, $this->revisor, '   ');
            $this->fail('Debería rechazar el texto vacío');
        } catch (EnvioInvalidoException $e) {
            $this->assertSame('APP-OBS-001', $e->codigo);
        }
    }

    public function test_no_acepta_un_archivo_de_otro_envio(): void
    {
        Periodo::create(['anio' => 2026, 'mes' => 10, 'estado' => Periodo::ABIERTO]);
        $otro = app(EnvioService::class)->crear(
            $this->envio->establecimiento, 2026, 10, $this->encargado
        );

        try {
            $this->observaciones()->registrar($otro, $this->revisor, 'texto', $this->archivoA);
            $this->fail('Debería rechazar el archivo ajeno');
        } catch (EnvioInvalidoException $e) {
            $this->assertSame('APP-OBS-003', $e->codigo);
        }
    }

    public function test_reenviar_la_serie_resuelve_sus_observaciones(): void
    {
        $observacion = $this->observaciones()->registrar(
            $this->envio, $this->revisor, 'Falta la columna 12', $this->archivoA
        );

        app(EnvioService::class)->subirVersion(
            $this->envio, $this->serieA, $this->excel('A', 'corregido'), $this->encargado
        );

        $this->assertTrue($observacion->fresh()->resuelta);
        $this->assertNotNull($observacion->fresh()->resuelta_en);
    }

    public function test_reenviar_otra_serie_no_resuelve_la_observacion(): void
    {
        $observacion = $this->observaciones()->registrar(
            $this->envio, $this->revisor, 'Falta la columna 12', $this->archivoA
        );

        app(EnvioService::class)->subirVersion(
            $this->envio, $this->serieBS, $this->excel('BS'), $this->encargado
        );

        $this->assertFalse($observacion->fresh()->resuelta);
    }

    public function test_una_observacion_general_no_se_resuelve_sola(): void
    {
        $general = $this->observaciones()->registrar($this->envio, $this->revisor, 'Falta el reporte de agosto');

        app(EnvioService::class)->subirVersion(
            $this->envio, $this->serieA, $this->excel('A'), $this->encargado
        );

        $this->assertFalse($general->fresh()->resuelta, 'Solo se resuelven las de la serie reenviada');
    }

    public function test_cuenta_las_observaciones_abiertas(): void
    {
        $this->observaciones()->registrar($this->envio, $this->revisor, 'Uno');
        $this->observaciones()->registrar($this->envio, $this->revisor, 'Dos', $this->archivoA);

        $this->assertSame(2, $this->observaciones()->abiertas($this->envio));
    }

    private function excel(string $serie, string $marca = 'A'): UploadedFile
    {
        $libro = new Spreadsheet();
        $hoja = $libro->getActiveSheet();
        $hoja->setTitle('NOMBRE');
        foreach (['C3' => '123100', 'C6' => '09', 'B7' => '2026', 'B17' => 'SERIE '.$serie, 'A9' => 'Version 1.2'] as $c => $v) {
            $hoja->setCellValue($c, $v);
        }
        $hoja->setCellValue('Z99', $marca);

        $tmp = tempnam(sys_get_temp_dir(), 'obs').'.xlsx';
        (new Xlsx($libro))->save($tmp);
        $libro->disconnectWorksheets();

        return UploadedFile::fake()->createWithContent("123100{$serie}09.xlsx", file_get_contents($tmp));
    }
}
