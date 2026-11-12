<?php

namespace Tests\Feature;

use App\Models\CatalogoSnapshot;
use App\Models\Envio;
use App\Models\EnvioArchivo;
use App\Models\Establecimiento;
use App\Models\Periodo;
use App\Models\Serie;
use App\Models\Servicio;
use App\Models\User;
use App\Notifications\EnvioNotificacion;
use App\Services\EnvioService;
use App\Services\EstadoEnvioService;
use App\Services\Notificador;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Notificaciones por correo (T-F3-11, T-F3-12).
 */
class NotificacionTest extends TestCase
{
    use RefreshDatabase;

    private Envio $envio;
    private User $encargado;
    private User $revisor;
    private User $otroRevisor;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Storage::fake('local');
        Notification::fake();

        $servicio = Servicio::create(['codigo' => '23', 'nombre' => 'Osorno']);
        $otroServicio = Servicio::create(['codigo' => '24', 'nombre' => 'Otro SS']);

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

        $this->otroRevisor = User::factory()->create(['activo' => true, 'servicio_id' => $otroServicio->id]);
        $this->otroRevisor->assignRole('revisor');

        $this->admin = User::factory()->create(['activo' => true, 'servicio_id' => $servicio->id]);
        $this->admin->assignRole('admin');

        $serie = Serie::create(['codigo' => 'A', 'nombre' => 'SERIE A']);
        $establecimiento->series()->attach($serie->id, ['obligatoria' => true]);

        $this->actingAs($this->encargado);
        $this->envio = app(EnvioService::class)->crear($establecimiento, 2026, 9, $this->encargado);

        $this->envio->archivos()->create([
            'serie_id' => $serie->id, 'version' => 1, 'es_vigente' => true,
            'nombre_original' => '123100A09.xlsx', 'ruta' => 'envios/x.xlsx', 'disco' => 'local',
            'sha256' => str_repeat('e', 64), 'tamano_bytes' => 100, 'mime' => 'application/vnd.ms-excel',
            'estado' => EnvioArchivo::VALIDO, 'snapshot_id' => $snapshot->id,
            'subido_por' => $this->encargado->id,
        ]);
    }

    private function estados(): EstadoEnvioService
    {
        return app(EstadoEnvioService::class);
    }

    public function test_al_enviar_a_revision_avisa_a_los_revisores_del_servicio(): void
    {
        $this->envio->update(['estado' => Envio::VALIDADO]);

        $this->estados()->transicionar($this->envio->fresh(), Envio::EN_REVISION, $this->encargado);

        Notification::assertSentTo($this->revisor, EnvioNotificacion::class);
        Notification::assertNotSentTo($this->otroRevisor, EnvioNotificacion::class);
        Notification::assertNotSentTo($this->encargado, EnvioNotificacion::class);
    }

    public function test_al_observar_avisa_al_encargado_y_no_al_revisor(): void
    {
        $this->envio->update(['estado' => Envio::EN_REVISION]);

        $this->estados()->transicionar($this->envio->fresh(), Envio::OBSERVADO, $this->revisor, 'Falta la columna 12');

        Notification::assertSentTo($this->encargado, EnvioNotificacion::class);
        Notification::assertNotSentTo($this->revisor, EnvioNotificacion::class, function ($n) {
            return $n->evento === 'observado';
        });
    }

    public function test_al_aprobar_avisa_al_encargado(): void
    {
        $this->envio->update(['estado' => Envio::EN_REVISION]);

        $this->estados()->aprobar($this->envio->fresh(), $this->revisor);

        Notification::assertSentTo($this->encargado, EnvioNotificacion::class, function (EnvioNotificacion $n) {
            return $n->evento === 'aprobado';
        });
    }

    public function test_al_confirmar_la_carga_avisa_al_encargado(): void
    {
        $this->envio->update(['estado' => Envio::EN_REVISION]);
        $aprobado = $this->estados()->aprobar($this->envio->fresh(), $this->revisor);

        $this->estados()->confirmarCarga($aprobado, $this->revisor, 'ACTA-9');

        Notification::assertSentTo($this->encargado, EnvioNotificacion::class, function (EnvioNotificacion $n) {
            return $n->evento === 'cargado';
        });
    }

    public function test_no_avisa_a_quien_provoco_el_cambio(): void
    {
        $this->envio->update(['estado' => Envio::VALIDADO]);

        $this->estados()->transicionar($this->envio->fresh(), Envio::EN_REVISION, $this->revisor);

        Notification::assertNotSentTo($this->revisor, EnvioNotificacion::class);
    }

    public function test_sin_revisor_asignado_avisa_al_admin(): void
    {
        $this->revisor->update(['activo' => false]);
        $this->envio->update(['estado' => Envio::VALIDADO]);

        $this->estados()->transicionar($this->envio->fresh(), Envio::EN_REVISION, $this->encargado);

        Notification::assertSentTo($this->admin, EnvioNotificacion::class);
    }

    public function test_el_aviso_deja_rastro_en_la_auditoria(): void
    {
        $this->envio->update(['estado' => Envio::EN_REVISION]);

        $this->estados()->transicionar($this->envio->fresh(), Envio::OBSERVADO, $this->revisor, 'motivo');

        $this->assertDatabaseHas('auditoria', ['evento' => 'envio.notificado']);
    }

    public function test_un_fallo_del_correo_no_rompe_la_transicion(): void
    {
        $this->envio->update(['estado' => Envio::EN_REVISION]);

        // Notificador que explota: el estado igual tiene que cambiar.
        $this->app->bind(Notificador::class, function () {
            return new class extends Notificador {
                public function enviar(Envio $envio, string $evento, ?User $actor = null): array
                {
                    throw new \RuntimeException('el correo está caído');
                }
            };
        });

        $this->estados()->transicionar($this->envio->fresh(), Envio::OBSERVADO, $this->revisor, 'motivo');

        $this->assertSame(Envio::OBSERVADO, $this->envio->fresh()->estado);
    }

    public function test_el_aviso_va_por_correo_y_queda_en_la_tabla(): void
    {
        $this->envio->update(['estado' => Envio::EN_REVISION]);

        $this->estados()->transicionar($this->envio->fresh(), Envio::OBSERVADO, $this->revisor, 'motivo');

        // El canal `database` es el respaldo: si el correo falla, el aviso
        // sigue estando en el portal.
        Notification::assertSentTo($this->encargado, EnvioNotificacion::class, function (EnvioNotificacion $n) {
            return in_array('mail', $n->via($this->encargado), true)
                && in_array('database', $n->via($this->encargado), true);
        });
    }
}
