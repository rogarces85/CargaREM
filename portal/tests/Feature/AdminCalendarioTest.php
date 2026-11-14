<?php

namespace Tests\Feature;

use App\Models\Periodo;
use App\Models\User;
use Database\Seeders\FeriadosSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pantalla de calendario y feriados del administrador (D-029).
 */
class AdminCalendarioTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(FeriadosSeeder::class);
    }

    private function admin(): User
    {
        $u = User::factory()->create(['activo' => true]);
        $u->assignRole('admin');

        return $u;
    }

    public function test_el_admin_ve_el_calendario_del_anio(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/calendario?anio=2026')
            ->assertOk()
            ->assertSee('quinto día hábil')
            ->assertSee('07-10-2026')
            ->assertSee('Feriados de 2026');
    }

    public function test_aplicar_crea_los_doce_periodos(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/calendario/aplicar', ['anio' => 2026])
            ->assertRedirect(route('admin.calendario.index', ['anio' => 2026]));

        $this->assertSame(12, Periodo::count());

        $septiembre = Periodo::where('anio', 2026)->where('mes', 9)->first();

        $this->assertSame('2026-10-07', $septiembre->fecha_limite->toDateString());
        $this->assertSame(Periodo::ABIERTO, $septiembre->estado);
    }

    public function test_aplicar_respeta_el_override_manual_y_el_estado(): void
    {
        $manual = Periodo::create([
            'anio' => 2026, 'mes' => 9, 'estado' => Periodo::CERRADO,
            'fecha_limite' => '2026-10-20', 'fecha_limite_manual' => true,
        ]);

        $this->actingAs($this->admin())
            ->post('/admin/calendario/aplicar', ['anio' => 2026]);

        $manual = $manual->fresh();

        $this->assertSame('2026-10-20', $manual->fecha_limite->toDateString());
        $this->assertSame(Periodo::CERRADO, $manual->estado);
        $this->assertTrue($manual->fecha_limite_manual);
    }

    public function test_un_encargado_no_entra_al_calendario(): void
    {
        $encargado = User::factory()->create(['activo' => true]);
        $encargado->assignRole('encargado');

        $this->actingAs($encargado)->get('/admin/calendario')->assertForbidden();
    }

    public function test_el_admin_gestiona_feriados(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/feriados', [
                'fecha'  => '2026-11-04',
                'nombre' => 'Feriado de prueba',
                'ambito' => 'nacional',
            ])
            ->assertRedirect(route('admin.feriados.index'));

        $this->assertDatabaseHas('feriados', ['fecha' => '2026-11-04', 'nombre' => 'Feriado de prueba']);

        $this->actingAs($this->admin())
            ->get('/admin/feriados')
            ->assertOk()
            ->assertSee('Feriado de prueba');
    }

    public function test_no_se_repite_un_feriado_en_la_misma_fecha(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/feriados', [
                'fecha'  => '2026-01-01',
                'nombre' => 'Repetido',
                'ambito' => 'nacional',
            ])
            ->assertSessionHasErrors('fecha');

        $this->assertDatabaseMissing('feriados', ['nombre' => 'Repetido']);
    }
}
