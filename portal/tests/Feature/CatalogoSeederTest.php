<?php

namespace Tests\Feature;

use App\Exceptions\CatalogoInvalidoException;
use App\Models\Diccionario;
use App\Models\Establecimiento;
use App\Models\Plantilla;
use App\Models\PlantillaHoja;
use App\Models\Serie;
use App\Models\Servicio;
use App\Services\ImportadorCatalogo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Carga del catálogo de validación (MOD-02, v2.0 sin snapshots).
 */
class CatalogoSeederTest extends TestCase
{
    use RefreshDatabase;

    private string $fixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixture = base_path('tests/fixtures/catalogo');
    }

    private function importador(): ImportadorCatalogo
    {
        return app(ImportadorCatalogo::class);
    }

    public function test_carga_un_catalogo_completo(): void
    {
        $resumen = $this->importador()->cargar($this->fixture);

        $this->assertSame(2, $resumen['establecimientos']);
        $this->assertSame(2, $resumen['plantillas']);
        $this->assertSame(3, $resumen['hojas']);
        $this->assertSame(2, $resumen['diccionario']);

        $this->assertSame(2, Serie::count(), 'SERIE A y SERIE A1 colapsan en una serie externa');
        $this->assertSame(2, Establecimiento::count());
        $this->assertSame(2, Diccionario::count());
        $this->assertSame(3, PlantillaHoja::count());
        $this->assertSame(2, Plantilla::count());
    }

    public function test_crea_el_servicio_configurado_y_lo_asigna(): void
    {
        $this->importador()->cargar($this->fixture);

        $servicio = Servicio::where('codigo', (string) config('rem.servicio.codigo'))->first();

        $this->assertNotNull($servicio, 'El catálogo debe traer el Servicio configurado');
        $this->assertSame($servicio->id, Establecimiento::first()->servicio_id);
    }

    public function test_conserva_la_serie_interna_en_el_diccionario(): void
    {
        $this->importador()->cargar($this->fixture);

        $fila = Diccionario::first();

        $this->assertSame('SERIE A1', $fila->serie, 'El diccionario se indexa por el nombre interno');
        $this->assertSame('Consulta medica', $fila->texto_prestacion);
    }

    public function test_lee_campos_con_coma_entre_comillas(): void
    {
        $this->importador()->cargar($this->fixture);

        $fila = Diccionario::where('codigo_prestacion', '01010101')->first();

        $this->assertNotNull($fila, 'Un campo entrecomillado con coma no debe romper la fila');
        $this->assertSame('Control con texto, con coma', $fila->texto_prestacion);
    }

    public function test_reemplaza_el_diccionario_sin_duplicar(): void
    {
        $this->importador()->cargar($this->fixture);
        $this->importador()->cargar($this->fixture);

        $this->assertSame(2, Diccionario::count(), 'El diccionario se reemplaza, no se acumula');
        $this->assertSame(3, PlantillaHoja::count(), 'Las hojas no se duplican');
    }

    public function test_una_carga_incompleta_no_deja_restos(): void
    {
        $destino = sys_get_temp_dir().DIRECTORY_SEPARATOR.'catalogo_incompleto_'.uniqid();

        File::copyDirectory($this->fixture, $destino);
        File::delete($destino.DIRECTORY_SEPARATOR.'diccionario.csv');

        try {
            $this->expectException(CatalogoInvalidoException::class);

            $this->importador()->cargar($destino);
        } finally {
            $this->assertSame(0, Diccionario::count(), 'La transacción no debe dejar restos');
            $this->assertSame(0, Establecimiento::count());
            File::deleteDirectory($destino);
        }
    }

    public function test_disponible_solo_con_todos_los_archivos(): void
    {
        $this->assertTrue($this->importador()->disponible($this->fixture));
        $this->assertFalse($this->importador()->disponible($this->fixture.DIRECTORY_SEPARATOR.'no-existe'));
    }
}
