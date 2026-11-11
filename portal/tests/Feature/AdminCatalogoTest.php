<?php

namespace Tests\Feature;

use App\Models\CatalogoSnapshot;
use App\Models\Periodo;
use App\Models\Servicio;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CRUD de administración de catálogos (T-F1-22).
 *
 * El permiso se comprueba en el servidor: la vista no es la que decide.
 */
class AdminCatalogoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function admin(): User
    {
        $u = User::factory()->create(['activo' => true]);
        $u->assignRole('admin');

        return $u;
    }

    private function encargado(): User
    {
        $u = User::factory()->create(['activo' => true]);
        $u->assignRole('encargado');

        return $u;
    }

    public function test_un_invitado_va_al_login(): void
    {
        $this->get('/admin/servicios')->assertRedirect('/login');
    }

    public function test_un_encargado_no_entra_a_la_administracion(): void
    {
        $this->actingAs($this->encargado())
            ->get('/admin/servicios')
            ->assertForbidden();
    }

    public function test_el_admin_ve_el_listado(): void
    {
        Servicio::create(['codigo' => '23', 'nombre' => 'Osorno']);

        $this->actingAs($this->admin())
            ->get('/admin/servicios')
            ->assertOk()
            ->assertSee('Osorno');
    }

    public function test_el_admin_crea_un_periodo(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/periodos', [
                'anio'   => 2026,
                'mes'    => 9,
                'estado' => Periodo::ABIERTO,
            ])
            ->assertRedirect(route('admin.periodos.index'));

        $this->assertDatabaseHas('periodos', ['anio' => 2026, 'mes' => 9, 'estado' => 'abierto']);
    }

    public function test_no_se_pueden_repetir_periodo_anio_mes(): void
    {
        Periodo::create(['anio' => 2026, 'mes' => 9, 'estado' => Periodo::ABIERTO]);

        $this->actingAs($this->admin())
            ->post('/admin/periodos', [
                'anio'   => 2026,
                'mes'    => 9,
                'estado' => Periodo::ABIERTO,
            ])
            ->assertSessionHasErrors();

        $this->assertSame(1, Periodo::count());
    }

    public function test_valida_los_datos_del_servicio(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/servicios', ['codigo' => '', 'nombre' => ''])
            ->assertSessionHasErrors(['codigo', 'nombre']);
    }

    public function test_el_admin_edita_un_servicio(): void
    {
        $servicio = Servicio::create(['codigo' => '23', 'nombre' => 'Osorno']);

        $this->actingAs($this->admin())
            ->put("/admin/servicios/{$servicio->id}", [
                'codigo' => '23',
                'nombre' => 'Servicio de Salud Osorno',
                'activo' => 1,
            ])
            ->assertRedirect(route('admin.servicios.index'));

        $this->assertSame('Servicio de Salud Osorno', $servicio->fresh()->nombre);
    }

    public function test_el_admin_ve_los_snapshots_importados(): void
    {
        CatalogoSnapshot::create(['origen' => 'snapshot de prueba', 'activo' => true]);

        $this->actingAs($this->admin())
            ->get('/admin/snapshots')
            ->assertOk()
            ->assertSee('snapshot de prueba')
            ->assertSee('activo');
    }

    public function test_un_encargado_no_puede_activar_un_snapshot(): void
    {
        $snapshot = CatalogoSnapshot::create(['origen' => 'prueba']);

        $this->actingAs($this->encargado())
            ->post("/admin/snapshots/{$snapshot->id}/activar")
            ->assertForbidden();

        $this->assertFalse($snapshot->fresh()->activo);
    }
}
