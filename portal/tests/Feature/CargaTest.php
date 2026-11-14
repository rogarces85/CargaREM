<?php

namespace Tests\Feature;

use App\Exceptions\EnvioInvalidoException;
use App\Models\Envio;
use App\Models\EnvioArchivo;
use App\Models\Establecimiento;
use App\Models\Periodo;
use App\Models\Serie;
use App\Models\User;
use App\Services\EnvioService;
use App\Services\EstadoEnvioService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Aprobación completa del envío (MOD-05/06).
 *
 * En v2.0 `aprobado` es el estado final: no hay reapertura, anulación ni
 * confirmación de carga.
 */
class CargaTest extends TestCase
{
    use RefreshDatabase;

    private Envio $envio;
    private User $admin;
    private User $revisor;
    private User $encargado;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Storage::fake('local');

        $establecimiento = Establecimiento::create([
            'codigo' => '123100', 'nombre' => 'Hospital Base',
        ]);
        Periodo::create(['anio' => 2026, 'mes' => 9, 'estado' => Periodo::ABIERTO]);

        $this->encargado = User::factory()->create(['activo' => true]);
        $this->encargado->assignRole('encargado');
        $this->encargado->establecimientos()->attach($establecimiento->id);

        $this->revisor = User::factory()->create(['activo' => true]);
        $this->revisor->assignRole('revisor');

        $this->admin = User::factory()->create(['activo' => true]);
        $this->admin->assignRole('admin');

        $serie = Serie::create(['codigo' => 'A', 'nombre' => 'SERIE A']);
        $establecimiento->series()->attach($serie->id, ['obligatoria' => true]);

        $this->actingAs($this->encargado);
        $this->envio = app(EnvioService::class)->crear($establecimiento, 2026, 9, $this->encargado);

        $this->envio->archivos()->create([
            'serie_id' => $serie->id, 'version' => 1, 'es_vigente' => true,
            'nombre_original' => '123100A09.xlsx', 'ruta' => 'envios/x.xlsx', 'disco' => 'local',
            'sha256' => str_repeat('d', 64), 'tamano_bytes' => 100, 'mime' => 'application/vnd.ms-excel',
            'estado' => EnvioArchivo::VALIDO,
            'subido_por' => $this->encargado->id,
        ]);
    }

    private function estados(): EstadoEnvioService
    {
        return app(EstadoEnvioService::class);
    }

    private function aprobado(): Envio
    {
        $this->envio->update(['estado' => Envio::EN_REVISION]);
        $this->estados()->aprobar($this->envio->fresh(), $this->revisor);

        return $this->envio->fresh();
    }

    public function test_aprobar_marca_los_archivos_vigentes_como_aprobados(): void
    {
        $envio = $this->aprobado();

        $this->assertSame(Envio::APROBADO, $envio->estado);

        $this->assertDatabaseHas('envio_archivos', [
            'envio_id'   => $envio->id,
            'es_vigente' => true,
            'estado'     => EnvioArchivo::APROBADO,
        ]);
    }

    public function test_un_envio_aprobado_no_tiene_mas_transiciones(): void
    {
        $envio = $this->aprobado();

        $this->expectException(EnvioInvalidoException::class);

        $this->estados()->transicionar($envio, Envio::EN_REVISION, $this->admin, 'Reapertura');
    }

    public function test_no_existen_las_rutas_de_reabrir_anular_ni_cargar(): void
    {
        $envio = $this->aprobado();

        $this->actingAs($this->admin)->post("/revision/{$envio->id}/reaperturar")->assertNotFound();
        $this->actingAs($this->admin)->post("/revision/{$envio->id}/anular")->assertNotFound();
        $this->actingAs($this->admin)->post("/revision/{$envio->id}/cargar")->assertNotFound();
    }
}
