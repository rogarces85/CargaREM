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

    /**
     * Sin matriz, R-SER-05 («sin fila, no obliga») dejaría al catálogo real sin
     * ninguna serie exigida: los 82 establecimientos del Servicio entrarían sin
     * nada que pedirles y V11 solo advertiría.
     */
    public function test_al_importar_exige_todas_las_series_activas(): void
    {
        $resumen = $this->importador()->cargar($this->fixture);

        $activas = Serie::where('activo', true)->count();
        $establecimientos = Establecimiento::count();

        $this->assertSame($activas, 2, 'El fixture trae SERIE A y SERIE BS');
        $this->assertSame(
            $establecimientos * $activas,
            $resumen['matriz'],
            'Cada establecimiento recibe una fila por serie activa'
        );

        $this->assertSame(
            $establecimientos * $activas,
            \DB::table('establecimiento_serie')->count()
        );

        foreach (Establecimiento::with('series')->get() as $establecimiento) {
            $this->assertSame($activas, $establecimiento->series->count());
            $this->assertTrue((bool) $establecimiento->series->first()->pivot->obligatoria);
            $this->assertNull(
                $establecimiento->series->first()->pivot->vigente_desde,
                'Rige desde siempre: no se inventa una fecha de inicio'
            );
        }
    }

    /**
     * El ajuste manual manda. Si el administrador quitó una serie, reimportar el
     * catálogo no se la vuelve a poner: si no, su decisión duraría hasta la
     * siguiente importación y nadie podría confiar en la matriz.
     */
    public function test_reimportar_no_pisa_una_matriz_ya_editada(): void
    {
        $this->importador()->cargar($this->fixture);

        $establecimiento = Establecimiento::first();
        $serieBS = Serie::where('codigo', 'BS')->first();

        // El administrador quita BS de ese establecimiento.
        $establecimiento->series()->detach($serieBS->id);

        $this->assertSame(
            Serie::where('activo', true)->count() - 1,
            $establecimiento->fresh()->series()->count()
        );

        $resumen = $this->importador()->cargar($this->fixture);

        $this->assertSame(0, $resumen['matriz'], 'Nada nuevo que crear: el par ya existía');
        $this->assertSame(
            Serie::where('activo', true)->count() - 1,
            $establecimiento->fresh()->series()->count(),
            'La edición del administrador se respeta'
        );
        $this->assertFalse(
            $establecimiento->fresh()->series->contains('id', $serieBS->id),
            'BS sigue fuera de la matriz de ese establecimiento'
        );
    }

    public function test_reimportar_no_duplica_la_matriz(): void
    {
        $primera = $this->importador()->cargar($this->fixture);
        $segunda = $this->importador()->cargar($this->fixture);

        $this->assertGreaterThan(0, $primera['matriz']);
        $this->assertSame(0, $segunda['matriz'], 'La segunda corrida no crea filas');
        $this->assertSame(
            Establecimiento::count() * Serie::where('activo', true)->count(),
            \DB::table('establecimiento_serie')->count(),
            'Sin duplicados'
        );
    }
}
