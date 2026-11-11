<?php

namespace Tests\Feature;

use App\Exceptions\ArchivoRechazadoException;
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
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Alta de envíos (T-F2-03), almacenamiento privado (T-F2-05) y versionado
 * (T-F2-06).
 */
class EnvioServiceTest extends TestCase
{
    use RefreshDatabase;

    private Establecimiento $establecimiento;
    private User $encargado;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Storage::fake('local');

        $servicio = Servicio::create(['codigo' => '23', 'nombre' => 'Osorno']);

        $this->establecimiento = Establecimiento::create([
            'codigo' => '123100', 'nombre' => 'Hospital Base', 'servicio_id' => $servicio->id,
        ]);

        $this->encargado = User::factory()->create(['activo' => true]);
        $this->encargado->assignRole('encargado');
        $this->encargado->establecimientos()->attach($this->establecimiento->id);
        $this->actingAs($this->encargado);
    }

    private function servicio(): EnvioService
    {
        return app(EnvioService::class);
    }

    private function periodo(string $estado = Periodo::ABIERTO, int $mes = 9): Periodo
    {
        return Periodo::create(['anio' => 2026, 'mes' => $mes, 'estado' => $estado]);
    }

    /** Excel falso con la firma real de un .xlsx (ZIP). */
    private function excel(string $nombre = '123100A09.xlsx', int $kb = 4): UploadedFile
    {
        $contenido = "PK\x03\x04".str_repeat('A', max(1, $kb * 1024));

        return UploadedFile::fake()->createWithContent($nombre, $contenido);
    }

    private function archivo(string $nombre, string $contenido): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($nombre, $contenido);
    }

    public function test_crea_el_envio_y_registra_la_primera_transicion(): void
    {
        $this->periodo();

        $envio = $this->servicio()->crear($this->establecimiento, 2026, 9, $this->encargado);

        $this->assertSame(Envio::RECIBIDO, $envio->estado);
        $this->assertSame($this->encargado->id, $envio->enviado_por);
        $this->assertDatabaseHas('envio_transiciones', [
            'envio_id'        => $envio->id,
            'estado_anterior' => null,
            'estado_nuevo'    => 'recibido',
        ]);
        $this->assertDatabaseHas('auditoria', ['evento' => 'envio.creado']);
    }

    public function test_rechaza_un_segundo_envio_del_mismo_periodo(): void
    {
        $this->periodo();
        $this->servicio()->crear($this->establecimiento, 2026, 9, $this->encargado);

        $this->expectException(EnvioInvalidoException::class);

        $this->servicio()->crear($this->establecimiento, 2026, 9, $this->encargado);
    }

    public function test_rechaza_un_periodo_inexistente(): void
    {
        $this->expectException(EnvioInvalidoException::class);

        $this->servicio()->crear($this->establecimiento, 2026, 11, $this->encargado);
    }

    public function test_rechaza_un_periodo_cerrado(): void
    {
        $this->periodo(Periodo::CERRADO);

        try {
            $this->servicio()->crear($this->establecimiento, 2026, 9, $this->encargado);
            $this->fail('Debería rechazar el período cerrado');
        } catch (EnvioInvalidoException $e) {
            $this->assertSame('APP-ENV-002', $e->codigo);
        }
    }

    public function test_sube_un_archivo_al_almacen_privado_con_nombre_generado(): void
    {
        $this->periodo();
        $envio = $this->servicio()->crear($this->establecimiento, 2026, 9, $this->encargado);
        $serie = Serie::create(['codigo' => 'A', 'nombre' => 'SERIE A']);
        CatalogoSnapshot::create(['origen' => 'prueba', 'activo' => true]);

        $registro = $this->servicio()->subirVersion($envio, $serie, $this->excel(), $this->encargado);

        $this->assertSame('123100A09.xlsx', $registro->nombre_original, 'El original se conserva como dato');
        $this->assertStringNotContainsString('123100A09', $registro->ruta, 'La ruta no usa el nombre del usuario');
        $this->assertStringContainsString('envios/2026/09/123100/v1/', $registro->ruta);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $registro->sha256);
        $this->assertTrue($registro->es_vigente);
        Storage::disk('local')->assertExists($registro->ruta);
    }

    public function test_el_reenvio_crea_version_nueva_y_deja_la_anterior_en_historico(): void
    {
        $this->periodo();
        $envio = $this->servicio()->crear($this->establecimiento, 2026, 9, $this->encargado);
        $serie = Serie::create(['codigo' => 'A', 'nombre' => 'SERIE A']);
        CatalogoSnapshot::create(['origen' => 'prueba', 'activo' => true]);

        $v1 = $this->servicio()->subirVersion($envio, $serie, $this->excel(), $this->encargado);
        $v2 = $this->servicio()->subirVersion(
            $envio, $serie, $this->archivo('123100A09.xlsx', "PK\x03\x04".str_repeat('B', 2048)), $this->encargado
        );

        $this->assertSame(1, $v1->version);
        $this->assertSame(2, $v2->version);
        $this->assertFalse($v1->fresh()->es_vigente);
        $this->assertTrue($v2->fresh()->es_vigente);
        $this->assertSame(2, $envio->archivos()->count(), 'La versión anterior no se borra');
    }

    public function test_rechaza_un_archivo_demasiado_grande(): void
    {
        $this->periodo();
        $envio = $this->servicio()->crear($this->establecimiento, 2026, 9, $this->encargado);
        $serie = Serie::create(['codigo' => 'A', 'nombre' => 'SERIE A']);
        CatalogoSnapshot::create(['origen' => 'prueba', 'activo' => true]);

        try {
            $this->servicio()->subirVersion($envio, $serie, $this->excel('123100A09.xlsx', 2100), $this->encargado);
            $this->fail('Debería rechazar por tamaño');
        } catch (ArchivoRechazadoException $e) {
            $this->assertSame('ENV-001', $e->codigo);
        }
    }

    public function test_rechaza_una_extension_no_permitida(): void
    {
        $this->periodo();
        $envio = $this->servicio()->crear($this->establecimiento, 2026, 9, $this->encargado);
        $serie = Serie::create(['codigo' => 'A', 'nombre' => 'SERIE A']);
        CatalogoSnapshot::create(['origen' => 'prueba', 'activo' => true]);

        try {
            $this->servicio()->subirVersion($envio, $serie, $this->archivo('malware.exe', "PK\x03\x04xxxx"), $this->encargado);
            $this->fail('Debería rechazar la extensión');
        } catch (ArchivoRechazadoException $e) {
            $this->assertSame('ENV-002', $e->codigo);
        }
    }

    public function test_rechaza_un_archivo_que_finge_ser_excel(): void
    {
        $this->periodo();
        $envio = $this->servicio()->crear($this->establecimiento, 2026, 9, $this->encargado);
        $serie = Serie::create(['codigo' => 'A', 'nombre' => 'SERIE A']);
        CatalogoSnapshot::create(['origen' => 'prueba', 'activo' => true]);

        try {
            $this->servicio()->subirVersion($envio, $serie, $this->archivo('123100A09.xlsx', '%PDF-1.7 falso'), $this->encargado);
            $this->fail('Debería rechazar la firma');
        } catch (ArchivoRechazadoException $e) {
            $this->assertSame('ENV-003', $e->codigo);
        }
    }

    public function test_rechaza_el_mismo_archivo_dos_veces(): void
    {
        $this->periodo();
        $envio = $this->servicio()->crear($this->establecimiento, 2026, 9, $this->encargado);
        $serie = Serie::create(['codigo' => 'A', 'nombre' => 'SERIE A']);
        CatalogoSnapshot::create(['origen' => 'prueba', 'activo' => true]);

        $this->servicio()->subirVersion($envio, $serie, $this->excel(), $this->encargado);

        try {
            $this->servicio()->subirVersion($envio, $serie, $this->excel(), $this->encargado);
            $this->fail('Debería rechazar el duplicado');
        } catch (ArchivoRechazadoException $e) {
            $this->assertSame('ENV-029', $e->codigo);
        }
    }

    public function test_sin_catalogo_activo_no_se_puede_subir(): void
    {
        $this->periodo();
        $envio = $this->servicio()->crear($this->establecimiento, 2026, 9, $this->encargado);
        $serie = Serie::create(['codigo' => 'A', 'nombre' => 'SERIE A']);

        try {
            $this->servicio()->subirVersion($envio, $serie, $this->excel(), $this->encargado);
            $this->fail('Debería exigir catálogo activo');
        } catch (EnvioInvalidoException $e) {
            $this->assertSame('APP-ENV-004', $e->codigo);
        }
    }

    public function test_un_reenvio_devuelve_el_envio_a_recibido(): void
    {
        $this->periodo();
        $envio = $this->servicio()->crear($this->establecimiento, 2026, 9, $this->encargado);
        $serie = Serie::create(['codigo' => 'A', 'nombre' => 'SERIE A']);
        CatalogoSnapshot::create(['origen' => 'prueba', 'activo' => true]);

        $envio->update(['estado' => Envio::OBSERVADO]);

        $this->servicio()->subirVersion($envio, $serie, $this->excel(), $this->encargado);

        $this->assertSame(Envio::RECIBIDO, $envio->fresh()->estado);
    }
}
