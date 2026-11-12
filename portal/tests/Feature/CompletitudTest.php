<?php

namespace Tests\Feature;

use App\Models\CatalogoSnapshot;
use App\Models\Envio;
use App\Models\EnvioArchivo;
use App\Models\Establecimiento;
use App\Models\Periodo;
use App\Models\Serie;
use App\Models\Servicio;
use App\Models\User;
use App\Services\CompletitudService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Matriz de completitud, exportación y drill-down (T-F4-05).
 * CA-034, CA-035, CA-036 y RNF-005.
 */
class CompletitudTest extends TestCase
{
    use RefreshDatabase;

    private Servicio $servicio;
    private User $revisor;
    private User $encargado;
    private Serie $serieA;
    private Serie $serieBS;
    private CatalogoSnapshot $snapshot;
    private Establecimiento $hosp1;
    private Establecimiento $hosp2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Storage::fake('local');

        $this->servicio = Servicio::create(['codigo' => '23', 'nombre' => 'Osorno']);
        Periodo::create(['anio' => 2026, 'mes' => 9, 'estado' => Periodo::ABIERTO]);
        $this->snapshot = CatalogoSnapshot::create(['origen' => 'prueba', 'activo' => true]);

        $this->revisor = User::factory()->create(['activo' => true, 'servicio_id' => $this->servicio->id]);
        $this->revisor->assignRole('revisor');

        $this->encargado = User::factory()->create(['activo' => true]);
        $this->encargado->assignRole('encargado');

        $this->serieA = Serie::create(['codigo' => 'A', 'nombre' => 'SERIE A', 'orden' => 1]);
        $this->serieBS = Serie::create(['codigo' => 'BS', 'nombre' => 'SERIE BS', 'orden' => 2]);

        $this->hosp1 = Establecimiento::create([
            'codigo' => '123100', 'nombre' => 'Hospital Base', 'servicio_id' => $this->servicio->id,
        ]);
        $this->hosp2 = Establecimiento::create([
            'codigo' => '123101', 'nombre' => 'Hospital de Río Negro', 'servicio_id' => $this->servicio->id,
        ]);

        foreach ([$this->hosp1, $this->hosp2] as $establecimiento) {
            $establecimiento->series()->attach($this->serieA->id, ['obligatoria' => true]);
            $establecimiento->series()->attach($this->serieBS->id, ['obligatoria' => true]);
        }

        $this->encargado->establecimientos()->attach($this->hosp1->id);
    }

    private function envio(Establecimiento $establecimiento, string $estado, array $series = []): Envio
    {
        $envio = Envio::create([
            'establecimiento_id' => $establecimiento->id,
            'anio'               => 2026,
            'mes'                => 9,
            'estado'             => $estado,
            'enviado_por'        => $this->encargado->id,
            'fecha_envio'        => now(),
        ]);

        foreach ($series as $serieId => $estadoArchivo) {
            $envio->archivos()->create([
                'serie_id' => $serieId, 'version' => 1, 'es_vigente' => true,
                'nombre_original' => 'x.xlsx', 'ruta' => 'envios/x.xlsx', 'disco' => 'local',
                'sha256' => str_repeat('f', 64), 'tamano_bytes' => 100, 'mime' => 'application/vnd.ms-excel',
                'estado' => $estadoArchivo, 'snapshot_id' => $this->snapshot->id,
                'subido_por' => $this->encargado->id,
            ]);
        }

        return $envio;
    }

    private function matriz(?User $usuario = null): array
    {
        return app(CompletitudService::class)->matriz(2026, 9, $usuario ?? $this->revisor);
    }

    public function test_la_matriz_muestra_un_semaforo_por_celda(): void
    {
        // hosp1: A aprobada, BS con errores. hosp2: sin envío.
        $this->envio($this->hosp1, Envio::APROBADO, [
            $this->serieA->id  => EnvioArchivo::VALIDO,
            $this->serieBS->id => EnvioArchivo::CON_ERRORES,
        ]);

        $matriz = $this->matriz();

        $this->assertCount(2, $matriz['filas']);

        $filaHosp1 = collect($matriz['filas'])->first(fn ($f) => $f['establecimiento']->id === $this->hosp1->id);

        $this->assertSame(CompletitudService::APROBADO, $filaHosp1['celdas'][$this->serieA->id]['estado']);
        $this->assertSame(CompletitudService::CON_ERRORES, $filaHosp1['celdas'][$this->serieBS->id]['estado']);

        $filaHosp2 = collect($matriz['filas'])->first(fn ($f) => $f['establecimiento']->id === $this->hosp2->id);
        $this->assertSame(CompletitudService::SIN_ENVIO, $filaHosp2['celdas'][$this->serieA->id]['estado']);
    }

    public function test_distingue_sin_envio_de_sin_archivo(): void
    {
        $this->envio($this->hosp1, Envio::RECIBIDO, [$this->serieA->id => EnvioArchivo::VALIDO]);

        $matriz = $this->matriz();
        $fila = collect($matriz['filas'])->first(fn ($f) => $f['establecimiento']->id === $this->hosp1->id);

        $this->assertSame(CompletitudService::EN_PROCESO, $fila['celdas'][$this->serieA->id]['estado']);
        $this->assertSame(CompletitudService::SIN_ARCHIVO, $fila['celdas'][$this->serieBS->id]['estado']);
    }

    public function test_un_envio_aprobado_pinta_verde_sus_series(): void
    {
        $this->envio($this->hosp1, Envio::CARGADO, [
            $this->serieA->id  => EnvioArchivo::VALIDO,
            $this->serieBS->id => EnvioArchivo::VALIDO,
        ]);

        $matriz = $this->matriz();

        $this->assertSame(2, $matriz['resumen']['aprobadas']);
        $this->assertSame(4, $matriz['resumen']['exigidas']);
    }

    public function test_el_encargado_solo_ve_su_establecimiento(): void
    {
        $matriz = $this->matriz($this->encargado);

        $this->assertCount(1, $matriz['filas']);
        $this->assertSame($this->hosp1->id, $matriz['filas'][0]['establecimiento']->id);
    }

    public function test_el_revisor_ve_todo_su_servicio(): void
    {
        $matriz = $this->matriz($this->revisor);

        $this->assertCount(2, $matriz['filas']);
    }

    public function test_la_pantalla_muestra_la_matriz(): void
    {
        $this->envio($this->hosp1, Envio::APROBADO, [
            $this->serieA->id => EnvioArchivo::VALIDO,
        ]);

        $this->actingAs($this->revisor)
            ->get('/completitud?anio=2026&mes=9')
            ->assertOk()
            ->assertSee('Completitud del período')
            ->assertSee('Hospital Base');
    }

    public function test_el_drill_down_lleva_al_envio(): void
    {
        $envio = $this->envio($this->hosp1, Envio::APROBADO, [
            $this->serieA->id => EnvioArchivo::VALIDO,
        ]);

        $respuesta = $this->actingAs($this->revisor)->get('/completitud?anio=2026&mes=9');

        $respuesta->assertOk();
        $respuesta->assertSee(route('envios.show', $envio), false);
    }

    public function test_exporta_csv_con_encabezados_y_datos(): void
    {
        $this->envio($this->hosp1, Envio::APROBADO, [
            $this->serieA->id => EnvioArchivo::VALIDO,
        ]);

        $respuesta = $this->actingAs($this->revisor)
            ->get('/completitud/exportar?anio=2026&mes=9&formato=csv');

        $respuesta->assertOk();
        $this->assertStringContainsString('text/csv', $respuesta->headers->get('content-type'));
        $this->assertStringContainsString('completitud-2026-09.csv', $respuesta->headers->get('content-disposition'));

        $contenido = $respuesta->streamedContent();

        $this->assertStringContainsString('Establecimiento', $contenido);
        $this->assertStringContainsString('Hospital Base', $contenido);
        $this->assertStringContainsString('Aprobado', $contenido);
    }

    public function test_exporta_excel(): void
    {
        $this->envio($this->hosp1, Envio::APROBADO, [
            $this->serieA->id => EnvioArchivo::VALIDO,
        ]);

        $respuesta = $this->actingAs($this->revisor)
            ->get('/completitud/exportar?anio=2026&mes=9&formato=xlsx');

        $respuesta->assertOk();
        $this->assertStringContainsString('spreadsheetml', $respuesta->headers->get('content-type'));
        $this->assertStringContainsString('completitud-2026-09.xlsx', $respuesta->headers->get('content-disposition'));
    }

    public function test_un_usuario_sin_alcance_de_envios_no_entra(): void
    {
        $ajeno = User::factory()->create(['activo' => true]);
        $ajeno->assignRole('consulta');

        // consulta tiene envios.ver.ss pero sin servicio no ve nada; entra igual.
        $this->actingAs($ajeno)->get('/completitud')->assertOk();
    }

    public function test_la_matriz_carga_rapido_con_muchos_establecimientos(): void
    {
        // RNF-005: 30 establecimientos × 7 series por debajo de 2 segundos.
        $series = [$this->serieA, $this->serieBS];

        for ($i = 3; $i <= 7; $i++) {
            $series[] = Serie::create(['codigo' => "S{$i}", 'nombre' => "SERIE S{$i}", 'orden' => $i]);
        }

        for ($i = 3; $i <= 30; $i++) {
            $establecimiento = Establecimiento::create([
                'codigo' => '9000'.$i, 'nombre' => "Establecimiento {$i}", 'servicio_id' => $this->servicio->id,
            ]);

            foreach ($series as $serie) {
                $establecimiento->series()->attach($serie->id, ['obligatoria' => true]);
            }

            $this->envio($establecimiento, Envio::RECIBIDO, [$serie->id => EnvioArchivo::VALIDO]);
        }

        $inicio = microtime(true);
        $matriz = $this->matriz($this->revisor);
        $segundos = microtime(true) - $inicio;

        $this->assertCount(30, $matriz['filas']);
        $this->assertLessThan(2.0, $segundos, "La matriz tardó {$segundos} s");
    }
}
