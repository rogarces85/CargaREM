<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Endurecimiento de F1.2: sesión, límite de intentos, recuperación de
 * contraseña y coherencia entre la matriz declarada y la sembrada.
 */
class AuthHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_sesion_esta_configurada_segun_el_diseno(): void
    {
        $this->assertSame(480, (int) config('session.lifetime'), 'Sesión de 8 horas (R-ID-14)');
        $this->assertTrue((bool) config('session.http_only'), 'Cookie no accesible por JS');
        $this->assertSame('lax', config('session.same_site'));
    }

    public function test_el_login_se_bloquea_despues_de_cinco_intentos(): void
    {
        $usuario = User::factory()->create([
            'email'    => 'limite@ssosorno.cl',
            'password' => Hash::make('ContraseñaLarga123'),
            'activo'   => true,
        ]);

        // Los cinco primeros se rechazan por credenciales, no por límite.
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => $usuario->email, 'password' => 'mala']);
        }

        $respuesta = $this->post('/login', ['email' => $usuario->email, 'password' => 'mala']);

        // Fortify responde 302 con un error de validación, igual que un fallo
        // normal; lo que distingue el bloqueo es el mensaje.
        $errores = session('errors') ? session('errors')->get('email') : [];
        $texto = implode(' ', $errores);

        $this->assertMatchesRegularExpression(
            '/Too many|Demasiados/i',
            $texto,
            'El sexto intento debería quedar bloqueado. Errores recibidos: '.$texto
        );
        $this->assertGuest();
    }

    public function test_se_puede_pedir_el_enlace_de_recuperacion(): void
    {
        $usuario = User::factory()->create(['email' => 'recupera@ssosorno.cl']);

        $this->post('/forgot-password', ['email' => $usuario->email])
            ->assertSessionHasNoErrors();

        $this->assertNotNull(
            \Illuminate\Support\Facades\DB::table('password_reset_tokens')->where('email', $usuario->email)->first(),
            'Debe quedar un token de un solo uso (R-ID-11)'
        );
    }

    public function test_la_matriz_sembrada_coincide_con_el_fixture(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $fixture = json_decode(file_get_contents(base_path('../tests/Parity/fixtures/rbac-matrix.json')), true);
        $nombresFixture = array_column($fixture['permissions'], 'name');
        $nombresSeeder = array_keys(RolePermissionSeeder::MATRIZ);

        sort($nombresFixture);
        sort($nombresSeeder);

        $this->assertSame($nombresFixture, $nombresSeeder, 'El fixture y el seeder deben declarar los mismos permisos');
        $this->assertCount(18, $nombresSeeder);
        $this->assertSame(18, Permission::count());
    }
}
