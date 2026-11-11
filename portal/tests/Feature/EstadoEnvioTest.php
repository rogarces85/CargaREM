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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Máquina de estados (T-F2-28, principio 7).
 */
class EstadoEnvioTest extends TestCase
{
    use RefreshDatabase;

    private Envio $envio;
    private User $encargado;
    private User $revisor;
    private EnvioArchivo $archivo;

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
        $this->actingAs($this->encargado);

        $this->revisor = User::factory()->create(['activo' => true, 'servicio_id' => $servicio->id]);
        $this->revisor->assignRole('revisor');

        $serie = Serie::create(['codigo' => 'A', 'nombre' => 'SERIE A']);
        $establecimiento->series()->attach($serie->id, ['obligatoria' => true]);

        $this->envio = app(EnvioService::class)->crear($establecimiento, 2026, 9, $this->encargado);

        $this->archivo = $this->envio->archivos()->create([
            'serie_id' => $serie->id, 'version' => 1, 'es_vigente' => true,
            'nombre_original' => '123100A09.xlsx', 'ruta' => 'envios/x.xlsx', 'disco' => 'local',
            'sha256' => str_repeat('a', 64), 'tamano_bytes' => 100, 'mime' => 'application/vnd.ms-excel',
            'estado' => EnvioArchivo::SUBIDO, 'snapshot_id' => $snapshot->id,
            'subido_por' => $this->encargado->id,
        ]);
    }

    private function estados(): EstadoEnvioService
    {
        return app(EstadoEnvioService::class);
    }

    public function test_sin_archivos_validados_el_envio_sigue_recibido(): void
    {
        $this->assertSame(Envio::RECIBIDO, $this->estados()->recalcular($this->envio));
    }

    public function test_con_todo_valido_pasa_a_validado(): void
    {
        $this->archivo->update(['estado' => EnvioArchivo::VALIDO]);

        $this->assertSame(Envio::VALIDADO, $this->estados()->recalcular($this->envio));
        $this->assertSame(Envio::VALIDADO, $this->envio->fresh()->estado);
    }

    public function test_con_un_bloqueante_pasa_a_con_errores(): void
    {
        $this->archivo->update(['estado' => EnvioArchivo::CON_ERRORES]);

        $this->assertSame(Envio::CON_ERRORES, $this->estados()->recalcular($this->envio));
    }

    public function test_una_transicion_automatica_no_tiene_actor(): void
    {
        $this->archivo->update(['estado' => EnvioArchivo::VALIDO]);
        $this->estados()->recalcular($this->envio);

        $this->assertDatabaseHas('envio_transiciones', [
            'envio_id'        => $this->envio->id,
            'estado_anterior' => 'recibido',
            'estado_nuevo'    => 'validado',
            'actor_id'        => null,
        ]);
    }

    public function test_un_envio_en_revision_no_se_recalcula(): void
    {
        $this->archivo->update(['estado' => EnvioArchivo::VALIDO]);
        $this->estados()->recalcular($this->envio);
        $this->estados()->transicionar($this->envio, Envio::EN_REVISION, $this->encargado);

        $this->archivo->update(['estado' => EnvioArchivo::CON_ERRORES]);

        $this->assertSame(Envio::EN_REVISION, $this->estados()->recalcular($this->envio->fresh()));
    }

    public function test_el_flujo_completo_hasta_cargado(): void
    {
        $this->archivo->update(['estado' => EnvioArchivo::VALIDO]);
        $this->estados()->recalcular($this->envio);

        $this->estados()->transicionar($this->envio, Envio::EN_REVISION, $this->encargado);
        $this->estados()->transicionar($this->envio->fresh(), Envio::APROBADO, $this->revisor);
        $this->estados()->transicionar($this->envio->fresh(), Envio::CARGADO, $this->revisor);

        $envio = $this->envio->fresh();

        $this->assertSame(Envio::CARGADO, $envio->estado);
        $this->assertNotNull($envio->fecha_aprobacion);
        $this->assertNotNull($envio->fecha_carga);
        $this->assertSame(5, $envio->transiciones()->count());
    }

    public function test_no_se_puede_aprobar_sin_pasar_por_revision(): void
    {
        $this->expectException(EnvioInvalidoException::class);

        $this->estados()->transicionar($this->envio, Envio::APROBADO, $this->revisor);
    }

    public function test_no_se_puede_cargar_sin_aprobar(): void
    {
        $this->archivo->update(['estado' => EnvioArchivo::VALIDO]);
        $this->estados()->recalcular($this->envio);
        $this->estados()->transicionar($this->envio, Envio::EN_REVISION, $this->encargado);

        $this->expectException(EnvioInvalidoException::class);

        $this->estados()->transicionar($this->envio->fresh(), Envio::CARGADO, $this->revisor);
    }

    public function test_anular_exige_motivo(): void
    {
        try {
            $this->estados()->transicionar($this->envio, Envio::ANULADO, $this->encargado);
            $this->fail('Debería exigir motivo');
        } catch (EnvioInvalidoException $e) {
            $this->assertSame('APP-ENV-007', $e->codigo);
        }
    }

    public function test_anular_con_motivo_lo_registra(): void
    {
        $this->estados()->transicionar($this->envio, Envio::ANULADO, $this->encargado, 'Período mal cargado');

        $this->assertSame(Envio::ANULADO, $this->envio->fresh()->estado);
        $this->assertDatabaseHas('envio_transiciones', [
            'envio_id' => $this->envio->id, 'estado_nuevo' => 'anulado', 'motivo' => 'Período mal cargado',
        ]);
    }

    public function test_un_estado_anulado_no_va_a_ninguna_parte(): void
    {
        $this->estados()->transicionar($this->envio, Envio::ANULADO, $this->encargado, 'error');

        $this->expectException(EnvioInvalidoException::class);

        $this->estados()->transicionar($this->envio->fresh(), Envio::EN_REVISION, $this->encargado);
    }

    public function test_desde_observado_se_puede_volver_a_recibido(): void
    {
        $this->archivo->update(['estado' => EnvioArchivo::VALIDO]);
        $this->estados()->recalcular($this->envio);
        $this->estados()->transicionar($this->envio, Envio::EN_REVISION, $this->encargado);
        $this->estados()->transicionar($this->envio->fresh(), Envio::OBSERVADO, $this->revisor, 'Falta hoja A03');

        $this->estados()->transicionar($this->envio->fresh(), Envio::RECIBIDO, $this->encargado);

        $this->assertSame(Envio::RECIBIDO, $this->envio->fresh()->estado);
    }

    public function test_cada_transicion_queda_auditada(): void
    {
        $this->archivo->update(['estado' => EnvioArchivo::VALIDO]);
        $this->estados()->recalcular($this->envio);
        $this->estados()->transicionar($this->envio, Envio::EN_REVISION, $this->encargado);

        $this->assertDatabaseHas('auditoria', ['evento' => 'envio.recalculado']);
        $this->assertDatabaseHas('auditoria', ['evento' => 'envio.transicion']);
    }
}
