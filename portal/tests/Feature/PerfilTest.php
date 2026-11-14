<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Cuenta propia (D-030): nombre y contraseña; el correo lo administra el portal.
 */
class PerfilTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->usuario = User::factory()->create([
            'name'     => 'Encargado Prueba',
            'email'    => 'perfil@example.com',
            'password' => Hash::make('ClaveInicial123'),
            'activo'   => true,
        ]);
        $this->usuario->assignRole('encargado');
    }

    public function test_el_usuario_cambia_su_clave(): void
    {
        $this->actingAs($this->usuario)
            ->put('/perfil', [
                'name'                  => 'Encargado Prueba',
                'actual'                => 'ClaveInicial123',
                'password'              => 'ClaveNuevaSegura1',
                'password_confirmation' => 'ClaveNuevaSegura1',
            ])
            ->assertRedirect(route('perfil.edit'));

        $this->assertTrue(Hash::check('ClaveNuevaSegura1', $this->usuario->fresh()->password));
        $this->assertFalse(Hash::check('ClaveInicial123', $this->usuario->fresh()->password));
    }

    public function test_exige_la_clave_actual(): void
    {
        $this->actingAs($this->usuario)
            ->put('/perfil', [
                'name'                  => 'Encargado Prueba',
                'actual'                => 'ClaveEquivocada1',
                'password'              => 'ClaveNuevaSegura1',
                'password_confirmation' => 'ClaveNuevaSegura1',
            ])
            ->assertSessionHasErrors('actual');

        $this->assertTrue(Hash::check('ClaveInicial123', $this->usuario->fresh()->password));
    }

    public function test_la_clave_nueva_necesita_doce_caracteres(): void
    {
        $this->actingAs($this->usuario)
            ->put('/perfil', [
                'name'                  => 'Encargado Prueba',
                'actual'                => 'ClaveInicial123',
                'password'              => 'corta123',
                'password_confirmation' => 'corta123',
            ])
            ->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('ClaveInicial123', $this->usuario->fresh()->password));
    }

    public function test_el_correo_no_se_cambia_desde_el_perfil(): void
    {
        $this->actingAs($this->usuario)
            ->put('/perfil', [
                'name'  => 'Encargado Prueba',
                'email' => 'otro@example.com',
            ])
            ->assertRedirect(route('perfil.edit'));

        $this->assertSame('perfil@example.com', $this->usuario->fresh()->email);
    }

    public function test_la_pantalla_de_perfil_muestra_la_cuenta(): void
    {
        $this->actingAs($this->usuario)
            ->get('/perfil')
            ->assertOk()
            ->assertSee('Mi cuenta')
            ->assertSee('perfil@example.com')
            ->assertSee('Lo fija el administrador');
    }
}
