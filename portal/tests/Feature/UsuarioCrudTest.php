<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * CRUD de usuarios (RF-013 a RF-016, CA-013 a CA-016).
 */
class UsuarioCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function admin(): User
    {
        $u = User::factory()->create(['activo' => true, 'password' => Hash::make('ContraseñaLarga123')]);
        $u->assignRole('admin');

        return $u;
    }

    public function test_el_admin_lista_usuarios(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get('/admin/usuarios')
            ->assertOk()
            ->assertSee($admin->email);
    }

    public function test_un_encargado_no_gestiona_usuarios(): void
    {
        $u = User::factory()->create(['activo' => true]);
        $u->assignRole('encargado');

        $this->actingAs($u)->get('/admin/usuarios')->assertForbidden();
    }

    public function test_crea_un_usuario_con_rol(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/usuarios', [
                'name'     => 'Nuevo Revisor',
                'email'    => 'nuevo@ssosorno.cl',
                'password' => 'ContraseñaLarga123',
                'rol'      => 'revisor',
                'activo'   => '1',
            ])
            ->assertRedirect(route('admin.usuarios.index'));

        $creado = User::where('email', 'nuevo@ssosorno.cl')->first();

        $this->assertNotNull($creado);
        $this->assertTrue($creado->hasRole('revisor'));
        $this->assertTrue(Hash::check('ContraseñaLarga123', $creado->password));
    }

    public function test_exige_contrasena_de_doce_caracteres(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/usuarios', [
                'name'     => 'Corto',
                'email'    => 'corto@ssosorno.cl',
                'password' => 'corta',
                'rol'      => 'encargado',
            ])
            ->assertSessionHasErrors('password');
    }

    public function test_desactiva_a_otro_usuario(): void
    {
        $otro = User::factory()->create(['activo' => true]);
        $otro->assignRole('encargado');

        $this->actingAs($this->admin())
            ->put("/admin/usuarios/{$otro->id}", [
                'name'   => $otro->name,
                'email'  => $otro->email,
                'rol'    => 'encargado',
                'activo' => '0',
            ])
            ->assertRedirect(route('admin.usuarios.index'));

        $this->assertFalse($otro->fresh()->activo);
    }

    public function test_el_admin_no_puede_desactivarse_a_si_mismo(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->put("/admin/usuarios/{$admin->id}", [
                'name'   => $admin->name,
                'email'  => $admin->email,
                'rol'    => 'admin',
                'activo' => '0',
            ])
            ->assertSessionHasErrors('activo');

        $this->assertTrue($admin->fresh()->activo);
    }

    public function test_el_admin_no_puede_quitarse_el_rol(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->put("/admin/usuarios/{$admin->id}", [
                'name'   => $admin->name,
                'email'  => $admin->email,
                'rol'    => 'encargado',
                'activo' => '1',
            ])
            ->assertSessionHasErrors('rol');

        $this->assertTrue($admin->fresh()->hasRole('admin'));
    }

    public function test_el_admin_no_resetea_su_propia_clave(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->put("/admin/usuarios/{$admin->id}", [
                'name'     => $admin->name,
                'email'    => $admin->email,
                'rol'      => 'admin',
                'activo'   => '1',
                'password' => 'OtraContraseña123',
            ])
            ->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('ContraseñaLarga123', $admin->fresh()->password));
    }

    public function test_resetea_la_clave_de_otro_usuario(): void
    {
        $otro = User::factory()->create(['activo' => true, 'password' => Hash::make('ViejaContraseña123')]);
        $otro->assignRole('encargado');

        $this->actingAs($this->admin())
            ->put("/admin/usuarios/{$otro->id}", [
                'name'     => $otro->name,
                'email'    => $otro->email,
                'rol'      => 'encargado',
                'activo'   => '1',
                'password' => 'NuevaContraseña123',
            ]);

        $this->assertTrue(Hash::check('NuevaContraseña123', $otro->fresh()->password));
    }
}
