<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private function usuario(array $atributos = []): User
    {
        return User::factory()->create(array_merge([
            'password' => Hash::make('ContraseñaLarga123'),
            'activo'   => true,
        ], $atributos));
    }

    public function test_la_pantalla_de_acceso_se_renderiza(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Ingrese con su cuenta del portal');
    }

    public function test_un_usuario_activo_entra_y_va_al_panel(): void
    {
        $usuario = $this->usuario(['email' => 'encargado@ssosorno.cl']);

        $this->post('/login', [
            'email'    => 'encargado@ssosorno.cl',
            'password' => 'ContraseñaLarga123',
        ])->assertRedirect('/panel');

        $this->assertAuthenticatedAs($usuario);
    }

    public function test_un_usuario_desactivado_no_entra_aunque_la_clave_sea_correcta(): void
    {
        $this->usuario(['email' => 'baja@ssosorno.cl', 'activo' => false]);

        $this->from('/login')->post('/login', [
            'email'    => 'baja@ssosorno.cl',
            'password' => 'ContraseñaLarga123',
        ])->assertRedirect('/login');

        $this->assertGuest();
    }

    public function test_credenciales_incorrectas_no_entran(): void
    {
        $this->usuario(['email' => 'otro@ssosorno.cl']);

        $this->post('/login', [
            'email'    => 'otro@ssosorno.cl',
            'password' => 'incorrecta',
        ]);

        $this->assertGuest();
    }

    public function test_el_panel_exige_autenticacion(): void
    {
        $this->get('/panel')->assertRedirect('/login');
    }

    public function test_el_panel_se_ve_autenticado(): void
    {
        $usuario = $this->usuario();

        $this->actingAs($usuario)
            ->get('/panel')
            ->assertOk()
            ->assertSee($usuario->name);
    }

    public function test_cerrar_sesion_deja_de_estar_autenticado(): void
    {
        $usuario = $this->usuario();

        $this->actingAs($usuario)
            ->post('/logout')
            ->assertRedirect('/');

        $this->assertGuest();
    }

    public function test_el_seeder_crea_los_roles_y_la_matriz_de_permisos(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $this->assertSame(4, Role::count());
        $this->assertSame(count(RolePermissionSeeder::MATRIZ), Permission::count());

        foreach (RolePermissionSeeder::ROLES as $rol) {
            $this->assertTrue(Role::where('name', $rol)->exists(), "Falta el rol {$rol}");
        }

        // Un muestreo de la matriz: lo que solo el admin puede y lo que todos ven.
        $this->assertTrue(Role::findByName('admin')->hasPermissionTo('envios.reabrir'));
        $this->assertFalse(Role::findByName('revisor')->hasPermissionTo('envios.reabrir'));
        $this->assertFalse(Role::findByName('encargado')->hasPermissionTo('envios.aprobar'));
        $this->assertTrue(Role::findByName('consulta')->hasPermissionTo('completitud.ver'));
        $this->assertFalse(Role::findByName('consulta')->hasPermissionTo('envios.crear'));
    }
}
