<?php

namespace Tests\Feature;

use App\Exceptions\EnvioInvalidoException;
use App\Models\Envio;
use App\Models\EnvioArchivo;
use App\Models\Establecimiento;
use App\Models\Periodo;
use App\Models\Serie;
use App\Models\User;
use App\Services\EnvioService;
use App\Services\EstadoEnvioService;
use App\Services\SolicitudReenvioService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Reapertura y solicitud de nueva subida (RF-044, D-043).
 *
 * La v2 decía que `aprobado` era final. La v3 lo abre de forma controlada:
 * con permiso, con motivo escrito y con el rastro en el historial. La máquina
 * de estados no gana una arista libre: la reapertura es un método con guardas.
 */
class ReaperturaTest extends TestCase
{
    use RefreshDatabase;

    private Establecimiento $establecimiento;
    private User $encargado;
    private User $revisor;
    private User $admin;
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

        $this->admin = User::factory()->create(['activo' => true]);
        $this->admin->assignRole('admin');
    }

    private function enviado(): Envio
    {
        $this->actingAs($this->encargado);

        $envio = app(EnvioService::class)->crear($this->establecimiento, 2026, 9, $this->encargado);

        foreach ([$this->serieA, $this->serieBS] as $serie) {
            $envio->archivos()->create([
                'serie_id' => $serie->id, 'version' => 1, 'es_vigente' => true,
                'nombre_original' => "123100{$serie->codigo}09.xlsx", 'ruta' => 'envios/x.xlsx',
                'disco' => 'local', 'sha256' => hash('sha256', $serie->codigo),
                'tamano_bytes' => 100, 'mime' => 'application/vnd.ms-excel',
                'estado' => EnvioArchivo::VALIDO, 'subido_por' => $this->encargado->id,
            ]);
        }

        return $envio->fresh();
    }

    private function aprobado(): Envio
    {
        $envio = $this->enviado();
        $envio->update(['estado' => Envio::APROBADO, 'fecha_aprobacion' => now()]);

        return $envio->fresh();
    }

    public function test_reabrir_devuelve_el_envio_a_observado_y_deja_rastro(): void
    {
        $envio = $this->aprobado();

        $this->actingAs($this->revisor)
            ->post("/revision/{$envio->id}/reabrir", ['motivo' => 'La serie BS trae datos de otro mes.'])
            ->assertRedirect(route('envios.show', $envio));

        $this->assertSame(Envio::OBSERVADO, $envio->fresh()->estado);
        $this->assertDatabaseHas('envio_transiciones', [
            'envio_id'        => $envio->id,
            'estado_anterior' => Envio::APROBADO,
            'estado_nuevo'    => Envio::OBSERVADO,
            'actor_id'        => $this->revisor->id,
        ]);
        $this->assertDatabaseHas('auditoria', ['evento' => 'envio.reabierto']);
        $this->assertSame(
            EnvioArchivo::OBSERVADO,
            $envio->archivosVigentes()->first()->estado
        );
    }

    public function test_reabrir_avisa_al_encargado(): void
    {
        $envio = $this->aprobado();

        $this->actingAs($this->revisor)
            ->post("/revision/{$envio->id}/reabrir", ['motivo' => 'Falta la hoja PERSONAS.']);

        $this->assertTrue(
            $this->encargado->notifications()->where('data->evento', 'reabierto')->exists()
        );
    }

    public function test_reabrir_exige_motivo(): void
    {
        $envio = $this->aprobado();

        $this->actingAs($this->revisor)
            ->post("/revision/{$envio->id}/reabrir", ['motivo' => 'mal'])
            ->assertSessionHasErrors('motivo');

        $this->assertSame(Envio::APROBADO, $envio->fresh()->estado);
    }

    public function test_no_se_reabre_un_envio_que_no_esta_aprobado(): void
    {
        $envio = $this->enviado();
        $envio->update(['estado' => Envio::EN_REVISION]);

        // La policy lo niega antes de llegar al servicio: no hay botón que
        // ofrecer y la URL directa tampoco entra.
        $this->actingAs($this->revisor)
            ->post("/revision/{$envio->id}/reabrir", ['motivo' => 'Un motivo suficientemente largo.'])
            ->assertForbidden();

        $this->assertSame(Envio::EN_REVISION, $envio->fresh()->estado);
    }

    public function test_el_servicio_rechaza_reabrir_algo_no_aprobado(): void
    {
        $envio = $this->enviado();
        $envio->update(['estado' => Envio::EN_REVISION]);

        $this->expectException(EnvioInvalidoException::class);

        app(EstadoEnvioService::class)->reabrir($envio->fresh(), $this->revisor, 'Un motivo suficientemente largo.');
    }

    public function test_un_revisor_de_otro_establecimiento_no_reabre(): void
    {
        $envio = $this->aprobado();

        $ajeno = User::factory()->create(['activo' => true]);
        $ajeno->assignRole('revisor');

        $this->actingAs($ajeno)
            ->post("/revision/{$envio->id}/reabrir", ['motivo' => 'Un motivo suficientemente largo.'])
            ->assertForbidden();
    }

    public function test_la_transicion_directa_desde_aprobado_sigue_prohibida(): void
    {
        $envio = $this->aprobado();

        // La reapertura no se agregó a PERMITIDAS: si alguien intenta la
        // transición sin pasar por `reabrir()`, el servicio la rechaza.
        $this->expectException(EnvioInvalidoException::class);

        app(EstadoEnvioService::class)->transicionar($envio, Envio::OBSERVADO, $this->revisor, 'atajo');
    }

    public function test_pedir_nueva_subida_de_una_serie_crea_su_observacion(): void
    {
        $envio = $this->enviado();
        $envio->update(['estado' => Envio::EN_REVISION]);

        $this->actingAs($this->revisor)
            ->post('/revision/reenvio', [
                'alcance'  => 'serie',
                'envio_id' => $envio->id,
                'serie_id' => $this->serieBS->id,
                'motivo'   => 'Los totales de la serie BS no cuadran con el resumen.',
            ])
            ->assertRedirect(route('revision.index'));

        $this->assertDatabaseHas('observaciones', [
            'envio_id' => $envio->id,
            'autor_id' => $this->revisor->id,
        ]);

        $observacion = $envio->observaciones()->first();
        $this->assertNotNull($observacion->envio_archivo_id, 'La observación se ata al archivo de la serie.');
        $this->assertStringContainsString('BS', $observacion->texto);
    }

    public function test_pedir_nueva_subida_de_todo_el_envio_observa_cada_serie(): void
    {
        $envio = $this->enviado();
        $envio->update(['estado' => Envio::EN_REVISION]);

        $resultado = app(SolicitudReenvioService::class)->solicitar(
            $this->revisor,
            SolicitudReenvioService::ALCANCE_ENVIO,
            'Todo el período está mal cargado.',
            $envio,
        );

        $this->assertSame(2, $resultado['observaciones']);
        $this->assertSame(2, $envio->observaciones()->count());
    }

    public function test_pedir_nueva_subida_de_un_envio_aprobado_lo_reabre(): void
    {
        $envio = $this->aprobado();

        $resultado = app(SolicitudReenvioService::class)->solicitar(
            $this->revisor,
            SolicitudReenvioService::ALCANCE_ENVIO,
            'Hay un error de fondo en el período.',
            $envio,
        );

        $this->assertSame(1, $resultado['reabiertos']);
        $this->assertSame(Envio::OBSERVADO, $envio->fresh()->estado);
    }

    public function test_pedir_nueva_subida_a_todos_los_asignados_recorre_el_periodo(): void
    {
        $posta = Establecimiento::create(['codigo' => '123105', 'nombre' => 'Posta', 'comuna' => 'San Pablo']);
        $posta->series()->attach($this->serieA->id, ['obligatoria' => true]);
        $this->revisor->establecimientos()->attach($posta->id);

        $primero = $this->enviado();
        $primero->update(['estado' => Envio::EN_REVISION]);

        // Segundo establecimiento del mismo revisor.
        $segundo = Envio::create([
            'establecimiento_id' => $posta->id, 'anio' => 2026, 'mes' => 9,
            'estado' => Envio::EN_REVISION, 'enviado_por' => $this->encargado->id,
        ]);
        $segundo->archivos()->create([
            'serie_id' => $this->serieA->id, 'version' => 1, 'es_vigente' => true,
            'nombre_original' => 'x.xlsx', 'ruta' => 'envios/y.xlsx', 'disco' => 'local',
            'sha256' => str_repeat('c', 64), 'tamano_bytes' => 10, 'mime' => 'application/vnd.ms-excel',
            'estado' => EnvioArchivo::VALIDO, 'subido_por' => $this->encargado->id,
        ]);

        $resultado = app(SolicitudReenvioService::class)->solicitar(
            $this->revisor,
            SolicitudReenvioService::ALCANCE_ASIGNADOS,
            'El mes completo viene con el formato anterior.',
            null,
            null,
            2026,
            9,
        );

        $this->assertSame(2, $resultado['envios']);
        $this->assertGreaterThanOrEqual(2, $resultado['observaciones']);
    }

    public function test_el_encargado_no_puede_pedir_una_nueva_subida(): void
    {
        $envio = $this->enviado();

        $this->actingAs($this->encargado)
            ->post('/revision/reenvio', [
                'alcance'  => 'envio',
                'envio_id' => $envio->id,
                'motivo'   => 'Un motivo suficientemente largo.',
            ])
            ->assertForbidden();
    }

    public function test_el_formulario_solo_lista_envios_del_alcance(): void
    {
        $envio = $this->enviado();

        $ajeno = Establecimiento::create(['codigo' => '123999', 'nombre' => 'Ajeno', 'comuna' => 'Osorno']);
        Envio::create([
            'establecimiento_id' => $ajeno->id, 'anio' => 2026, 'mes' => 9,
            'estado' => Envio::EN_REVISION, 'enviado_por' => $this->encargado->id,
        ]);

        $this->actingAs($this->revisor)
            ->get('/revision/reenvio?anio=2026&mes=9')
            ->assertOk()
            ->assertSee('123100')
            ->assertDontSee('123999');
    }
}
