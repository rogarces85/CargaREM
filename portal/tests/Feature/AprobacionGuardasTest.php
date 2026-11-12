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
use App\Services\ObservacionService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Guardas de la aprobación (T-F3-06).
 *
 * Aprobar no es solo tener el permiso: el envío tiene que estar completo, sin
 * bloqueantes y sin observaciones abiertas.
 */
class AprobacionGuardasTest extends TestCase
{
    use RefreshDatabase;

    private Envio $envio;
    private User $revisor;
    private User $encargado;
    private Serie $serieA;
    private Serie $serieBS;
    private CatalogoSnapshot $snapshot;

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
        $this->snapshot = CatalogoSnapshot::create(['origen' => 'prueba', 'activo' => true]);

        $this->encargado = User::factory()->create(['activo' => true]);
        $this->encargado->assignRole('encargado');
        $this->encargado->establecimientos()->attach($establecimiento->id);

        $this->revisor = User::factory()->create(['activo' => true, 'servicio_id' => $servicio->id]);
        $this->revisor->assignRole('revisor');

        $this->serieA = Serie::create(['codigo' => 'A', 'nombre' => 'SERIE A']);
        $this->serieBS = Serie::create(['codigo' => 'BS', 'nombre' => 'SERIE BS']);

        $establecimiento->series()->attach($this->serieA->id, ['obligatoria' => true]);
        $establecimiento->series()->attach($this->serieBS->id, ['obligatoria' => true]);

        $this->actingAs($this->encargado);
        $this->envio = app(EnvioService::class)->crear($establecimiento, 2026, 9, $this->encargado);
        $this->envio->update(['estado' => Envio::EN_REVISION]);
    }

    private function archivo(Serie $serie, string $estado = EnvioArchivo::VALIDO): EnvioArchivo
    {
        return $this->envio->archivos()->create([
            'serie_id' => $serie->id, 'version' => 1, 'es_vigente' => true,
            'nombre_original' => '123100'.$serie->codigo.'09.xlsx',
            'ruta' => 'envios/x.xlsx', 'disco' => 'local',
            'sha256' => str_repeat('b', 64), 'tamano_bytes' => 100, 'mime' => 'application/vnd.ms-excel',
            'estado' => $estado, 'snapshot_id' => $this->snapshot->id,
            'subido_por' => $this->encargado->id,
        ]);
    }

    private function aprobar(): Envio
    {
        return app(EstadoEnvioService::class)->aprobar($this->envio->fresh(), $this->revisor);
    }

    public function test_aprueba_un_envio_completo_y_sin_observaciones(): void
    {
        $this->archivo($this->serieA);
        $this->archivo($this->serieBS);

        $this->aprobar();

        $this->assertSame(Envio::APROBADO, $this->envio->fresh()->estado);
    }

    public function test_no_aprueba_con_observaciones_abiertas(): void
    {
        $this->archivo($this->serieA);
        $this->archivo($this->serieBS);
        app(ObservacionService::class)->registrar($this->envio, $this->revisor, 'Falta la columna 12');

        try {
            $this->aprobar();
            $this->fail('Debería rechazar por observaciones');
        } catch (EnvioInvalidoException $e) {
            $this->assertSame('APP-ENV-009', $e->codigo);
        }

        $this->assertSame(Envio::EN_REVISION, $this->envio->fresh()->estado);
    }

    public function test_no_aprueba_con_archivos_bloqueados(): void
    {
        $this->archivo($this->serieA, EnvioArchivo::CON_ERRORES);
        $this->archivo($this->serieBS);

        try {
            $this->aprobar();
            $this->fail('Debería rechazar por bloqueantes');
        } catch (EnvioInvalidoException $e) {
            $this->assertSame('APP-ENV-010', $e->codigo);
        }
    }

    public function test_no_aprueba_si_falta_una_serie_exigida(): void
    {
        $this->archivo($this->serieA);

        try {
            $this->aprobar();
            $this->fail('Debería rechazar por incompleto');
        } catch (EnvioInvalidoException $e) {
            $this->assertSame('APP-ENV-011', $e->codigo);
        }
    }

    public function test_no_aprueba_si_el_envio_no_esta_en_revision(): void
    {
        $this->archivo($this->serieA);
        $this->archivo($this->serieBS);
        $this->envio->update(['estado' => Envio::RECIBIDO]);

        $this->expectException(EnvioInvalidoException::class);

        $this->aprobar();
    }

    public function test_la_aprobacion_queda_en_el_historial_con_su_actor(): void
    {
        $this->archivo($this->serieA);
        $this->archivo($this->serieBS);

        $this->aprobar();

        $this->assertDatabaseHas('envio_transiciones', [
            'envio_id'     => $this->envio->id,
            'estado_nuevo' => 'aprobado',
            'actor_id'     => $this->revisor->id,
        ]);
    }

    public function test_la_pantalla_muestra_el_motivo_del_rechazo(): void
    {
        $this->archivo($this->serieA);

        $this->actingAs($this->revisor)
            ->post("/revision/{$this->envio->id}/aprobar")
            ->assertSessionHasErrors('aprobar');
    }
}
