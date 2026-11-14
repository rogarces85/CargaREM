<?php

namespace Tests\Feature;

use App\Models\Envio;
use App\Models\Establecimiento;
use App\Models\Periodo;
use App\Models\Serie;
use App\Models\User;
use App\Notifications\EnvioNotificacion;
use App\Services\EnvioService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Campana de notificaciones de la cabecera (MOD-09 recortado).
 */
class CampanaTest extends TestCase
{
    use RefreshDatabase;

    private Envio $envio;
    private User $revisor;
    private User $encargado;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $establecimiento = Establecimiento::create([
            'codigo' => '123100', 'nombre' => 'Hospital Base',
        ]);
        Periodo::create(['anio' => 2026, 'mes' => 9, 'estado' => Periodo::ABIERTO, 'fecha_limite' => '2026-10-10']);

        $this->encargado = User::factory()->create(['activo' => true]);
        $this->encargado->assignRole('encargado');
        $this->encargado->establecimientos()->attach($establecimiento->id);

        $this->revisor = User::factory()->create(['activo' => true]);
        $this->revisor->assignRole('revisor');

        $serie = Serie::create(['codigo' => 'A', 'nombre' => 'SERIE A']);
        $establecimiento->series()->attach($serie->id, ['obligatoria' => true]);

        $this->actingAs($this->encargado);
        $this->envio = app(EnvioService::class)->crear($establecimiento, 2026, 9, $this->encargado);
    }

    private function avisar(User $destinatario, string $evento = 'enviado_a_revision'): void
    {
        $destinatario->notify(new EnvioNotificacion($this->envio, $evento, $this->encargado));
    }

    public function test_la_campana_cuenta_los_avisos_sin_leer(): void
    {
        $this->avisar($this->revisor);

        $this->actingAs($this->revisor)
            ->get('/panel')
            ->assertOk()
            ->assertSee('1 aviso(s) sin leer')
            ->assertSee('Envío pendiente de revisión');
    }

    public function test_sin_avisos_la_campana_queda_vacia(): void
    {
        $this->actingAs($this->revisor)
            ->get('/panel')
            ->assertOk()
            ->assertSee('0 aviso(s) sin leer')
            ->assertSee('Sin avisos.');
    }

    public function test_marcar_una_notificacion_abre_el_envio(): void
    {
        $this->avisar($this->revisor);

        $aviso = $this->revisor->notifications()->first();

        $this->actingAs($this->revisor)
            ->post("/notificaciones/{$aviso->id}/leer")
            ->assertRedirect(route('envios.show', $this->envio));

        $this->assertNotNull($aviso->fresh()->read_at);
    }

    public function test_marcar_todas_deja_la_campana_en_cero(): void
    {
        $this->avisar($this->revisor);
        $this->avisar($this->revisor, 'observado');

        $this->actingAs($this->revisor)
            ->post('/notificaciones/leer-todas')
            ->assertRedirect();

        $this->assertSame(0, $this->revisor->unreadNotifications()->count());
    }

    public function test_no_se_puede_marcar_un_aviso_ajeno(): void
    {
        $this->avisar($this->revisor);

        $aviso = $this->revisor->notifications()->first();
        $otro = User::factory()->create(['activo' => true]);
        $otro->assignRole('revisor');

        $this->actingAs($otro)
            ->post("/notificaciones/{$aviso->id}/leer")
            ->assertNotFound();

        $this->assertNull($aviso->fresh()->read_at);
    }
}
