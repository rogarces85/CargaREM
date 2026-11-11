<?php

namespace Tests\Feature;

use App\Models\CatalogoSnapshot;
use App\Models\Envio;
use App\Models\EnvioArchivo;
use App\Models\Establecimiento;
use App\Models\Periodo;
use App\Models\Plantilla;
use App\Models\PlantillaHoja;
use App\Models\Serie;
use App\Models\Servicio;
use App\Models\User;
use App\Services\EnvioService;
use App\Validacion\ValidadorArchivo;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * V3 (versión de plantilla), V4 (pertenencia) y V5 (hojas).
 */
class ValidadorV345Test extends TestCase
{
    use RefreshDatabase;

    private Envio $envio;
    private Serie $serie;
    private Establecimiento $establecimiento;
    private User $usuario;
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

        $this->usuario = User::factory()->create(['activo' => true]);
        $this->usuario->assignRole('encargado');
        $this->usuario->establecimientos()->attach($this->establecimiento->id);
        $this->actingAs($this->usuario);

        $this->serie = Serie::create(['codigo' => 'A', 'nombre' => 'SERIE A']);
        $this->envio = app(EnvioService::class)->crear($this->establecimiento, 2026, 9, $this->usuario);

        // Matriz: la serie del archivo es la única exigida, así V11 no
        // añade avisos de completitud que no son el objeto de esta prueba.
        $this->envio->establecimiento->series()->attach($this->serie->id, ['obligatoria' => true]);
    }

    private function plantilla(string $version = '1.2', string $serieInterna = 'SERIE A1'): Plantilla
    {
        return Plantilla::create([
            'anio'          => 2026,
            'mes'           => 9,
            'serie_id'      => $this->serie->id,
            'serie_excel'   => 'SERIE A',
            'serie_interna' => $serieInterna,
            'version'       => $version,
            'snapshot_id'   => $this->snapshot->id,
        ]);
    }

    /** @param array<string, string> $celdas */
    private function excel(array $celdas = [], array $hojasExtra = [], string $hojaPrincipal = 'NOMBRE'): string
    {
        $libro = new Spreadsheet();
        $hoja = $libro->getActiveSheet();
        $hoja->setTitle($hojaPrincipal);

        $base = array_merge([
            'C3' => '123100', 'C6' => '09', 'B7' => '2026', 'B17' => 'SERIE A', 'A9' => 'Version 1.2',
        ], $celdas);

        foreach ($base as $celda => $valor) {
            $hoja->setCellValue($celda, $valor);
        }

        foreach ($hojasExtra as $nombre) {
            $libro->createSheet()->setTitle($nombre);
        }

        $ruta = tempnam(sys_get_temp_dir(), 'rem').'.xlsx';
        (new Xlsx($libro))->save($ruta);
        $libro->disconnectWorksheets();

        return $ruta;
    }

    private function archivo(string $rutaExcel, ?User $subidoPor = null): EnvioArchivo
    {
        $contenido = file_get_contents($rutaExcel);
        $ruta = 'envios/2026/09/123100/v9/'.uniqid('v345_', true).'.xlsx';
        Storage::disk('local')->put($ruta, $contenido);

        return $this->envio->archivos()->create([
            'serie_id'        => $this->serie->id,
            'version'         => 9,
            'es_vigente'      => true,
            'nombre_original' => '123100A09.xlsx',
            'ruta'            => $ruta,
            'disco'           => 'local',
            'sha256'          => hash('sha256', $contenido),
            'tamano_bytes'    => strlen($contenido),
            'mime'            => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'estado'          => EnvioArchivo::SUBIDO,
            'snapshot_id'     => $this->snapshot->id,
            'subido_por'      => ($subidoPor ?? $this->usuario)->id,
        ]);
    }

    private function codigos(EnvioArchivo $archivo): array
    {
        $resultado = app(ValidadorArchivo::class)->validar($archivo);

        return array_map(fn ($h) => $h->codigo, $resultado->hallazgos);
    }

    public function test_v3_acepta_la_version_del_catalogo(): void
    {
        $this->plantilla('1.2');

        $this->assertSame([], $this->codigos($this->archivo($this->excel())));
    }

    public function test_v3_bloquea_una_version_distinta(): void
    {
        $this->plantilla('1.3');

        $this->assertSame(['ENV-014'], $this->codigos($this->archivo($this->excel())));
    }

    public function test_v3_avisa_si_el_catalogo_no_tiene_plantilla(): void
    {
        $archivo = $this->archivo($this->excel());

        $resultado = app(ValidadorArchivo::class)->validar($archivo);

        // Sin plantilla, V3 y V5 avisan de que no pueden verificar. No bloquean.
        $this->assertSame(['ENV-014', 'ENV-016'], array_map(fn ($h) => $h->codigo, $resultado->hallazgos));
        $this->assertSame('advertencia', $resultado->hallazgos[0]->severidad, 'Sin plantilla se advierte, no se bloquea');
        $this->assertTrue($resultado->esValido());
    }

    public function test_v4_bloquea_si_el_autor_ya_no_tiene_alcance(): void
    {
        $this->plantilla();

        $antiguo = User::factory()->create(['activo' => true]);
        $antiguo->assignRole('encargado');
        // Sin establecimientos asignados ni servicio: perdió el alcance.

        $this->assertSame(['ENV-015'], $this->codigos($this->archivo($this->excel(), $antiguo)));
    }

    public function test_v4_acepta_al_admin(): void
    {
        $this->plantilla();

        $admin = User::factory()->create(['activo' => true]);
        $admin->assignRole('admin');

        $this->assertSame([], $this->codigos($this->archivo($this->excel(), $admin)));
    }

    public function test_v5_bloquea_si_falta_una_hoja_obligatoria(): void
    {
        $this->plantilla();
        PlantillaHoja::create(['anio' => 2026, 'serie_interna' => 'SERIE A1', 'hoja' => 'NOMBRE', 'obligatoria' => true]);
        PlantillaHoja::create(['anio' => 2026, 'serie_interna' => 'SERIE A1', 'hoja' => 'A01', 'obligatoria' => true]);
        PlantillaHoja::create(['anio' => 2026, 'serie_interna' => 'SERIE A1', 'hoja' => 'A02', 'obligatoria' => true]);

        $codigos = $this->codigos($this->archivo($this->excel([], ['A01'])));

        $this->assertSame(['ENV-016'], $codigos);
    }

    public function test_v5_avisa_de_una_hoja_no_prevista(): void
    {
        $this->plantilla();
        PlantillaHoja::create(['anio' => 2026, 'serie_interna' => 'SERIE A1', 'hoja' => 'NOMBRE', 'obligatoria' => true]);

        $codigos = $this->codigos($this->archivo($this->excel([], ['SOBRA'])));

        $this->assertSame(['ENV-017'], $codigos);
    }

    public function test_v5_no_bloquea_cuando_el_catalogo_no_define_hojas(): void
    {
        $this->plantilla();

        $resultado = app(ValidadorArchivo::class)->validar($this->archivo($this->excel()));

        $this->assertSame([], array_map(fn ($h) => $h->codigo, $resultado->hallazgos));
    }
}
