<?php

namespace Tests\Feature;

use App\Exceptions\EnvioInvalidoException;
use App\Models\Apertura;
use App\Models\Establecimiento;
use App\Models\Periodo;
use App\Models\Serie;
use App\Models\User;
use App\Services\AperturaService;
use App\Services\EnvioService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Aperturas de fuera de plazo (RF-043, D-044).
 *
 * La regla completa: el encargado pide, el administrador resuelve, y una
 * ventana aprobada habilita **solo** a su establecimiento mientras está
 * vigente. Fuera de la ventana, el período cerrado vuelve a cerrar.
 */
class AperturaFueraPlazoTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $encargado;
    private User $revisor;
    private Establecimiento $hospital;
    private Establecimiento $posta;
    private Serie $serie;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Storage::fake('local');

        $this->hospital = Establecimiento::create(['codigo' => '123100', 'nombre' => 'Hospital Base', 'comuna' => 'Osorno']);
        $this->posta = Establecimiento::create(['codigo' => '123105', 'nombre' => 'Posta', 'comuna' => 'San Pablo']);

        // Período cerrado: es el escenario que la apertura existe para abrir.
        Periodo::create(['anio' => 2026, 'mes' => 9, 'estado' => Periodo::CERRADO, 'fecha_limite' => '2026-10-07']);

        $this->serie = Serie::create(['codigo' => 'A', 'nombre' => 'SERIE A']);
        $this->hospital->series()->attach($this->serie->id, ['obligatoria' => true]);
        $this->posta->series()->attach($this->serie->id, ['obligatoria' => true]);

        $this->admin = User::factory()->create(['activo' => true]);
        $this->admin->assignRole('admin');

        $this->encargado = User::factory()->create(['activo' => true]);
        $this->encargado->assignRole('encargado');
        $this->encargado->establecimientos()->attach($this->hospital->id);

        $this->revisor = User::factory()->create(['activo' => true]);
        $this->revisor->assignRole('revisor');
        $this->revisor->establecimientos()->attach($this->hospital->id);
    }

    private function aperturas(): AperturaService
    {
        return app(AperturaService::class);
    }

    public function test_sin_apertura_el_periodo_cerrado_no_acepta_cargas(): void
    {
        $this->expectException(EnvioInvalidoException::class);

        app(EnvioService::class)->crear($this->hospital, 2026, 9, $this->encargado);
    }

    public function test_el_encargado_pide_y_el_administrador_recibe_el_aviso(): void
    {
        $this->actingAs($this->encargado)
            ->post(route('aperturas.store'), [
                'establecimiento_id' => $this->hospital->id,
                'anio'               => 2026,
                'mes'                => 9,
                'motivo'             => 'El sistema del hospital estuvo caído toda la primera semana.',
            ])
            ->assertRedirect(route('aperturas.index'));

        $this->assertDatabaseHas('aperturas', [
            'establecimiento_id' => $this->hospital->id,
            'anio'               => 2026,
            'mes'                => 9,
            'estado'             => Apertura::PENDIENTE,
        ]);

        $this->assertTrue(
            $this->admin->notifications()->where('data->evento', 'apertura_solicitada')->exists(),
            'El administrador debe enterarse de la solicitud.'
        );
        $this->assertDatabaseHas('auditoria', ['evento' => 'apertura.solicitada']);
    }

    public function test_solo_el_administrador_resuelve(): void
    {
        $apertura = $this->aperturas()->solicitar(
            $this->encargado, $this->hospital, 2026, 9, 'El sistema del hospital estuvo caído toda la semana.'
        )['apertura'];

        $this->actingAs($this->encargado)
            ->post(route('aperturas.resolver', $apertura), [
                'decision' => 'aprobar',
                'motivo'   => 'Yo mismo me la apruebo.',
            ])
            ->assertForbidden();

        $this->assertSame(Apertura::PENDIENTE, $apertura->fresh()->estado);
    }

    public function test_la_aprobacion_habilita_solo_a_su_establecimiento(): void
    {
        $apertura = $this->aperturas()->solicitar(
            $this->encargado, $this->hospital, 2026, 9, 'El sistema del hospital estuvo caído toda la semana.'
        )['apertura'];

        $this->actingAs($this->admin)
            ->post(route('aperturas.resolver', $apertura), [
                'decision'      => 'aprobar',
                'motivo'        => 'Se autoriza por la contingencia informática.',
                'vigente_hasta' => now()->addDays(5)->toDateString(),
            ])
            ->assertRedirect(route('aperturas.index'));

        $this->assertSame(Apertura::APROBADA, $apertura->fresh()->estado);

        // El autorizado puede crear el envío.
        $envio = app(EnvioService::class)->crear($this->hospital, 2026, 9, $this->encargado);
        $this->assertSame($apertura->id, $envio->apertura_id);

        // El otro establecimiento sigue cerrado.
        $otroEncargado = User::factory()->create(['activo' => true]);
        $otroEncargado->assignRole('encargado');
        $otroEncargado->establecimientos()->attach($this->posta->id);

        $this->expectException(EnvioInvalidoException::class);
        app(EnvioService::class)->crear($this->posta, 2026, 9, $otroEncargado);
    }

    public function test_una_apertura_para_todos_habilita_a_cualquier_establecimiento(): void
    {
        $this->aperturas()->otorgar(
            $this->admin, null, 2026, 9,
            'Se reabre el período para todo el Servicio por un cambio de instructivo.',
            now()->addWeek()->toDateString()
        );

        $otroEncargado = User::factory()->create(['activo' => true]);
        $otroEncargado->assignRole('encargado');
        $otroEncargado->establecimientos()->attach($this->posta->id);

        $envio = app(EnvioService::class)->crear($this->posta, 2026, 9, $otroEncargado);

        $this->assertNotNull($envio->id);
    }

    public function test_una_apertura_vencida_no_habilita_nada(): void
    {
        $this->aperturas()->otorgar(
            $this->admin, $this->hospital, 2026, 9,
            'Se abre por la contingencia, con plazo corto.',
            now()->subDay()->toDateString()
        );

        $this->assertFalse($this->aperturas()->permite($this->hospital, 2026, 9));

        $this->expectException(EnvioInvalidoException::class);
        app(EnvioService::class)->crear($this->hospital, 2026, 9, $this->encargado);
    }

    public function test_la_solicitud_exige_motivo_suficiente(): void
    {
        $this->actingAs($this->encargado)
            ->post(route('aperturas.store'), [
                'establecimiento_id' => $this->hospital->id,
                'anio'               => 2026,
                'mes'                => 9,
                'motivo'             => 'tarde',
            ])
            ->assertSessionHasErrors('motivo');

        $this->assertSame(0, Apertura::count());
    }

    public function test_una_solicitud_pendiente_igual_se_reutiliza(): void
    {
        $primera = $this->aperturas()->solicitar($this->encargado, $this->hospital, 2026, 9, 'Motivo suficientemente largo uno.');
        $segunda = $this->aperturas()->solicitar($this->encargado, $this->hospital, 2026, 9, 'Motivo suficientemente largo dos.');

        $this->assertFalse($segunda['creada']);
        $this->assertSame($primera['apertura']->id, $segunda['apertura']->id);
        $this->assertSame(1, Apertura::count());
    }

    public function test_el_resolver_exige_motivo_de_resolucion(): void
    {
        $apertura = $this->aperturas()->solicitar(
            $this->encargado, $this->hospital, 2026, 9, 'El sistema del hospital estuvo caído toda la semana.'
        )['apertura'];

        $this->actingAs($this->admin)
            ->post(route('aperturas.resolver', $apertura), ['decision' => 'aprobar', 'motivo' => 'ok'])
            ->assertSessionHasErrors('motivo');

        $this->assertSame(Apertura::PENDIENTE, $apertura->fresh()->estado);
    }

    public function test_una_solicitud_ya_resuelta_no_se_vuelve_a_resolver(): void
    {
        $apertura = $this->aperturas()->solicitar(
            $this->encargado, $this->hospital, 2026, 9, 'El sistema del hospital estuvo caído toda la semana.'
        )['apertura'];

        $this->aperturas()->resolver($apertura, $this->admin, true, 'Se autoriza la apertura solicitada.');

        $this->expectException(EnvioInvalidoException::class);
        $this->aperturas()->resolver($apertura->fresh(), $this->admin, false, 'Se rechaza ahora.');
    }

    public function test_el_encargado_no_puede_pedir_por_un_establecimiento_ajeno(): void
    {
        $this->actingAs($this->encargado)
            ->post(route('aperturas.store'), [
                'establecimiento_id' => $this->posta->id,
                'anio'               => 2026,
                'mes'                => 9,
                'motivo'             => 'Quiero abrir el período de otro establecimiento.',
            ])
            ->assertForbidden();
    }

    public function test_la_bandeja_del_encargado_solo_muestra_sus_establecimientos(): void
    {
        $this->aperturas()->otorgar($this->admin, $this->hospital, 2026, 9, 'Apertura del hospital por contingencia.', now()->addWeek()->toDateString());
        $this->aperturas()->otorgar($this->admin, $this->posta, 2026, 9, 'Apertura de la posta por contingencia.', now()->addWeek()->toDateString());

        $this->actingAs($this->encargado)
            ->get(route('aperturas.index'))
            ->assertOk()
            ->assertSee('123100')
            ->assertDontSee('123105');
    }
}
