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
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * Flujo completo FT-01 (T-F2-30).
 *
 * Un envío real atraviesa todo: el encargado sube dos series, se valida, la
 * completitud se cumple, se envía a revisión, el revisor observa, el encargado
 * reenvía una versión nueva, se aprueba y se carga.
 *
 * Es la prueba que dice si las piezas de F2 funcionan juntas, no por separado.
 */
class FlujoCompletoTest extends TestCase
{
    use RefreshDatabase;

    private Establecimiento $establecimiento;
    private User $encargado;
    private User $revisor;
    private Serie $serieA;
    private Serie $serieBS;
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

        $this->serieA = Serie::create(['codigo' => 'A', 'nombre' => 'SERIE A', 'orden' => 1]);
        $this->serieBS = Serie::create(['codigo' => 'BS', 'nombre' => 'SERIE BS', 'orden' => 2]);

        // La matriz exige las dos series: sin ambas, el envío no está completo.
        $this->establecimiento->series()->attach($this->serieA->id, ['obligatoria' => true]);
        $this->establecimiento->series()->attach($this->serieBS->id, ['obligatoria' => true]);
    }

    private function excel(string $serie, string $marca = 'A'): UploadedFile
    {
        $libro = new Spreadsheet();
        $hoja = $libro->getActiveSheet();
        $hoja->setTitle('NOMBRE');
        $hoja->setCellValue('C3', '123100');
        $hoja->setCellValue('C6', '09');
        $hoja->setCellValue('B7', '2026');
        $hoja->setCellValue('B17', 'SERIE '.$serie);
        $hoja->setCellValue('A9', 'Version 1.2');
        $hoja->setCellValue('Z99', $marca); // cambia el hash entre versiones

        $tmp = tempnam(sys_get_temp_dir(), 'ft01').'.xlsx';
        (new Xlsx($libro))->save($tmp);
        $libro->disconnectWorksheets();

        return UploadedFile::fake()->createWithContent("123100{$serie}09.xlsx", file_get_contents($tmp));
    }

    public function test_un_envio_recorre_el_flujo_completo(): void
    {
        $envios = app(EnvioService::class);
        $estados = app(EstadoEnvioService::class);

        // 1. El encargado crea el envío.
        $envio = $envios->crear($this->establecimiento, 2026, 9, $this->encargado);
        $this->assertSame(Envio::RECIBIDO, $envio->estado);

        // 2. Sube la serie A. Todavía falta BS: el envío no está completo.
        $envios->subirVersion($envio, $this->serieA, $this->excel('A'), $this->encargado);
        $envios->revalidar($envio);
        $estados->recalcular($envio->fresh());

        $this->assertSame(Envio::CON_ERRORES, $envio->fresh()->estado, 'Falta una serie exigida');

        // 3. Sube la serie BS. Ahora sí está completo y válido.
        $envios->subirVersion($envio, $this->serieBS, $this->excel('BS'), $this->encargado);
        $envios->revalidar($envio);
        $estados->recalcular($envio->fresh());

        $this->assertSame(Envio::VALIDADO, $envio->fresh()->estado);

        // 4. Lo envía a revisión.
        $this->actingAs($this->encargado)
            ->post("/envios/{$envio->id}/transicion", ['accion' => 'enviar'])
            ->assertRedirect();

        $this->assertSame(Envio::EN_REVISION, $envio->fresh()->estado);

        // 5. El revisor observa.
        $this->actingAs($this->revisor)
            ->post("/envios/{$envio->id}/transicion", [
                'accion' => 'observar',
                'motivo' => 'La hoja A01 viene sin la columna 12',
            ])
            ->assertRedirect();

        $this->assertSame(Envio::OBSERVADO, $envio->fresh()->estado);

        // 6. El encargado corrige: sube una versión nueva de A.
        $this->actingAs($this->encargado);
        $nueva = $envios->subirVersion($envio, $this->serieA, $this->excel('A', 'corregido'), $this->encargado);

        $this->assertSame(2, $nueva->version, 'El reenvío crea versión nueva');
        $this->assertSame(2, $envio->archivos()->where('serie_id', $this->serieA->id)->count(), 'La anterior no se borra');

        // La subida devuelve el envío a recibido: hay que revalidar.
        $this->assertSame(Envio::RECIBIDO, $envio->fresh()->estado, 'Un reenvío vuelve a recibido');

        $envios->revalidar($envio);
        $estados->recalcular($envio->fresh());

        $this->assertSame(Envio::VALIDADO, $envio->fresh()->estado, 'Revalidado y completo, vuelve a validado');

        // 7. Vuelve a enviar y el revisor aprueba.
        $this->actingAs($this->encargado)
            ->post("/envios/{$envio->id}/transicion", ['accion' => 'enviar']);

        $this->actingAs($this->revisor)
            ->post("/envios/{$envio->id}/transicion", ['accion' => 'aprobar'])
            ->assertRedirect();

        $this->assertSame(Envio::APROBADO, $envio->fresh()->estado);

        // 8. Se marca como cargado.
        $this->actingAs($this->revisor)
            ->post("/envios/{$envio->id}/transicion", ['accion' => 'cargar'])
            ->assertRedirect();

        $envio = $envio->fresh();

        $this->assertSame(Envio::CARGADO, $envio->estado);
        $this->assertNotNull($envio->fecha_aprobacion);
        $this->assertNotNull($envio->fecha_carga);

        // 9. La historia completa queda registrada.
        $historia = $envio->transiciones()->orderBy('id')->pluck('estado_nuevo')->all();

        $this->assertSame(
            ['recibido', 'con_errores', 'recibido', 'validado', 'en_revision', 'observado', 'recibido', 'validado', 'en_revision', 'aprobado', 'cargado'],
            $historia
        );

        // 10. El reporte se puede descargar al final del recorrido.
        $this->actingAs($this->revisor)
            ->get("/envios/{$envio->id}/reporte")
            ->assertOk();
    }
}
