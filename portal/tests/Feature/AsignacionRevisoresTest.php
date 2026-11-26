<?php

namespace Tests\Feature;

use App\Dominio\Alcance;
use App\Models\Envio;
use App\Models\Establecimiento;
use App\Models\Periodo;
use App\Models\User;
use App\Services\EnvioService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Alcance por asignación (R-ALC-01 a R-ALC-03, D-042).
 *
 * La v3 cambia el modelo: el revisor ya no ve todo su Servicio, ve solo lo que
 * el administrador le asigna. Estos tests fijan las tres consecuencias que
 * importan —el aislamiento entre revisores, el revisor sin asignación y el
 * reparto múltiple— y que el cambio se aplica en la consulta, no en la vista.
 */
class AsignacionRevisoresTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Establecimiento $hospital;
    private Establecimiento $posta;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create(['activo' => true]);
        $this->admin->assignRole('admin');

        $this->hospital = Establecimiento::create(['codigo' => '123100', 'nombre' => 'Hospital Base', 'comuna' => 'Osorno']);
        $this->posta = Establecimiento::create(['codigo' => '123105', 'nombre' => 'Posta San Pablo', 'comuna' => 'San Pablo']);

        Periodo::create(['anio' => 2026, 'mes' => 9, 'estado' => Periodo::ABIERTO]);
    }

    private function revisor(array $establecimientos = []): User
    {
        $revisor = User::factory()->create(['activo' => true]);
        $revisor->assignRole('revisor');
        $revisor->establecimientos()->sync(array_map(fn ($e) => $e->id, $establecimientos));

        return $revisor;
    }

    private function envio(Establecimiento $establecimiento): Envio
    {
        $encargado = User::factory()->create(['activo' => true]);
        $encargado->assignRole('encargado');
        $encargado->establecimientos()->attach($establecimiento->id);

        $this->actingAs($encargado);

        return app(EnvioService::class)->crear($establecimiento, 2026, 9, $encargado);
    }

    public function test_el_revisor_solo_ve_los_envios_de_sus_establecimientos(): void
    {
        $propio = $this->envio($this->hospital);
        $ajeno = $this->envio($this->posta);

        $revisor = $this->revisor([$this->hospital]);

        $this->assertTrue(Envio::query()->forUser($revisor)->whereKey($propio->id)->exists());
        $this->assertFalse(Envio::query()->forUser($revisor)->whereKey($ajeno->id)->exists());
    }

    public function test_el_revisor_sin_asignacion_no_ve_ningun_envio(): void
    {
        $envio = $this->envio($this->hospital);

        $revisor = $this->revisor();

        // El fallo que este test previene: caer a «todo» cuando falta el dato.
        $this->assertSame(0, Envio::query()->forUser($revisor)->count());
        $this->assertFalse(Envio::query()->forUser($revisor)->whereKey($envio->id)->exists());
    }

    public function test_dos_revisores_no_se_ven_los_envios_del_otro_ni_por_url_directa(): void
    {
        $delHospital = $this->envio($this->hospital);
        $deLaPosta = $this->envio($this->posta);

        $roxana = $this->revisor([$this->hospital]);

        $this->actingAs($roxana)
            ->get('/envios/'.$delHospital->id)
            ->assertOk();

        $this->actingAs($roxana)
            ->get('/envios/'.$deLaPosta->id)
            ->assertForbidden();
    }

    public function test_la_bandeja_de_revision_respeta_la_asignacion(): void
    {
        $propio = $this->envio($this->hospital);
        $ajeno = $this->envio($this->posta);

        $propio->update(['estado' => Envio::EN_REVISION]);
        $ajeno->update(['estado' => Envio::EN_REVISION]);

        $revisor = $this->revisor([$this->hospital]);

        $this->actingAs($revisor)
            ->get('/revision')
            ->assertOk()
            ->assertSee('123100')
            ->assertDontSee('123105');
    }

    public function test_un_establecimiento_puede_tener_varios_revisores(): void
    {
        $envio = $this->envio($this->hospital);

        $roxana = $this->revisor([$this->hospital]);
        $marcelo = $this->revisor([$this->hospital]);

        foreach ([$roxana, $marcelo] as $revisor) {
            $this->assertTrue(Envio::query()->forUser($revisor)->whereKey($envio->id)->exists());
        }

        $this->assertSame(2, $this->hospital->usuarios()->whereHas('roles', fn ($q) => $q->where('name', 'revisor'))->count());
    }

    public function test_el_administrador_ve_todos_los_envios(): void
    {
        $this->envio($this->hospital);
        $this->envio($this->posta);

        $this->assertSame(2, Envio::query()->forUser($this->admin)->count());
    }

    public function test_el_encargado_sigue_viendo_solo_lo_suyo(): void
    {
        $this->envio($this->hospital);
        $this->envio($this->posta);

        $encargado = User::factory()->create(['activo' => true]);
        $encargado->assignRole('encargado');
        $encargado->establecimientos()->attach($this->hospital->id);

        $this->assertSame(1, Envio::query()->forUser($encargado)->count());
    }

    public function test_el_alcance_detecta_al_usuario_sin_asignacion(): void
    {
        $alcance = app(Alcance::class);

        $this->assertTrue($alcance->sinAsignacion($this->revisor()));
        $this->assertFalse($alcance->sinAsignacion($this->revisor([$this->hospital])));

        // El administrador nunca «carece» de asignación: su alcance es global.
        $this->assertFalse($alcance->sinAsignacion($this->admin));
    }

    public function test_la_completitud_del_revisor_solo_trae_sus_establecimientos(): void
    {
        $revisor = $this->revisor([$this->hospital]);

        $this->actingAs($revisor)
            ->get('/completitud?anio=2026&mes=9')
            ->assertOk()
            ->assertSee('Hospital Base')
            ->assertDontSee('Posta San Pablo');
    }

    public function test_el_administrador_recibe_aviso_cuando_un_revisor_queda_sin_asignacion(): void
    {
        $revisor = $this->revisor([$this->hospital]);

        $this->actingAs($this->admin)
            ->put(route('admin.asignaciones.update', $revisor), ['establecimientos' => []])
            ->assertRedirect()
            ->assertSessionHas('ok');

        $this->assertSame(0, $revisor->establecimientos()->count());
        $this->assertTrue(
            $this->admin->notifications()->where('data->evento', 'sin_asignacion')->exists(),
            'El administrador debe quedar avisado de que hay un usuario sin establecimientos.'
        );
    }

    public function test_la_pantalla_de_asignaciones_solo_lista_establecimientos_activos(): void
    {
        Establecimiento::create(['codigo' => '999999', 'nombre' => 'Cerrado', 'comuna' => 'Osorno', 'activo' => false]);

        $revisor = $this->revisor();

        $this->actingAs($this->admin)
            ->get(route('admin.asignaciones.edit', $revisor))
            ->assertOk()
            ->assertSee('Hospital Base')
            ->assertDontSee('Cerrado');
    }

    public function test_un_encargado_comun_no_entra_a_las_asignaciones(): void
    {
        $encargado = User::factory()->create(['activo' => true]);
        $encargado->assignRole('encargado');

        $this->actingAs($encargado)
            ->get(route('admin.asignaciones.index'))
            ->assertForbidden();
    }
}
