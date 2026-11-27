<?php

namespace Tests\Feature;

use App\Models\Envio;
use App\Models\Establecimiento;
use App\Models\Periodo;
use App\Models\Serie;
use App\Models\User;
use App\Services\EnvioService;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * Pantallas del flujo de envío (T-F2-10, T-F2-11) y descarga del reporte
 * (T-F2-09).
 */
class EnvioWebTest extends TestCase
{
    use RefreshDatabase;

    private Establecimiento $establecimiento;
    private User $encargado;
    private User $revisor;
    private Serie $serie;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Storage::fake('local');

        $this->establecimiento = Establecimiento::create([
            'codigo' => '123100', 'nombre' => 'Hospital Base',
        ]);
        Periodo::create(['anio' => 2026, 'mes' => 9, 'estado' => Periodo::ABIERTO]);

        $this->encargado = User::factory()->create(['activo' => true]);
        $this->encargado->assignRole('encargado');
        $this->encargado->establecimientos()->attach($this->establecimiento->id);

        $this->revisor = User::factory()->create(['activo' => true]);
        $this->revisor->assignRole('revisor');
        $this->revisor->establecimientos()->attach($this->establecimiento->id);

        $this->serie = Serie::create(['codigo' => 'A', 'nombre' => 'SERIE A']);
        $this->establecimiento->series()->attach($this->serie->id, ['obligatoria' => true]);
    }

    private function excel(string $nombre = '123100A09.xlsx'): UploadedFile
    {
        $libro = new Spreadsheet();
        $hoja = $libro->getActiveSheet();
        $hoja->setTitle('NOMBRE');
        foreach (['C3' => '123100', 'C6' => '09', 'B7' => '2026', 'B17' => 'SERIE A', 'A9' => 'Version 1.2'] as $c => $v) {
            $hoja->setCellValue($c, $v);
        }

        $tmp = tempnam(sys_get_temp_dir(), 'web').'.xlsx';
        (new Xlsx($libro))->save($tmp);
        $libro->disconnectWorksheets();

        return UploadedFile::fake()->createWithContent($nombre, file_get_contents($tmp));
    }

    public function test_el_encargado_ve_su_tablero(): void
    {
        $this->actingAs($this->encargado)
            ->get('/envios')
            ->assertOk()
            ->assertSee('Nuevo envío');
    }

    public function test_el_revisor_ve_los_envios_de_su_servicio(): void
    {
        app(EnvioService::class)->crear($this->establecimiento, 2026, 9, $this->encargado);

        $this->actingAs($this->revisor)
            ->get('/envios')
            ->assertOk()
            ->assertSee('123100');
    }

    public function test_crea_un_envio_desde_el_formulario(): void
    {
        $this->actingAs($this->encargado)
            ->post('/envios', [
                'establecimiento_id' => $this->establecimiento->id,
                'periodo'            => '2026-9',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('envios', [
            'establecimiento_id' => $this->establecimiento->id,
            'anio'               => 2026,
            'mes'                => 9,
            'estado'             => 'recibido',
        ]);
    }

    public function test_el_detalle_muestra_las_series_exigidas(): void
    {
        $envio = app(EnvioService::class)->crear($this->establecimiento, 2026, 9, $this->encargado);

        $this->actingAs($this->encargado)
            ->get("/envios/{$envio->id}")
            ->assertOk()
            ->assertSee('SERIE A')
            // La etiqueta del estado se unificó con el resto del vocabulario:
            // «Aprobado», «Observado», «Sin archivo» van con mayúscula inicial.
            ->assertSee('Sin archivo');
    }

    /**
     * La tabla «Series del período» se armaba con las series sin mirar la
     * vigencia: ofrecía como pendiente una serie que la matriz ya no exigía y
     * que V11 ya no reclamaba.
     *
     * El desplegable de subida sí lista todas las series activas, y eso es
     * deliberado: permite subir una serie que la matriz no exige. Lo que no
     * puede aparecer como exigida es la que venció.
     *
     * «Hoy» se fija el 15-08-2026: la serie vence el 31-08, así que con `now()`
     * todavía estaría vigente y la tabla la mostraría. Con el cierre del
     * período (30-09) no.
     */
    public function test_la_tabla_de_series_no_muestra_una_serie_con_vigencia_terminada(): void
    {
        $this->travelTo(Carbon::parse('2026-08-15'));

        $serieBS = Serie::create(['codigo' => 'BS', 'nombre' => 'SERIE BS', 'orden' => 2]);

        $this->establecimiento->series()->attach($serieBS->id, [
            'obligatoria'   => true,
            'vigente_hasta' => '2026-08-31',
        ]);

        $envio = app(EnvioService::class)->crear($this->establecimiento, 2026, 9, $this->encargado);

        // El contador de la cabecera es el que manda: una sola serie exigida.
        $this->actingAs($this->encargado)
            ->get("/envios/{$envio->id}")
            ->assertOk()
            ->assertSee('Series exigidas')
            ->assertViewHas('exigidas', fn ($exigidas) => $exigidas->pluck('codigo')->all() === ['A']);

        $this->travelBack();
    }

    public function test_la_tabla_de_series_muestra_una_serie_vigente_hasta_el_cierre_del_periodo(): void
    {
        $serieBS = Serie::create(['codigo' => 'BS', 'nombre' => 'SERIE BS', 'orden' => 2]);

        // Vigente justo hasta el último día del período del envío (9/2026).
        $this->establecimiento->series()->attach($serieBS->id, [
            'obligatoria'   => true,
            'vigente_hasta' => '2026-09-30',
        ]);

        $envio = app(EnvioService::class)->crear($this->establecimiento, 2026, 9, $this->encargado);

        $this->actingAs($this->encargado)
            ->get("/envios/{$envio->id}")
            ->assertOk()
            ->assertViewHas('exigidas', fn ($exigidas) => $exigidas->pluck('codigo')->all() === ['A', 'BS']);
    }

    public function test_sube_un_archivo_y_lo_valida(): void
    {
        $envio = app(EnvioService::class)->crear($this->establecimiento, 2026, 9, $this->encargado);

        $this->actingAs($this->encargado)
            ->post("/envios/{$envio->id}/archivos", [
                'serie_id' => $this->serie->id,
                'archivo'  => $this->excel(),
            ])
            ->assertRedirect(route('envios.show', $envio));

        $archivo = $envio->archivos()->first();

        $this->assertSame(1, $envio->archivos()->count());
        $this->assertNotNull($archivo->validado_en, 'La subida dispara la validación');
    }

    public function test_un_archivo_invalido_vuelve_con_error(): void
    {
        $envio = app(EnvioService::class)->crear($this->establecimiento, 2026, 9, $this->encargado);

        $this->actingAs($this->encargado)
            ->post("/envios/{$envio->id}/archivos", [
                'serie_id' => $this->serie->id,
                'archivo'  => UploadedFile::fake()->createWithContent('notas.xlsx', 'no soy un excel'),
            ])
            ->assertSessionHasErrors('archivo');

        $this->assertSame(0, $envio->archivos()->count());
    }

    public function test_un_archivo_con_bloqueantes_avisa_del_problema(): void
    {
        $envio = app(EnvioService::class)->crear($this->establecimiento, 2026, 9, $this->encargado);

        // El nombre no corresponde al establecimiento del envío: V1 deja un
        // bloqueante. El archivo se guarda, pero el mensaje no puede decir
        // «validado».
        $respuesta = $this->actingAs($this->encargado)
            ->post("/envios/{$envio->id}/archivos", [
                'serie_id' => $this->serie->id,
                'archivo'  => $this->excel('999999A09.xlsx'),
            ]);

        $respuesta->assertSessionHasErrors('archivo');
        $this->assertSame(1, $envio->archivos()->count());
        $this->assertSame(Envio::CON_ERRORES, $envio->fresh()->estado);
    }

    public function test_descarga_el_archivo_subido(): void
    {
        $envio = app(EnvioService::class)->crear($this->establecimiento, 2026, 9, $this->encargado);
        app(EnvioService::class)->subirVersion($envio, $this->serie, $this->excel(), $this->encargado);
        $archivo = $envio->archivos()->first();

        $this->actingAs($this->encargado)
            ->get("/envios/{$envio->id}/archivos/{$archivo->id}/descargar")
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_no_descarga_el_archivo_de_otro_envio(): void
    {
        $envio = app(EnvioService::class)->crear($this->establecimiento, 2026, 9, $this->encargado);
        app(EnvioService::class)->subirVersion($envio, $this->serie, $this->excel(), $this->encargado);
        $archivo = $envio->archivos()->first();

        Periodo::create(['anio' => 2026, 'mes' => 10, 'estado' => Periodo::ABIERTO]);
        $otro = app(EnvioService::class)->crear($this->establecimiento, 2026, 10, $this->encargado);

        $this->actingAs($this->encargado)
            ->get("/envios/{$otro->id}/archivos/{$archivo->id}/descargar")
            ->assertNotFound();
    }

    public function test_descarga_el_reporte_en_excel(): void
    {
        $envio = app(EnvioService::class)->crear($this->establecimiento, 2026, 9, $this->encargado);
        app(EnvioService::class)->subirVersion($envio, $this->serie, $this->excel(), $this->encargado);

        $respuesta = $this->actingAs($this->encargado)->get("/envios/{$envio->id}/reporte");

        $respuesta->assertOk();
        $this->assertStringContainsString('spreadsheetml', $respuesta->headers->get('content-type'));
        $this->assertStringContainsString('reporte-123100-09-2026.xlsx', $respuesta->headers->get('content-disposition'));
    }

    public function test_un_encargado_ajeno_no_ve_el_envio(): void
    {
        $envio = app(EnvioService::class)->crear($this->establecimiento, 2026, 9, $this->encargado);

        $ajeno = User::factory()->create(['activo' => true]);
        $ajeno->assignRole('encargado');

        $this->actingAs($ajeno)->get("/envios/{$envio->id}")->assertForbidden();
    }

    public function test_el_encargado_envia_a_revision_y_el_revisor_aprueba(): void
    {
        $envio = app(EnvioService::class)->crear($this->establecimiento, 2026, 9, $this->encargado);
        app(EnvioService::class)->subirVersion($envio, $this->serie, $this->excel(), $this->encargado);
        app(EnvioService::class)->revalidar($envio);
        app(\App\Services\EstadoEnvioService::class)->recalcular($envio->fresh());

        $this->actingAs($this->encargado)
            ->post("/envios/{$envio->id}/transicion", ['accion' => 'enviar'])
            ->assertRedirect();

        $this->assertSame(Envio::EN_REVISION, $envio->fresh()->estado);

        $this->actingAs($this->revisor)
            ->post("/envios/{$envio->id}/transicion", ['accion' => 'aprobar'])
            ->assertRedirect();

        $this->assertSame(Envio::APROBADO, $envio->fresh()->estado);
    }

    public function test_un_encargado_no_puede_aprobar(): void
    {
        $envio = app(EnvioService::class)->crear($this->establecimiento, 2026, 9, $this->encargado);
        $envio->update(['estado' => Envio::EN_REVISION]);

        $this->actingAs($this->encargado)
            ->post("/envios/{$envio->id}/transicion", ['accion' => 'aprobar'])
            ->assertForbidden();
    }
}
