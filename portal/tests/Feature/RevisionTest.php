<?php

namespace Tests\Feature;

use App\Models\CatalogoSnapshot;
use App\Models\Envio;
use App\Models\Establecimiento;
use App\Models\Periodo;
use App\Models\Serie;
use App\Models\Servicio;
use App\Models\User;
use App\Services\EnvioService;
use App\Services\EstadoEnvioService;
use App\Services\ObservacionService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Bandeja de revisión (T-F3-03, T-F3-04).
 */
class RevisionTest extends TestCase
{
    use RefreshDatabase;

    private Establecimiento $establecimiento;
    private User $encargado;
    private User $revisor;
    private Serie $serie;
    private Envio $envio;
    private CatalogoSnapshot $snapshot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Storage::fake('local');

        $servicio = Servicio::create(['codigo' => '23', 'nombre' => 'Osorno']);
        $this->establecimiento = Establecimiento::create([
            'codigo' => '123100', 'nombre' => 'Hospital Base', 'servicio_id' => $servicio->id,
        ]);
        Periodo::create(['anio' => 2026, 'mes' => 9, 'estado' => Periodo::ABIERTO]);
        $this->snapshot = CatalogoSnapshot::create(['origen' => 'prueba', 'activo' => true]);

        $this->encargado = User::factory()->create(['activo' => true]);
        $this->encargado->assignRole('encargado');
        $this->encargado->establecimientos()->attach($this->establecimiento->id);

        $this->revisor = User::factory()->create(['activo' => true, 'servicio_id' => $servicio->id]);
        $this->revisor->assignRole('revisor');

        $this->serie = Serie::create(['codigo' => 'A', 'nombre' => 'SERIE A']);
        $this->establecimiento->series()->attach($this->serie->id, ['obligatoria' => true]);

        $this->actingAs($this->encargado);
        $this->envio = app(EnvioService::class)->crear($this->establecimiento, 2026, 9, $this->encargado);
        $this->envio->update(['estado' => Envio::EN_REVISION, 'fecha_envio' => now()->subDays(5)]);
    }

    public function test_el_revisor_ve_su_bandeja_con_los_envios_en_revision(): void
    {
        $this->actingAs($this->revisor)
            ->get('/revision')
            ->assertOk()
            ->assertSee('Bandeja de revisión')
            ->assertSee('123100');
    }

    public function test_un_encargado_no_entra_a_la_bandeja(): void
    {
        $this->actingAs($this->encargado)
            ->get('/revision')
            ->assertForbidden();
    }

    public function test_filtra_por_estado(): void
    {
        // Se comprueba sobre el paginador: el código aparece igual en el
        // desplegable de establecimientos del formulario de filtros.
        $this->actingAs($this->revisor)
            ->get('/revision?estado=aprobado')
            ->assertOk()
            ->assertViewHas('envios', fn ($pagina) => $pagina->total() === 0);
    }

    public function test_filtra_por_antiguedad(): void
    {
        $this->actingAs($this->revisor)
            ->get('/revision?dias=10')
            ->assertOk()
            ->assertViewHas('envios', fn ($pagina) => $pagina->total() === 0);
    }

    public function test_observar_deja_la_observacion_y_cambia_el_estado(): void
    {
        $this->actingAs($this->revisor)
            ->post("/revision/{$this->envio->id}/observar", [
                'texto' => 'La hoja A01 viene sin la columna 12.',
            ])
            ->assertRedirect(route('revision.index'));

        $this->assertSame(Envio::OBSERVADO, $this->envio->fresh()->estado);
        $this->assertDatabaseHas('observaciones', [
            'envio_id' => $this->envio->id,
            'texto'    => 'La hoja A01 viene sin la columna 12.',
            'resuelta' => false,
        ]);
    }

    public function test_observar_exige_texto(): void
    {
        $this->actingAs($this->revisor)
            ->post("/revision/{$this->envio->id}/observar", ['texto' => ''])
            ->assertSessionHasErrors('texto');
    }

    /** Una serie exigida con su archivo vigente y válido: el envío está completo. */
    private function completar(): void
    {
        $this->envio->archivos()->create([
            'serie_id' => $this->serie->id, 'version' => 1, 'es_vigente' => true,
            'nombre_original' => '123100A09.xlsx', 'ruta' => 'envios/x.xlsx', 'disco' => 'local',
            'sha256' => str_repeat('c', 64), 'tamano_bytes' => 100, 'mime' => 'application/vnd.ms-excel',
            'estado' => \App\Models\EnvioArchivo::VALIDO, 'snapshot_id' => $this->snapshot->id,
            'subido_por' => $this->encargado->id,
        ]);
    }

    public function test_aprobar_sin_observaciones_funciona(): void
    {
        $this->completar();

        $this->actingAs($this->revisor)
            ->post("/revision/{$this->envio->id}/aprobar")
            ->assertRedirect(route('revision.index'));

        $this->assertSame(Envio::APROBADO, $this->envio->fresh()->estado);
    }

    public function test_aprobar_con_observaciones_abiertas_se_rechaza(): void
    {
        app(ObservacionService::class)->registrar($this->envio, $this->revisor, 'Falta la columna 12');

        $this->actingAs($this->revisor)
            ->post("/revision/{$this->envio->id}/aprobar")
            ->assertSessionHasErrors('aprobar');

        $this->assertSame(Envio::EN_REVISION, $this->envio->fresh()->estado);
    }

    public function test_un_encargado_no_puede_aprobar(): void
    {
        $this->actingAs($this->encargado)
            ->post("/revision/{$this->envio->id}/aprobar")
            ->assertForbidden();
    }

    public function test_confirmar_la_carga_despues_de_aprobar(): void
    {
        app(EstadoEnvioService::class)->transicionar($this->envio, Envio::APROBADO, $this->revisor);

        $this->actingAs($this->revisor)
            ->post("/revision/{$this->envio->id}/cargar")
            ->assertRedirect(route('revision.index'));

        $this->assertSame(Envio::CARGADO, $this->envio->fresh()->estado);
    }

    public function test_no_se_confirma_la_carga_sin_aprobar(): void
    {
        $this->actingAs($this->revisor)
            ->post("/revision/{$this->envio->id}/cargar")
            ->assertForbidden();
    }
}
