<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\ValidadorEjecucion;
use App\Validador\FiltroResultados;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Filtros del listado de resultados (RF-040.4).
 *
 * Lo que se fija aquí:
 *
 * 1. **Al abrir se ve lo que hay que corregir**, no las 87 reglas.
 * 2. **La búsqueda ignora tildes**: quien escribe «presion» encuentra «Presión».
 * 3. **Los filtros se combinan**: estado y severidad y hoja a la vez acotan.
 * 4. **El conteo por hoja no se encoge** al filtrar: sirve para navegar.
 */
class FiltroResultadosTest extends TestCase
{
    use RefreshDatabase;

    private User $encargado;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Storage::fake('local');

        $this->encargado = User::factory()->create(['activo' => true]);
        $this->encargado->assignRole('encargado');
    }

    /** @return list<array<string, mixed>> */
    private function resultados(): array
    {
        return [
            $this->regla('A01-VAL001', 'REVISAR', 'CUMPLE', 'A01', 'Control Preconcepcional en edades extremas', 'F11'),
            $this->regla('A01-VAL023', 'ERROR', 'HALLAZGO', 'A01', 'Control Puérpera recién nacido', 'C89'),
            $this->regla('A05-VAL005', 'ERROR', 'HALLAZGO', 'A05', 'Ingresos al programa de salud', 'C204'),
            $this->regla('A08-VAL001a', 'REVISAR', 'OMITIDA', 'A08', 'Sección exclusiva del titular', 'E12'),
            $this->regla('P1-VAL009a', 'REVISAR', 'OMITIDA', 'P1', 'Prestación sin datos', 'B160'),
        ];
    }

    /** @return array<string, mixed> */
    private function regla(string $id, string $severidad, string $estado, string $hoja, string $mensaje, string $celda): array
    {
        return [
            'regla' => $id, 'severidad' => $severidad, 'estado' => $estado,
            'mensaje' => $mensaje, 'hoja' => $hoja, 'hoja2' => null,
            'expresion1' => $celda, 'valor1' => 0.0, 'operador' => '==',
            'expresion2' => '0', 'valor2' => 0.0, 'diferencia' => 0.0,
            'celdas' => ["{$hoja}!{$celda}"], 'omision' => $estado === 'OMITIDA' ? 'AMBOS_CERO' : null,
            'evidencia' => '', 'bloquea' => true,
        ];
    }

    /** @param  list<string>  $ids */
    private function ids(array $resultados): array
    {
        return array_map(fn (array $r) => $r['regla'], array_values($resultados));
    }

    // ─── Comportamiento de partida ────────────────────────────────────────

    public function test_al_abrir_solo_se_ve_lo_que_hay_que_corregir(): void
    {
        $filtro = FiltroResultados::desdeConsulta([]);

        $this->assertSame(
            ['A01-VAL023', 'A05-VAL005'],
            $this->ids($filtro->aplicar($this->resultados())),
            'De partida se ven los hallazgos y los errores técnicos, no las 5 reglas.'
        );
    }

    public function test_sin_filtro_de_estado_se_ven_todos(): void
    {
        $filtro = new FiltroResultados(estados: []);

        $this->assertCount(5, $filtro->aplicar($this->resultados()));
    }

    // ─── Filtros individuales ─────────────────────────────────────────────

    public function test_el_filtro_por_estado(): void
    {
        $filtro = new FiltroResultados(estados: ['CUMPLE']);

        $this->assertSame(['A01-VAL001'], $this->ids($filtro->aplicar($this->resultados())));
    }

    public function test_el_filtro_por_severidad(): void
    {
        $filtro = new FiltroResultados(estados: [], severidades: ['ERROR']);

        $this->assertSame(['A01-VAL023', 'A05-VAL005'], $this->ids($filtro->aplicar($this->resultados())));
    }

    public function test_el_filtro_por_hoja(): void
    {
        $filtro = new FiltroResultados(estados: [], hoja: 'A08');

        $this->assertSame(['A08-VAL001a'], $this->ids($filtro->aplicar($this->resultados())));
    }

    public function test_los_filtros_se_combinan(): void
    {
        // Hallazgos de la hoja A05: solo uno.
        $filtro = new FiltroResultados(estados: ['HALLAZGO'], severidades: ['ERROR'], hoja: 'A05');

        $this->assertSame(['A05-VAL005'], $this->ids($filtro->aplicar($this->resultados())));
    }

    public function test_un_filtro_sin_coincidencias_devuelve_vacio(): void
    {
        $filtro = new FiltroResultados(estados: ['HALLAZGO'], hoja: 'P1');

        $this->assertSame([], $filtro->aplicar($this->resultados()));
    }

    // ─── Búsqueda ─────────────────────────────────────────────────────────

    public function test_la_busqueda_ignora_mayusculas(): void
    {
        $filtro = new FiltroResultados(estados: [], busqueda: 'INGRESOS');

        $this->assertSame(['A05-VAL005'], $this->ids($filtro->aplicar($this->resultados())));
    }

    /** El catálogo trae «Puérpera», «Sección», «Prestación»: nadie escribe tildes al buscar. */
    public function test_la_busqueda_ignora_las_tildes(): void
    {
        $casos = [
            'puérpera' => 'A01-VAL023',
            'puérpera sin tilde' => null,
        ];

        $filtro = new FiltroResultados(estados: [], busqueda: 'puérpera');
        $this->assertSame(['A01-VAL023'], $this->ids($filtro->aplicar($this->resultados())));

        $filtro = new FiltroResultados(estados: [], busqueda: 'puerpera');
        $this->assertSame(['A01-VAL023'], $this->ids($filtro->aplicar($this->resultados())), 'Sin tilde también encuentra.');

        $filtro = new FiltroResultados(estados: [], busqueda: 'seccion');
        $this->assertSame(['A08-VAL001a'], $this->ids($filtro->aplicar($this->resultados())), '«seccion» encuentra «Sección».');

        $filtro = new FiltroResultados(estados: [], busqueda: 'prestacion');
        $this->assertSame(['P1-VAL009a'], $this->ids($filtro->aplicar($this->resultados())), '«prestacion» encuentra «Prestación».');
    }

    public function test_la_busqueda_encuentra_por_codigo_de_regla_y_por_celda(): void
    {
        $filtro = new FiltroResultados(estados: [], busqueda: 'A05-VAL005');
        $this->assertSame(['A05-VAL005'], $this->ids($filtro->aplicar($this->resultados())));

        $filtro = new FiltroResultados(estados: [], busqueda: 'C204');
        $this->assertSame(['A05-VAL005'], $this->ids($filtro->aplicar($this->resultados())), 'Busca en las celdas leídas.');
    }

    /** Varios términos acotan: todos tienen que aparecer. */
    public function test_varios_terminos_de_busqueda_se_exigen_todos(): void
    {
        $filtro = new FiltroResultados(estados: [], busqueda: 'A01 puerpera');
        $this->assertSame(['A01-VAL023'], $this->ids($filtro->aplicar($this->resultados())));

        $filtro = new FiltroResultados(estados: [], busqueda: 'A05 puerpera');
        $this->assertSame([], $filtro->aplicar($this->resultados()), '«A05» y «puerpera» no coinciden en ninguna fila.');
    }

    public function test_la_busqueda_se_combina_con_el_estado(): void
    {
        // «puerpera» existe en una regla que cumple y en otra que falla; con el
        // filtro de partida solo aparece la que falla.
        $filtro = new FiltroResultados(busqueda: 'A01');

        $this->assertSame(['A01-VAL023'], $this->ids($filtro->aplicar($this->resultados())));
    }

    // ─── Conteo por hoja ──────────────────────────────────────────────────

    /** El desplegable no puede encogerse al filtrar, o deja de servir para navegar. */
    public function test_el_conteo_por_hoja_usa_todos_los_resultados(): void
    {
        $hojas = FiltroResultados::conteoPorHoja($this->resultados());

        $this->assertSame(
            ['A01', 'A05', 'A08', 'P1'],
            array_column($hojas, 'hoja'),
            'Las hojas salen ordenadas naturalmente, con A05 antes que A08.'
        );

        $porHoja = array_column($hojas, null, 'hoja');
        $this->assertSame(2, $porHoja['A01']['total']);
        $this->assertSame(1, $porHoja['A01']['hallazgos']);
        // A08 solo aporta una regla y está OMITIDA: una sección sin actividad
        // no es un hallazgo, y el conteo del desplegable no puede sugerir que sí.
        $this->assertSame(1, $porHoja['A08']['total']);
        $this->assertSame(0, $porHoja['A08']['hallazgos'], 'Una omitida no cuenta como hallazgo.');
    }

    // ─── Construcción desde la URL ────────────────────────────────────────

    public function test_la_url_desconoce_estados_y_severidades_inventados(): void
    {
        $filtro = FiltroResultados::desdeConsulta([
            'estados' => ['HALLAZGO', 'INVENTADO'],
            'severidades' => ['ERROR', 'NO_EXISTE'],
        ]);

        $this->assertSame(['HALLAZGO'], $filtro->estados);
        $this->assertSame(['ERROR'], $filtro->severidades);
    }

    public function test_sin_parametros_la_url_usa_el_filtro_de_partida(): void
    {
        $filtro = FiltroResultados::desdeConsulta([]);

        $this->assertSame(FiltroResultados::INICIALES, $filtro->estados);
        $this->assertSame([], $filtro->severidades);
        $this->assertNull($filtro->hoja);
        $this->assertSame('', $filtro->busqueda);
    }

    // ─── Alternar y estado de los chips ───────────────────────────────────

    public function test_alternar_un_estado_lo_quita_si_estaba(): void
    {
        $filtro = FiltroResultados::desdeConsulta([]);

        $sinHallazgo = $filtro->conEstado('HALLAZGO');
        $this->assertSame(['ERROR_TECNICO'], $sinHallazgo->estados);

        $conHallazgo = $sinHallazgo->conEstado('HALLAZGO');
        $this->assertSame(['HALLAZGO', 'ERROR_TECNICO'], $conHallazgo->estados, 'Vuelve en su orden canónico.');
    }

    public function test_alternar_una_severidad_la_agrega(): void
    {
        $filtro = FiltroResultados::desdeConsulta([])->conSeveridad('ERROR');

        $this->assertSame(['ERROR'], $filtro->severidades);
        $this->assertSame([], $filtro->conSeveridad('ERROR')->severidades);
    }

    public function test_el_filtro_de_partida_no_cuenta_como_activo(): void
    {
        $this->assertFalse(FiltroResultados::desdeConsulta([])->hayFiltrosActivos());
        $this->assertTrue(FiltroResultados::desdeConsulta(['q' => 'algo'])->hayFiltrosActivos());
        $this->assertTrue(FiltroResultados::desdeConsulta(['hoja' => 'A01'])->hayFiltrosActivos());
        $this->assertTrue(FiltroResultados::desdeConsulta(['severidades' => ['ERROR']])->hayFiltrosActivos());
        $this->assertTrue(FiltroResultados::desdeConsulta(['estados' => []])->hayFiltrosActivos(), 'Ver todo es un cambio.');
    }

    /** La URL de un chip no arrastra el filtro de partida: «Ver todo» no aparece por defecto. */
    public function test_la_url_no_repite_el_filtro_de_partida(): void
    {
        $this->assertSame([], FiltroResultados::desdeConsulta([])->aConsulta());

        $consulta = FiltroResultados::desdeConsulta(['q' => 'A01', 'hoja' => 'A05'])->aConsulta();
        $this->assertSame('A01', $consulta['q']);
        $this->assertSame('A05', $consulta['hoja']);
        $this->assertArrayNotHasKey('estados', $consulta);
    }

    // ─── La pantalla ──────────────────────────────────────────────────────

    private function ejecucion(): ValidadorEjecucion
    {
        return ValidadorEjecucion::create([
            'usuario_id'      => $this->encargado->id,
            'nombre_original' => '123100A09.xlsm',
            'ruta'            => 'validador/x.xlsm',
            'sha256'          => str_repeat('a', 64),
            'tamano_bytes'    => 1024,
            'reglas_version'  => '2026.09',
            'hallazgos'       => 2,
            'bloqueantes'     => 1,
            'advertencias'    => 0,
            'duracion_ms'     => 1000,
            'estado'          => ValidadorEjecucion::CON_HALLAZGOS,
            'resultado'       => [
                'metadatos' => ['nombre' => '123100A09.xlsm', 'codigo' => '123100', 'serie' => 'A', 'mes' => '09'],
                'resumen'   => ['evaluadas' => 2, 'cumplen' => 0, 'hallazgos' => 2, 'porcentaje' => 0.0, 'veredicto' => 'RECHAZADO'],
                'resultados' => $this->resultados(),
                'hallazgos'  => [
                    $this->resultados()[1],
                    $this->resultados()[2],
                ],
            ],
        ]);
    }

    public function test_la_pantalla_abre_en_lo_que_hay_que_corregir(): void
    {
        $ejecucion = $this->ejecucion();

        $respuesta = $this->actingAs($this->encargado)->get(route('validador.show', $ejecucion));

        $respuesta->assertOk();
        // Se ven los 2 hallazgos, no las 5 reglas.
        $this->assertSame(2, count($respuesta->viewData('visibles')));
        $this->assertSame(5, $respuesta->viewData('total'));
    }

    public function test_la_pantalla_aplica_el_filtro_de_la_url(): void
    {
        $ejecucion = $this->ejecucion();

        $respuesta = $this->actingAs($this->encargado)
            ->get(route('validador.show', [$ejecucion, 'estados' => ['CUMPLE']]));

        $respuesta->assertOk();
        $this->assertSame(1, count($respuesta->viewData('visibles')));
        $this->assertSame('A01-VAL001', $respuesta->viewData('visibles')[0]['regla']);
    }

    public function test_la_pantalla_busca_sin_tildes(): void
    {
        $ejecucion = $this->ejecucion();

        $respuesta = $this->actingAs($this->encargado)
            ->get(route('validador.show', [$ejecucion, 'q' => 'puerpera', 'estados' => []]));

        $respuesta->assertOk();
        $this->assertSame(1, count($respuesta->viewData('visibles')));
    }

    public function test_la_pantalla_no_filtra_por_hoja_ajena(): void
    {
        $ejecucion = $this->ejecucion();

        $respuesta = $this->actingAs($this->encargado)
            ->get(route('validador.show', [$ejecucion, 'hoja' => 'NO-EXISTE', 'estados' => []]));

        $respuesta->assertOk();
        $this->assertSame([], $respuesta->viewData('visibles'));
    }
}
