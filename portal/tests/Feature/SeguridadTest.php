<?php

namespace Tests\Feature;

use App\Models\Envio;
use App\Models\User;
use App\Rules\ZipSeguro;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;
use ZipArchive;

/**
 * Endurecimiento (T-F6-01 a T-F6-04).
 */
class SeguridadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_las_respuestas_llevan_las_cabeceras_de_seguridad(): void
    {
        $respuesta = $this->get('/login');

        $respuesta->assertOk()
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_la_csp_es_restrictiva(): void
    {
        $csp = $this->get('/login')->headers->get('Content-Security-Policy');

        $this->assertNotEmpty($csp);
        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        // No se permite cargar scripts de cualquier origen.
        $this->assertStringNotContainsString('script-src *', $csp);
        $this->assertStringNotContainsString('https:', explode(';', $csp)[0]);
    }

    public function test_hsts_solo_aparece_con_https(): void
    {
        $respuesta = $this->get('/login');

        $this->assertNull($respuesta->headers->get('Strict-Transport-Security'));
    }

    public function test_las_rutas_de_escritura_pasan_por_csrf(): void
    {
        // En el entorno de pruebas Laravel omite la comprobación de CSRF, así
        // que el estado 419 no se puede provocar. Se comprueba que el grupo
        // `web` —y por lo tanto el middleware— esté en las rutas que escriben.
        $kernel = app(\Illuminate\Contracts\Http\Kernel::class);
        $reflexion = new \ReflectionClass($kernel);
        $propiedad = $reflexion->getProperty('middlewareGroups');
        $propiedad->setAccessible(true);

        $grupo = $propiedad->getValue($kernel)['web'] ?? [];

        $this->assertContains(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class, $grupo);

        foreach (['envios.store', 'envios.subir', 'revision.aprobar', 'revision.observar'] as $nombre) {
            $ruta = collect(app('router')->getRoutes())->first(fn ($r) => $r->getName() === $nombre);

            $this->assertNotNull($ruta, "Falta la ruta {$nombre}");
            $this->assertContains('web', $ruta->gatherMiddleware(), "La ruta {$nombre} no pasa por el grupo web");
        }
    }

    public function test_rechaza_una_bomba_de_descompresion(): void
    {
        $ruta = tempnam(sys_get_temp_dir(), 'bomba').'.zip';

        $zip = new ZipArchive();
        $zip->open($ruta, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        // 20 MB de ceros comprimen a unos pocos KB: ratio altísimo.
        $zip->addFromString('hoja.xml', str_repeat("\0", 20 * 1024 * 1024));
        $zip->close();

        $archivo = new UploadedFile($ruta, 'bomba.xlsx', null, null, true);

        $validador = Validator::make(
            ['archivo' => $archivo],
            ['archivo' => [new ZipSeguro()]]
        );

        $this->assertTrue($validador->fails(), 'Un ZIP que se expande 20 MB debe rechazarse');
        $this->assertStringContainsString('expande', $validador->errors()->first('archivo'));

        @unlink($ruta);
    }

    public function test_acepta_un_zip_normal(): void
    {
        $ruta = tempnam(sys_get_temp_dir(), 'normal').'.zip';

        $zip = new ZipArchive();
        $zip->open($ruta, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('xl/workbook.xml', '<workbook/>'.str_repeat('a', 500));
        $zip->close();

        $archivo = new UploadedFile($ruta, 'normal.xlsx', null, null, true);

        $validador = Validator::make(['archivo' => $archivo], ['archivo' => [new ZipSeguro()]]);

        $this->assertFalse($validador->fails(), 'Un xlsx pequeño y normal debe pasar');

        @unlink($ruta);
    }

    public function test_las_vistas_no_escupen_html_sin_escapar(): void
    {
        $infractoras = [];

        foreach ($this->vistas() as $ruta) {
            $contenido = file_get_contents($ruta);

            if (str_contains($contenido, '{!!')) {
                $infractoras[] = basename($ruta);
            }
        }

        $this->assertSame([], $infractoras, 'Estas vistas imprimen HTML sin escapar: '.implode(', ', $infractoras));
    }

    public function test_no_hay_sql_crudo_con_cadenas_concatenadas(): void
    {
        $infractoras = [];

        $carpetas = [app_path(), database_path(), base_path('routes')];

        foreach ($carpetas as $carpeta) {
            if (! is_dir($carpeta)) {
                continue;
            }

            $archivos = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($carpeta));

            foreach ($archivos as $archivo) {
                if (! $archivo->isFile() || $archivo->getExtension() !== 'php') {
                    continue;
                }

                $codigo = file_get_contents($archivo->getPathname());

                // DB::raw con una variable concatenada dentro es inyección.
                if (preg_match('/DB::raw\s*\(\s*["\'].*\.\s*\$/', $codigo)) {
                    $infractoras[] = $archivo->getFilename();
                }

                // whereRaw con interpolación directa.
                if (preg_match('/whereRaw\s*\(\s*["\'][^"\']*\$/', $codigo)) {
                    $infractoras[] = $archivo->getFilename();
                }
            }
        }

        $this->assertSame([], array_values(array_unique($infractoras)), 'SQL crudo con interpolación: '.implode(', ', $infractoras));
    }

    public function test_la_subida_tiene_limite_de_frecuencia(): void
    {
        $ruta = collect(app('router')->getRoutes())->first(
            fn ($r) => $r->getName() === 'envios.subir'
        );

        $this->assertNotNull($ruta);
        $this->assertContains('throttle:10,60', $ruta->gatherMiddleware());
    }

    public function test_la_descarga_de_paquetes_tiene_limite(): void
    {
        $ruta = collect(app('router')->getRoutes())->first(
            fn ($r) => $r->getName() === 'envios.paquete'
        );

        $this->assertNotNull($ruta);
        $this->assertContains('throttle:10,1', $ruta->gatherMiddleware());
    }

    public function test_el_login_limita_los_intentos(): void
    {
        // Fortify aplica el límite por su propio controlador, no por middleware
        // de ruta: se comprueba el comportamiento, no la configuración.
        $usuario = User::factory()->create([
            'activo'   => true,
            'email'    => 'limite@ssosorno.cl',
            'password' => \Illuminate\Support\Facades\Hash::make('ContraseñaLarga123'),
        ]);

        for ($i = 0; $i < 6; $i++) {
            $this->post('/login', ['email' => $usuario->email, 'password' => 'mal']);
        }

        $respuesta = $this->post('/login', ['email' => $usuario->email, 'password' => 'mal']);

        $respuesta->assertSessionHasErrors();
        $this->assertMatchesRegularExpression(
            '/Demasiados|Too many/i',
            session('errors')->first('email') ?? ''
        );
    }

    /** @return list<string> */
    private function vistas(): array
    {
        $vistas = array_merge(
            glob(resource_path('views/*.blade.php')),
            glob(resource_path('views/**/*.blade.php'))
        );

        return array_values(array_unique($vistas));
    }
}
