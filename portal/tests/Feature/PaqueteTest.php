<?php

namespace Tests\Feature;

use App\Exceptions\EnvioInvalidoException;
use App\Models\CatalogoSnapshot;
use App\Models\Envio;
use App\Models\EnvioArchivo;
use App\Models\Establecimiento;
use App\Models\Periodo;
use App\Models\Serie;
use App\Models\Servicio;
use App\Models\User;
use App\Services\EnvioService;
use App\Services\EstadoEnvioService;
use App\Services\PaqueteService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Paquete de entrega: ZIP, manifiesto, hashes y firma (T-F5-01 a T-F5-05).
 */
class PaqueteTest extends TestCase
{
    use RefreshDatabase;

    private Envio $envio;
    private User $revisor;
    private User $encargado;
    private Serie $serieA;
    private User $admin;

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

        $this->admin = User::factory()->create(['activo' => true, 'servicio_id' => $servicio->id]);
        $this->admin->assignRole('admin');

        $this->serieA = Serie::create(['codigo' => 'A', 'nombre' => 'SERIE A', 'orden' => 1]);
        $establecimiento->series()->attach($this->serieA->id, ['obligatoria' => true]);

        $this->actingAs($this->encargado);
        $this->envio = app(EnvioService::class)->crear($establecimiento, 2026, 9, $this->encargado);

        $contenido = 'PK'."\x03\x04".str_repeat('A', 200);
        $ruta = 'envios/2026/09/123100/v1/archivo.xlsx';
        Storage::disk('local')->put($ruta, $contenido);

        $this->envio->archivos()->create([
            'serie_id' => $this->serieA->id, 'version' => 1, 'es_vigente' => true,
            'nombre_original' => '123100A09.xlsx', 'ruta' => $ruta, 'disco' => 'local',
            'sha256' => hash('sha256', $contenido), 'tamano_bytes' => strlen($contenido),
            'mime' => 'application/vnd.ms-excel', 'estado' => EnvioArchivo::VALIDO,
            'snapshot_id' => $snapshot->id, 'subido_por' => $this->encargado->id,
        ]);
    }

    private function aprobado(): Envio
    {
        $this->envio->update(['estado' => Envio::EN_REVISION]);
        app(EstadoEnvioService::class)->aprobar($this->envio->fresh(), $this->revisor);

        return $this->envio->fresh();
    }

    public function test_genera_el_zip_con_archivos_reporte_y_manifiesto(): void
    {
        $envio = $this->aprobado();

        $ruta = app(PaqueteService::class)->generar($envio);

        $this->assertFileExists($ruta);

        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($ruta) === true);

        $nombres = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $nombres[] = $zip->getNameIndex($i);
        }

        $zip->close();
        @unlink($ruta);

        $this->assertContains('manifiesto.json', $nombres);
        $this->assertContains('archivos/A.xlsx', $nombres);
        $this->assertContains('validacion/A.xlsx', $nombres);
    }

    public function test_el_manifiesto_tiene_la_estructura_declarada(): void
    {
        $envio = $this->aprobado();

        $ruta = app(PaqueteService::class)->generar($envio);

        $zip = new \ZipArchive();
        $zip->open($ruta);
        $manifiesto = json_decode($zip->getFromName('manifiesto.json'), true);
        $zip->close();
        @unlink($ruta);

        $this->assertSame('1.0', $manifiesto['version']);
        $this->assertSame($envio->id, $manifiesto['envio_id']);
        $this->assertSame('123100', $manifiesto['establecimiento']['codigo']);
        $this->assertSame(9, $manifiesto['periodo']['mes']);
        $this->assertSame($this->revisor->name, $manifiesto['aprobado_por']);
        $this->assertNotEmpty($manifiesto['fecha_aprobacion']);
        $this->assertCount(1, $manifiesto['archivos']);
        $this->assertSame('A', $manifiesto['archivos'][0]['serie']);
        $this->assertSame(64, strlen($manifiesto['archivos'][0]['sha256']));
        $this->assertNotEmpty($manifiesto['hash_paquete']);
    }

    public function test_el_hash_del_manifiesto_verifica(): void
    {
        $envio = $this->aprobado();
        $servicio = app(PaqueteService::class);

        $ruta = $servicio->generar($envio);

        $zip = new \ZipArchive();
        $zip->open($ruta);
        $manifiesto = json_decode($zip->getFromName('manifiesto.json'), true);
        $zip->close();
        @unlink($ruta);

        $this->assertTrue($servicio->verificar($manifiesto));
    }

    public function test_alterar_un_archivo_rompe_la_verificacion(): void
    {
        $envio = $this->aprobado();
        $servicio = app(PaqueteService::class);

        $ruta = $servicio->generar($envio);

        $zip = new \ZipArchive();
        $zip->open($ruta);
        $manifiesto = json_decode($zip->getFromName('manifiesto.json'), true);
        $zip->close();
        @unlink($ruta);

        // Cambiar el hash de un archivo simula un contenido distinto.
        $manifiesto['archivos'][0]['sha256'] = str_repeat('0', 64);

        $this->assertFalse($servicio->verificar($manifiesto));
    }

    public function test_con_clave_configurada_el_manifiesto_se_firma(): void
    {
        config(['rem.paquete.hmac_key' => 'clave-de-prueba']);
        $envio = $this->aprobado();
        $servicio = app(PaqueteService::class);

        $ruta = $servicio->generar($envio);

        $zip = new \ZipArchive();
        $zip->open($ruta);
        $manifiesto = json_decode($zip->getFromName('manifiesto.json'), true);
        $zip->close();
        @unlink($ruta);

        $this->assertNotEmpty($manifiesto['hmac']);
        $this->assertSame(
            hash_hmac('sha256', $manifiesto['hash_paquete'], 'clave-de-prueba'),
            $manifiesto['hmac']
        );
        $this->assertTrue($servicio->verificar($manifiesto));
    }

    public function test_sin_clave_el_manifiesto_queda_sin_firma(): void
    {
        config(['rem.paquete.hmac_key' => null]);
        $envio = $this->aprobado();
        $servicio = app(PaqueteService::class);

        $ruta = $servicio->generar($envio);

        $zip = new \ZipArchive();
        $zip->open($ruta);
        $manifiesto = json_decode($zip->getFromName('manifiesto.json'), true);
        $zip->close();
        @unlink($ruta);

        $this->assertNull($manifiesto['hmac'], 'Sin clave no se finge una firma');
        $this->assertTrue($servicio->verificar($manifiesto));
    }

    public function test_no_se_genera_paquete_de_un_envio_sin_aprobar(): void
    {
        $this->expectException(EnvioInvalidoException::class);

        app(PaqueteService::class)->generar($this->envio);
    }

    public function test_el_revisor_descarga_el_paquete(): void
    {
        $envio = $this->aprobado();

        $respuesta = $this->actingAs($this->revisor)->get("/envios/{$envio->id}/paquete");

        $respuesta->assertOk();
        $this->assertStringContainsString('application/zip', $respuesta->headers->get('content-type'));
        $this->assertStringContainsString("paquete-123100-09-2026.zip", $respuesta->headers->get('content-disposition'));
    }

    public function test_un_envio_sin_aprobar_no_se_descarga(): void
    {
        $this->actingAs($this->revisor)
            ->get("/envios/{$this->envio->id}/paquete")
            ->assertForbidden();
    }

    public function test_confirmar_la_carga_desde_el_detalle_guarda_la_referencia(): void
    {
        $envio = $this->aprobado();

        $this->actingAs($this->revisor)
            ->post("/envios/{$envio->id}/transicion", [
                'accion'     => 'cargar',
                'referencia' => 'ACTA-2026-114',
            ])
            ->assertRedirect();

        $this->assertSame(Envio::CARGADO, $envio->fresh()->estado);
        $this->assertDatabaseHas('envio_transiciones', [
            'envio_id'     => $envio->id,
            'estado_nuevo' => 'cargado',
            'motivo'       => 'Carga confirmada. Referencia: ACTA-2026-114',
        ]);
    }
}
