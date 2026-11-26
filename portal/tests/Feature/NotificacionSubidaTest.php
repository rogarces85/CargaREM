<?php

namespace Tests\Feature;

use App\Models\Envio;
use App\Models\Establecimiento;
use App\Models\Periodo;
use App\Models\Serie;
use App\Models\User;
use App\Services\EnvioService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Avisos de subida al Equipo REM (RF-041, D-050).
 *
 * Lo que la v3 agrega: el revisor se entera de que hay archivos nuevos sin
 * tener que entrar a mirar, el aviso distingue una serie suelta de un envío
 * completo, y seis subidas seguidas no producen seis avisos.
 */
class NotificacionSubidaTest extends TestCase
{
    use RefreshDatabase;

    private Establecimiento $establecimiento;
    private User $encargado;
    private User $revisor;
    private Envio $envio;
    private Serie $serieA;
    private Serie $serieBS;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Storage::fake('local');

        $this->establecimiento = Establecimiento::create([
            'codigo' => '123100', 'nombre' => 'Hospital Base', 'comuna' => 'Osorno',
        ]);
        Periodo::create(['anio' => 2026, 'mes' => 9, 'estado' => Periodo::ABIERTO]);

        $this->serieA = Serie::create(['codigo' => 'A', 'nombre' => 'SERIE A', 'orden' => 1]);
        $this->serieBS = Serie::create(['codigo' => 'BS', 'nombre' => 'SERIE BS', 'orden' => 2]);

        $this->establecimiento->series()->attach($this->serieA->id, ['obligatoria' => true]);
        $this->establecimiento->series()->attach($this->serieBS->id, ['obligatoria' => true]);

        $this->encargado = User::factory()->create(['activo' => true]);
        $this->encargado->assignRole('encargado');
        $this->encargado->establecimientos()->attach($this->establecimiento->id);

        $this->revisor = User::factory()->create(['activo' => true]);
        $this->revisor->assignRole('revisor');
        $this->revisor->establecimientos()->attach($this->establecimiento->id);

        $this->actingAs($this->encargado);
        $this->envio = app(EnvioService::class)->crear($this->establecimiento, 2026, 9, $this->encargado);
    }

    private function subir(Serie $serie, int $kb = 4): void
    {
        // Cada archivo necesita contenido distinto: el mismo hash se rechaza
        // como duplicado (ENV-029) y el test mediría otra cosa.
        $contenido = "PK\x03\x04".str_repeat(chr(65 + $serie->id), max(1, $kb * 1024));

        app(EnvioService::class)->subirVersion(
            $this->envio->fresh(),
            $serie,
            UploadedFile::fake()->createWithContent("123100{$serie->codigo}09.xlsx", $contenido),
            $this->encargado
        );
    }

    private function avisoDeSubida(): ?object
    {
        return $this->revisor->notifications()
            ->where('data->tipo', 'subida')
            ->first();
    }

    public function test_una_serie_suelta_avisa_al_revisor_con_el_conteo(): void
    {
        $this->subir($this->serieA);

        $aviso = $this->avisoDeSubida();

        $this->assertNotNull($aviso, 'El Equipo REM debe enterarse de la subida.');
        $this->assertSame('subida_serie', $aviso->data['evento']);
        $this->assertSame('serie', $aviso->data['alcance']);
        $this->assertSame(['A'], $aviso->data['series']);
        $this->assertSame(1, $aviso->data['presentes']);
        $this->assertSame(2, $aviso->data['exigidas']);
        $this->assertStringContainsString('subió la serie A', $aviso->data['mensaje']);
        $this->assertStringContainsString('1 de 2', $aviso->data['mensaje']);
    }

    public function test_completar_las_series_avisa_envio_completo(): void
    {
        $this->subir($this->serieA);
        $this->subir($this->serieBS);

        $avisos = $this->revisor->notifications()->where('data->tipo', 'subida')->get();

        // RF-041.3: un solo aviso, actualizado, no dos.
        $this->assertCount(1, $avisos);

        $aviso = $avisos->first();
        $this->assertSame('subida_completa', $aviso->data['evento']);
        $this->assertSame('completo', $aviso->data['alcance']);
        $this->assertSame(2, $aviso->data['presentes']);
        $this->assertStringContainsString('Envío completo', $aviso->data['titulo']);
    }

    public function test_seis_subidas_seguidas_no_producen_seis_avisos(): void
    {
        for ($version = 1; $version <= 6; $version++) {
            $this->subir($this->serieA, 4 + $version);
        }

        $this->assertCount(1, $this->revisor->notifications()->where('data->tipo', 'subida')->get());
    }

    public function test_quien_sube_no_recibe_su_propio_aviso(): void
    {
        $encargadoRevisor = User::factory()->create(['activo' => true]);
        $encargadoRevisor->assignRole('encargado');
        $encargadoRevisor->establecimientos()->attach($this->establecimiento->id);

        $this->actingAs($encargadoRevisor);
        $this->subir($this->serieA);

        $this->assertSame(0, $encargadoRevisor->notifications()->count());
    }

    public function test_un_establecimiento_sin_revisor_avisa_al_administrador(): void
    {
        $this->revisor->establecimientos()->sync([]);

        $admin = User::factory()->create(['activo' => true]);
        $admin->assignRole('admin');

        $this->subir($this->serieA);

        $this->assertTrue(
            $admin->notifications()->where('data->tipo', 'subida')->exists(),
            'Sin revisor asignado, el aviso va al administrador.'
        );
        $this->assertSame(0, $this->revisor->notifications()->count());
    }

    public function test_el_revisor_de_otro_establecimiento_no_recibe_el_aviso(): void
    {
        $otroEstablecimiento = Establecimiento::create([
            'codigo' => '123999', 'nombre' => 'Otro', 'comuna' => 'Osorno',
        ]);

        $otroRevisor = User::factory()->create(['activo' => true]);
        $otroRevisor->assignRole('revisor');
        $otroRevisor->establecimientos()->attach($otroEstablecimiento->id);

        $this->subir($this->serieA);

        $this->assertSame(0, $otroRevisor->notifications()->count());
    }

    public function test_el_aviso_de_subida_lleva_al_detalle_del_envio(): void
    {
        $this->subir($this->serieA);

        $aviso = $this->avisoDeSubida();

        $this->actingAs($this->revisor)
            ->post("/notificaciones/{$aviso->id}/leer")
            ->assertRedirect(route('envios.show', $this->envio));
    }
}
