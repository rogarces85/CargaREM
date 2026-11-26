<?php

namespace Tests\Feature;

use App\Models\Envio;
use App\Models\Establecimiento;
use App\Models\Periodo;
use App\Models\User;
use App\Services\PanelService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Panel con indicadores (RF-048, D-048).
 *
 * Lo que se fija: cada rol ve su alcance y no el ajeno, las cifras cuadran con
 * los datos, y el dato sigue estando en texto cuando el gráfico no se pinta.
 */
class PanelTest extends TestCase
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

        Periodo::create(['anio' => (int) now()->year, 'mes' => 9, 'estado' => Periodo::ABIERTO]);

        $this->admin = User::factory()->create(['activo' => true]);
        $this->admin->assignRole('admin');

        $this->encargado = User::factory()->create(['activo' => true]);
        $this->encargado->assignRole('encargado');
        $this->encargado->establecimientos()->attach($this->hospital->id);

        $this->revisor = User::factory()->create(['activo' => true]);
        $this->revisor->assignRole('revisor');
        $this->revisor->establecimientos()->attach($this->hospital->id);
    }

    private function envio(Establecimiento $establecimiento, string $estado = Envio::RECIBIDO, bool $fuera = false): Envio
    {
        return Envio::create([
            'establecimiento_id' => $establecimiento->id,
            'anio' => (int) now()->year, 'mes' => 9, 'estado' => $estado,
            'fuera_de_plazo' => $fuera,
            'enviado_por' => $this->encargado->id,
            'fecha_envio' => now()->subDays(2),
            'fecha_revision' => now()->subDay(),
        ]);
    }

    public function test_el_panel_carga_para_cada_rol(): void
    {
        foreach ([$this->encargado, $this->revisor, $this->admin] as $usuario) {
            $this->actingAs($usuario)->get('/panel')->assertOk();
        }
    }

    public function test_el_encargado_solo_cuenta_sus_envios(): void
    {
        $this->envio($this->hospital, Envio::APROBADO);
        $this->envio($this->posta, Envio::APROBADO);

        $panel = app(PanelService::class)->para($this->encargado);

        $this->assertSame('encargado', $panel['rol']);

        $aprobados = collect($panel['tarjetas'])->firstWhere('rotulo', 'Aprobados');
        $this->assertSame(1, $aprobados['valor']);
    }

    public function test_el_revisor_cuenta_solo_sus_establecimientos(): void
    {
        $this->envio($this->hospital, Envio::EN_REVISION);
        $this->envio($this->posta, Envio::EN_REVISION);

        $panel = app(PanelService::class)->para($this->revisor);

        $this->assertSame('revisor', $panel['rol']);

        $pendientes = collect($panel['tarjetas'])->firstWhere('rotulo', 'Pendientes de revisión');
        $this->assertSame(1, $pendientes['valor']);
    }

    public function test_el_administrador_ve_el_panorama_completo(): void
    {
        $this->envio($this->hospital, Envio::APROBADO);
        $this->envio($this->posta, Envio::EN_REVISION, true);

        $panel = app(PanelService::class)->para($this->admin);

        $this->assertSame('admin', $panel['rol']);

        $aprobados = collect($panel['tarjetas'])->firstWhere('rotulo', 'Aprobados');
        $this->assertSame(1, $aprobados['valor']);

        $fuera = collect($panel['tarjetas'])->firstWhere('rotulo', 'Fuera de plazo');
        $this->assertSame(1, $fuera['valor']);
    }

    public function test_el_panel_avisa_cuando_no_hay_asignacion(): void
    {
        $sinReparto = User::factory()->create(['activo' => true]);
        $sinReparto->assignRole('revisor');

        $panel = app(PanelService::class)->para($sinReparto);

        $this->assertNotEmpty($panel['avisos']);
        $this->assertStringContainsString('No tiene establecimientos asignados', $panel['avisos'][0]);
    }

    public function test_las_cifras_siguen_en_texto_sin_javascript(): void
    {
        $this->envio($this->hospital, Envio::APROBADO);

        $respuesta = $this->actingAs($this->encargado)->get('/panel');

        $respuesta->assertOk();

        // El resumen viaja como texto en la tarjeta del gráfico y en la tabla
        // de respaldo; el gráfico es un extra, no la única fuente.
        $respuesta->assertSee('Ver los datos en tabla');
        $respuesta->assertSee('Aprobado');
    }

    public function test_el_administrador_ve_el_aviso_de_establecimientos_sin_revisor(): void
    {
        $panel = app(PanelService::class)->para($this->admin);

        $this->assertNotEmpty($panel['avisos']);
        $this->assertStringContainsString('sin revisor asignado', implode(' ', $panel['avisos']));
    }

    public function test_los_graficos_llevan_su_configuracion_para_el_cliente(): void
    {
        $this->envio($this->hospital, Envio::APROBADO);

        $this->actingAs($this->encargado)
            ->get('/panel')
            ->assertOk()
            ->assertSee('data-chart', false)
            ->assertSee('data-config', false);
    }
}
