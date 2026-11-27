<?php

namespace Tests\Feature;

use App\Models\Envio;
use App\Models\Establecimiento;
use App\Models\Periodo;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Módulo Envíos: alcance, filtros y orden (RF-047, D-049).
 *
 * El portal nace con un año y va a acumular varios; la pantalla tiene que
 * seguir siendo legible. Lo que se fija aquí: el filtro filtra, el orden ordena,
 * la paginación conserva el filtro y nadie ve lo que no le toca.
 */
class EnviosFiltrosTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $encargado;
    private User $revisor;
    private Establecimiento $hospital;
    private Establecimiento $posta;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->hospital = Establecimiento::create(['codigo' => '123100', 'nombre' => 'Hospital Base', 'comuna' => 'Osorno']);
        $this->posta = Establecimiento::create(['codigo' => '123105', 'nombre' => 'Posta San Pablo', 'comuna' => 'San Pablo']);

        foreach ([2025, 2026] as $anio) {
            foreach ([1, 9] as $mes) {
                Periodo::create(['anio' => $anio, 'mes' => $mes, 'estado' => Periodo::ABIERTO]);
            }
        }

        $this->admin = User::factory()->create(['activo' => true]);
        $this->admin->assignRole('admin');

        $this->encargado = User::factory()->create(['activo' => true]);
        $this->encargado->assignRole('encargado');
        $this->encargado->establecimientos()->attach([$this->hospital->id, $this->posta->id]);

        $this->revisor = User::factory()->create(['activo' => true]);
        $this->revisor->assignRole('revisor');
        $this->revisor->establecimientos()->attach($this->hospital->id);
    }

    private function envio(Establecimiento $establecimiento, int $anio, int $mes, string $estado = Envio::RECIBIDO, bool $fuera = false): Envio
    {
        return Envio::create([
            'establecimiento_id' => $establecimiento->id,
            'anio' => $anio, 'mes' => $mes, 'estado' => $estado,
            'fuera_de_plazo' => $fuera,
            'enviado_por' => $this->encargado->id,
        ]);
    }

    public function test_el_filtro_por_anio_deja_solo_ese_anio(): void
    {
        $this->envio($this->hospital, 2025, 1);
        $this->envio($this->hospital, 2026, 1);

        $this->actingAs($this->encargado)
            ->get('/envios?anio=2026')
            ->assertOk()
            ->assertViewHas('envios', fn ($pagina) => $pagina->total() === 1);
    }

    public function test_el_filtro_por_comuna(): void
    {
        $this->envio($this->hospital, 2026, 9);
        $this->envio($this->posta, 2026, 9);

        $this->actingAs($this->encargado)
            ->get('/envios?comuna=San+Pablo')
            ->assertOk()
            ->assertViewHas('envios', fn ($pagina) => $pagina->total() === 1
                && $pagina->first()->establecimiento->comuna === 'San Pablo');
    }

    public function test_el_filtro_por_mes_deja_solo_ese_mes(): void
    {
        $this->envio($this->hospital, 2026, 1);
        $this->envio($this->hospital, 2026, 9);

        $this->actingAs($this->encargado)
            ->get('/envios?anio=2026&mes=9')
            ->assertOk()
            ->assertViewHas('envios', fn ($pagina) => $pagina->total() === 1
                && (int) $pagina->first()->mes === 9);
    }

    public function test_el_filtro_por_establecimiento_deja_solo_ese(): void
    {
        $this->envio($this->hospital, 2026, 9);
        $this->envio($this->posta, 2026, 9);

        $this->actingAs($this->encargado)
            ->get('/envios?establecimiento_id=' . $this->posta->id)
            ->assertOk()
            ->assertViewHas('envios', fn ($pagina) => $pagina->total() === 1
                && $pagina->first()->establecimiento_id === $this->posta->id);
    }

    /**
     * El desplegable de comuna es del alcance: listar el territorio completo
     * entrega al revisor lo que no le asignaron (R-ALC-01, principio 33).
     */
    public function test_el_desplegable_de_comuna_respeta_el_alcance(): void
    {
        $this->actingAs($this->revisor)
            ->get('/envios')
            ->assertOk()
            ->assertViewHas('comunas', fn ($comunas) => $comunas === ['Osorno']);
    }

    public function test_el_revisor_sin_asignacion_no_ve_ninguna_comuna(): void
    {
        $sinAsignacion = User::factory()->create(['activo' => true]);
        $sinAsignacion->assignRole('revisor');

        $this->actingAs($sinAsignacion)
            ->get('/envios')
            ->assertOk()
            ->assertViewHas('comunas', fn ($comunas) => $comunas === []);
    }

    public function test_el_filtro_por_estado(): void
    {
        $this->envio($this->hospital, 2026, 9, Envio::EN_REVISION);
        $this->envio($this->posta, 2026, 9, Envio::RECIBIDO);

        $this->actingAs($this->encargado)
            ->get('/envios?estado=en_revision')
            ->assertOk()
            ->assertViewHas('envios', fn ($pagina) => $pagina->total() === 1);
    }

    public function test_el_filtro_por_fuera_de_plazo(): void
    {
        $this->envio($this->hospital, 2026, 9, Envio::RECIBIDO, true);
        $this->envio($this->posta, 2026, 9, Envio::RECIBIDO, false);

        $this->actingAs($this->encargado)
            ->get('/envios?plazo=fuera')
            ->assertOk()
            ->assertViewHas('envios', fn ($pagina) => $pagina->total() === 1);
    }

    public function test_la_busqueda_por_codigo(): void
    {
        $this->envio($this->hospital, 2026, 9);
        $this->envio($this->posta, 2026, 9);

        $this->actingAs($this->encargado)
            ->get('/envios?q=123105')
            ->assertOk()
            ->assertViewHas('envios', fn ($pagina) => $pagina->total() === 1);
    }

    public function test_el_orden_por_comuna_agrupa_por_territorio(): void
    {
        $this->envio($this->hospital, 2026, 9);
        $this->envio($this->posta, 2026, 9);

        $this->actingAs($this->encargado)
            ->get('/envios?orden=comuna')
            ->assertOk()
            ->assertViewHas('envios', function ($pagina) {
                $comunas = $pagina->getCollection()->pluck('establecimiento.comuna')->all();

                return $comunas === ['Osorno', 'San Pablo'];
            });
    }

    public function test_el_orden_por_periodo_pone_lo_mas_reciente_primero(): void
    {
        $this->envio($this->hospital, 2025, 1);
        $this->envio($this->hospital, 2026, 9);
        $this->envio($this->hospital, 2026, 1);

        $this->actingAs($this->encargado)
            ->get('/envios')
            ->assertOk()
            ->assertViewHas('envios', function ($pagina) {
                $periodos = $pagina->getCollection()->map(fn ($e) => $e->anio * 100 + $e->mes)->all();

                return $periodos === [202609, 202601, 202501];
            });
    }

    public function test_la_paginacion_conserva_los_filtros(): void
    {
        for ($i = 1; $i <= 30; $i++) {
            $establecimiento = Establecimiento::create([
                'codigo' => '9'.str_pad((string) $i, 5, '0', STR_PAD_LEFT),
                'nombre' => "Establecimiento {$i}",
                'comuna' => 'Osorno',
            ]);

            // Sin asignación, el encargado no los vería y no habría segunda
            // página que paginar.
            $this->encargado->establecimientos()->attach($establecimiento->id);

            $this->envio($establecimiento, 2026, 9);
        }

        $respuesta = $this->actingAs($this->encargado)
            ->get('/envios?anio=2026&comuna=Osorno&orden=comuna');

        $respuesta->assertOk();

        // El paginador arrastra los parámetros en los enlaces (RF-047.3).
        $respuesta->assertSee('anio=2026', false);
        $respuesta->assertSee('comuna=Osorno', false);
    }

    public function test_el_revisor_no_ve_envios_de_establecimientos_ajenos(): void
    {
        $this->envio($this->hospital, 2026, 9);
        $this->envio($this->posta, 2026, 9);

        $this->actingAs($this->revisor)
            ->get('/envios')
            ->assertOk()
            ->assertSee('123100')
            ->assertDontSee('123105');
    }

    public function test_el_encargado_no_ve_un_selector_con_establecimientos_ajenos(): void
    {
        $ajeno = Establecimiento::create(['codigo' => '999999', 'nombre' => 'Ajeno', 'comuna' => 'Osorno']);

        $this->actingAs($this->encargado)
            ->get('/envios')
            ->assertOk()
            ->assertViewHas('establecimientos', fn ($lista) => ! $lista->contains('id', $ajeno->id));
    }

    public function test_los_anios_salen_de_los_datos(): void
    {
        $this->envio($this->hospital, 2025, 1);

        $this->actingAs($this->encargado)
            ->get('/envios')
            ->assertOk()
            ->assertViewHas('anios', fn ($anios) => in_array(2025, $anios, true));
    }

    public function test_el_administrador_ve_todos_los_envios(): void
    {
        $this->envio($this->hospital, 2026, 9);
        $this->envio($this->posta, 2026, 9);

        $this->actingAs($this->admin)
            ->get('/envios')
            ->assertOk()
            ->assertViewHas('envios', fn ($pagina) => $pagina->total() === 2);
    }

    public function test_un_filtro_sin_resultados_muestra_el_aviso(): void
    {
        $this->actingAs($this->encargado)
            ->get('/envios?anio=2019')
            ->assertOk()
            ->assertSee('No hay envíos con ese filtro');
    }
}
