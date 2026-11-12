<?php

namespace Tests\Feature;

use App\Models\CatalogoSnapshot;
use App\Models\Envio;
use App\Models\Establecimiento;
use App\Models\Periodo;
use App\Models\Serie;
use App\Models\Servicio;
use App\Models\User;
use App\Notifications\EnvioNotificacion;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * Ciclo completo FT-02 (T-F3-13).
 *
 * Recorre el portal por HTTP como lo haría una persona: crear, subir, enviar,
 * observar, corregir, reenviar, aprobar y confirmar la carga. Es la prueba de
 * que las piezas de F2 y F3 encajan, no solo que cada una funciona.
 */
class FlujoRevisionCompletoTest extends TestCase
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
        Notification::fake();

        $servicio = Servicio::create(['codigo' => '23', 'nombre' => 'Osorno']);
        $this->establecimiento = Establecimiento::create([
            'codigo' => '123100', 'nombre' => 'Hospital Base San José', 'servicio_id' => $servicio->id,
        ]);
        Periodo::create(['anio' => 2026, 'mes' => 9, 'estado' => Periodo::ABIERTO]);
        CatalogoSnapshot::create(['origen' => 'prueba', 'activo' => true]);

        $this->encargado = User::factory()->create(['activo' => true]);
        $this->encargado->assignRole('encargado');
        $this->encargado->establecimientos()->attach($this->establecimiento->id);

        $this->revisor = User::factory()->create(['activo' => true, 'servicio_id' => $servicio->id]);
        $this->revisor->assignRole('revisor');

        $this->admin = User::factory()->create(['activo' => true, 'servicio_id' => $servicio->id]);
        $this->admin->assignRole('admin');

        $this->serieA = Serie::create(['codigo' => 'A', 'nombre' => 'SERIE A', 'orden' => 1]);
        $this->serieBS = Serie::create(['codigo' => 'BS', 'nombre' => 'SERIE BS', 'orden' => 2]);

        $this->establecimiento->series()->attach($this->serieA->id, ['obligatoria' => true]);
        $this->establecimiento->series()->attach($this->serieBS->id, ['obligatoria' => true]);
    }

    private function excel(string $serie, string $marca = 'A'): UploadedFile
    {
        $libro = new Spreadsheet();
        $hoja = $libro->getActiveSheet();
        $hoja->setTitle('NOMBRE');
        foreach ([
            'C3' => '123100', 'C6' => '09', 'B7' => '2026',
            'B17' => 'SERIE '.$serie, 'A9' => 'Version 1.2', 'Z99' => $marca,
        ] as $celda => $valor) {
            $hoja->setCellValue($celda, $valor);
        }

        $tmp = tempnam(sys_get_temp_dir(), 'ft02').'.xlsx';
        (new Xlsx($libro))->save($tmp);
        $libro->disconnectWorksheets();

        return UploadedFile::fake()->createWithContent("123100{$serie}09.xlsx", file_get_contents($tmp));
    }

    public function test_el_ciclo_completo_de_revision(): void
    {
        // 1. El encargado crea el envío desde el formulario.
        $this->actingAs($this->encargado)
            ->post('/envios', [
                'establecimiento_id' => $this->establecimiento->id,
                'periodo'            => '2026-9',
            ])
            ->assertRedirect();

        $envio = Envio::latest('id')->firstOrFail();
        $this->assertSame(Envio::RECIBIDO, $envio->estado);

        // 2. Sube las dos series exigidas.
        foreach ([[$this->serieA, 'A'], [$this->serieBS, 'BS']] as [$serie, $codigo]) {
            $this->actingAs($this->encargado)
                ->post("/envios/{$envio->id}/archivos", [
                    'serie_id' => $serie->id,
                    'archivo'  => $this->excel($codigo),
                ])
                ->assertRedirect();
        }

        $envio = $envio->fresh();
        $this->assertSame(2, $envio->archivosVigentes()->count());
        $this->assertSame(Envio::VALIDADO, $envio->estado, 'Completo y sin bloqueantes');

        // 3. Lo envía a revisión: el revisor recibe el aviso.
        $this->actingAs($this->encargado)
            ->post("/envios/{$envio->id}/transicion", ['accion' => 'enviar'])
            ->assertRedirect();

        $this->assertSame(Envio::EN_REVISION, $envio->fresh()->estado);
        Notification::assertSentTo($this->revisor, EnvioNotificacion::class, fn ($n) => $n->evento === 'enviado_a_revision');

        // 4. El revisor observa la serie A.
        $this->actingAs($this->revisor)
            ->post("/revision/{$envio->id}/observar", [
                'texto'    => 'La hoja A01 no trae la columna 12.',
                'serie_id' => $this->serieA->id,
            ])
            ->assertRedirect();

        $this->assertSame(Envio::OBSERVADO, $envio->fresh()->estado);
        Notification::assertSentTo($this->encargado, EnvioNotificacion::class, fn ($n) => $n->evento === 'observado');

        // 5. El encargado corrige: sube una versión nueva de A.
        $this->actingAs($this->encargado)
            ->post("/envios/{$envio->id}/archivos", [
                'serie_id' => $this->serieA->id,
                'archivo'  => $this->excel('A', 'corregido'),
            ])
            ->assertRedirect();

        $this->assertSame(
            0,
            $envio->observaciones()->where('resuelta', false)->count(),
            'La observación de esa serie queda resuelta'
        );

        $envio = $envio->fresh();
        $this->assertSame(2, $envio->archivos()->where('serie_id', $this->serieA->id)->count(), 'La versión anterior se conserva');
        $this->assertSame(Envio::VALIDADO, $envio->estado);

        // 6. Vuelve a enviar a revisión.
        $this->actingAs($this->encargado)
            ->post("/envios/{$envio->id}/transicion", ['accion' => 'enviar'])
            ->assertRedirect();

        $this->assertSame(Envio::EN_REVISION, $envio->fresh()->estado);

        // 7. El revisor aprueba.
        $this->actingAs($this->revisor)
            ->post("/revision/{$envio->id}/aprobar", ['comentario' => 'Corregido'])
            ->assertRedirect();

        $this->assertSame(Envio::APROBADO, $envio->fresh()->estado);
        Notification::assertSentTo($this->encargado, EnvioNotificacion::class, fn ($n) => $n->evento === 'aprobado');

        // 8. Se confirma la carga en el sistema institucional.
        $this->actingAs($this->revisor)
            ->post("/revision/{$envio->id}/cargar", ['referencia' => 'ACTA-2026-114'])
            ->assertRedirect();

        $envio = $envio->fresh();
        $this->assertSame(Envio::CARGADO, $envio->estado);
        $this->assertNotNull($envio->fecha_carga);

        // 9. La historia completa, sin saltos.
        $this->assertSame(
            [
                'recibido',    // se creó
                'con_errores', // subió solo A: la matriz exige A y BS
                'recibido',    // subió BS: vuelve a recibido
                'validado',    // completo y sin bloqueantes
                'en_revision', // lo envió
                'observado',   // el revisor observó A
                'recibido',    // subió la corrección de A
                'validado',    // revalidado y completo
                'en_revision', // lo reenvió
                'aprobado',
                'cargado',
            ],
            $envio->transiciones()->orderBy('id')->pluck('estado_nuevo')->all()
        );

        // 10. Todo quedó auditado.
        foreach (['envio.creado', 'archivo.subido', 'envio.transicion', 'envio.observado', 'envio.notificado'] as $evento) {
            $this->assertDatabaseHas('auditoria', ['evento' => $evento]);
        }

        // 11. El administrador puede reabrir si hubo un error.
        $this->actingAs($this->admin)
            ->post("/revision/{$envio->id}/reaperturar", ['motivo' => 'Se cargó el acta equivocada'])
            ->assertRedirect();

        $this->assertSame(Envio::EN_REVISION, $envio->fresh()->estado);
        $this->assertSame(12, $envio->fresh()->transiciones()->count());
    }

    public function test_el_encargado_no_puede_saltarse_la_revision(): void
    {
        $this->actingAs($this->encargado)
            ->post('/envios', [
                'establecimiento_id' => $this->establecimiento->id,
                'periodo'            => '2026-9',
            ]);

        $envio = Envio::latest('id')->firstOrFail();

        // Aprobar sin pasar por revisión: prohibido para el encargado.
        $this->actingAs($this->encargado)
            ->post("/revision/{$envio->id}/aprobar")
            ->assertForbidden();
    }
}
