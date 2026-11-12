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
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Confirmar carga (T-F3-07) y reapertura/anulación (T-F3-08).
 */
class CargaYReaperturaTest extends TestCase
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

        $serie = Serie::create(['codigo' => 'A', 'nombre' => 'SERIE A']);
        $establecimiento->series()->attach($serie->id, ['obligatoria' => true]);

        $this->actingAs($this->encargado);
        $this->envio = app(EnvioService::class)->crear($establecimiento, 2026, 9, $this->encargado);

        $this->envio->archivos()->create([
            'serie_id' => $serie->id, 'version' => 1, 'es_vigente' => true,
            'nombre_original' => '123100A09.xlsx', 'ruta' => 'envios/x.xlsx', 'disco' => 'local',
            'sha256' => str_repeat('d', 64), 'tamano_bytes' => 100, 'mime' => 'application/vnd.ms-excel',
            'estado' => EnvioArchivo::VALIDO, 'snapshot_id' => $snapshot->id,
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

    public function test_confirma_la_carga_con_referencia(): void
    {
        $envio = $this->aprobado();

        $this->actingAs($this->revisor)
            ->post("/revision/{$envio->id}/cargar", ['referencia' => 'ACTA-2026-114'])
            ->assertRedirect(route('revision.index'));

        $envio = $envio->fresh();

        $this->assertSame(Envio::CARGADO, $envio->estado);
        $this->assertNotNull($envio->fecha_carga);
        $this->assertDatabaseHas('envio_transiciones', [
            'envio_id'     => $envio->id,
            'estado_nuevo' => 'cargado',
            'motivo'       => 'Carga confirmada. Referencia: ACTA-2026-114',
        ]);
    }

    public function test_confirma_la_carga_sin_referencia(): void
    {
        $envio = $this->aprobado();

        $this->actingAs($this->revisor)
            ->post("/revision/{$envio->id}/cargar")
            ->assertRedirect(route('revision.index'));

        $this->assertSame(Envio::CARGADO, $envio->fresh()->estado);
    }

    public function test_no_confirmar_la_carga_sin_aprobar(): void
    {
        $this->envio->update(['estado' => Envio::EN_REVISION]);

        $this->actingAs($this->revisor)
            ->post("/revision/{$this->envio->id}/cargar")
            ->assertForbidden();

        $this->assertSame(Envio::EN_REVISION, $this->envio->fresh()->estado);
    }

    public function test_el_admin_reabre_un_envio_cargado(): void
    {
        $envio = $this->aprobado();
        $this->estados()->confirmarCarga($envio, $this->revisor, 'ACTA-1');

        $this->actingAs($this->admin)
            ->post("/revision/{$envio->id}/reaperturar", ['motivo' => 'Se cargó el acta equivocada'])
            ->assertRedirect(route('revision.index'));

        $this->assertSame(Envio::EN_REVISION, $envio->fresh()->estado);
        $this->assertDatabaseHas('envio_transiciones', [
            'envio_id'     => $envio->id,
            'estado_nuevo' => 'en_revision',
            'motivo'       => 'Reapertura: Se cargó el acta equivocada',
        ]);
    }

    public function test_reabrir_exige_motivo(): void
    {
        $envio = $this->aprobado();

        $this->actingAs($this->admin)
            ->post("/revision/{$envio->id}/reaperturar", ['motivo' => ''])
            ->assertSessionHasErrors('motivo');

        $this->assertSame(Envio::APROBADO, $envio->fresh()->estado);
    }

    public function test_un_revisor_no_reabre(): void
    {
        $envio = $this->aprobado();

        $this->actingAs($this->revisor)
            ->post("/revision/{$envio->id}/reaperturar", ['motivo' => 'quiero'])
            ->assertForbidden();
    }

    public function test_el_admin_anula_un_envio(): void
    {
        $this->envio->update(['estado' => Envio::RECIBIDO]);

        $this->actingAs($this->admin)
            ->post("/revision/{$this->envio->id}/anular", ['motivo' => 'Período cargado por error'])
            ->assertRedirect(route('revision.index'));

        $this->assertSame(Envio::ANULADO, $this->envio->fresh()->estado);
    }

    public function test_anular_exige_motivo(): void
    {
        $this->expectException(EnvioInvalidoException::class);

        $this->estados()->anular($this->envio->fresh(), $this->admin, '   ');
    }

    public function test_un_envio_cargado_no_se_anula_directo(): void
    {
        $envio = $this->aprobado();
        $this->estados()->confirmarCarga($envio, $this->revisor);

        $this->expectException(EnvioInvalidoException::class);

        $this->estados()->anular($envio->fresh(), $this->admin, 'motivo');
    }

    public function test_reaperturar_un_envio_en_revision_no_aplica(): void
    {
        $this->envio->update(['estado' => Envio::EN_REVISION]);

        $this->expectException(EnvioInvalidoException::class);

        $this->estados()->reaperturar($this->envio->fresh(), $this->admin, 'motivo');
    }
}
