<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\ValidadorEjecucion;
use App\Validador\DetalleResultado;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Detalle de un resultado (RF-040.5).
 *
 * Lo que se fija aquí:
 *
 * 1. **El mensaje del catálogo se segmenta** en REM | sección | detalle, y no
 *    se pierde nada si no encaja en el formato.
 * 2. **El operador se lee en palabras**: «debe ser menor o igual a» dice más
 *    que «<=» cuando alguien tiene que decidir si el dato está bien.
 * 3. **Un valor ausente no es cero**: se muestra «—», porque el cero es un
 *    dato y la ausencia no.
 * 4. **El detalle se abre por la URL y sobrevive a los filtros**: se puede
 *    compartir el enlace de una regla concreta.
 */
class DetalleResultadoTest extends TestCase
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

    // ─── Segmentación del mensaje ─────────────────────────────────────────

    public function test_segmenta_un_mensaje_simple(): void
    {
        $partes = DetalleResultado::segmentar(
            'REM A01 | SECCIÓN A: CONTROLES DE SALUD | Control Preconcepcional (celda F11)'
        );

        $this->assertCount(1, $partes);
        $this->assertSame('REM A01', $partes[0]['rem']);
        $this->assertSame('SECCIÓN A: CONTROLES DE SALUD', $partes[0]['secciones']);
        $this->assertSame('Control Preconcepcional (celda F11)', $partes[0]['detalle']);
    }

    /** Las reglas cruzadas encadenan dos bloques: uno por cada hoja. */
    public function test_segmenta_un_mensaje_compuesto_en_dos_partes(): void
    {
        $partes = DetalleResultado::segmentar(
            'REM A01 | SECCIÓN B | Controles realizado por Médico/a '
            . 'REM A05 | SECCIÓN E | Ingresos a control (celda C89)'
        );

        $this->assertCount(2, $partes, 'Dos hojas, dos bloques.');
        $this->assertSame('REM A01', $partes[0]['rem']);
        $this->assertSame('REM A05', $partes[1]['rem']);
        $this->assertSame('Ingresos a control (celda C89)', $partes[1]['detalle']);
    }

    /** Un mensaje que no sigue el patrón no se pierde: va entero al detalle. */
    public function test_un_mensaje_sin_formato_se_conserva_entero(): void
    {
        $partes = DetalleResultado::segmentar('Texto libre sin el patrón del catálogo');

        $this->assertCount(1, $partes);
        $this->assertSame('', $partes[0]['rem']);
        $this->assertSame('Texto libre sin el patrón del catálogo', $partes[0]['detalle']);
    }

    public function test_un_mensaje_vacio_no_rompe(): void
    {
        $partes = DetalleResultado::segmentar('');

        $this->assertCount(1, $partes);
        $this->assertSame('', $partes[0]['detalle']);
    }

    /** Las tildes y los saltos de línea del catálogo no rompen el patrón. */
    public function test_segmenta_con_tildes_y_saltos_de_linea(): void
    {
        $partes = DetalleResultado::segmentar(
            "REM P1 | SECCIÓN A: POBLACIÓN EN CONTROL | Detalle con ñ y áéíóú\n(celda B160)"
        );

        $this->assertCount(1, $partes);
        $this->assertSame('REM P1', $partes[0]['rem']);
        $this->assertStringContainsString('ñ y áéíóú', $partes[0]['detalle']);
    }

    // ─── Lectura de operadores ────────────────────────────────────────────

    public function test_los_operadores_se_leen_en_palabras(): void
    {
        $this->assertSame('debe ser igual a', DetalleResultado::leerOperador('=='));
        $this->assertSame('debe ser distinto de', DetalleResultado::leerOperador('!='));
        $this->assertSame('debe ser mayor que', DetalleResultado::leerOperador('>'));
        $this->assertSame('debe ser menor que', DetalleResultado::leerOperador('<'));
        $this->assertSame('debe ser mayor o igual a', DetalleResultado::leerOperador('>='));
        $this->assertSame('debe ser menor o igual a', DetalleResultado::leerOperador('<='));
    }

    public function test_un_operador_desconocido_se_muestra_tal_cual(): void
    {
        $this->assertSame('~', DetalleResultado::leerOperador('~'));
    }

    // ─── Presentación de valores ──────────────────────────────────────────

    /** El cero es un dato; la ausencia de dato no. */
    public function test_un_valor_ausente_se_muestra_como_guion(): void
    {
        $this->assertSame('—', DetalleResultado::numero(null));
        $this->assertSame('—', DetalleResultado::numero(''));
        $this->assertSame('0', DetalleResultado::numero(0));
        $this->assertSame('0', DetalleResultado::numero(0.0));
    }

    public function test_los_numeros_se_formatean_a_la_chilena(): void
    {
        $this->assertSame('1.500', DetalleResultado::numero(1500));
        $this->assertSame('15', DetalleResultado::numero(15.0));
        $this->assertSame('2,50', DetalleResultado::numero(2.5));
    }

    public function test_la_diferencia_lleva_su_signo(): void
    {
        $this->assertSame('+5', DetalleResultado::diferencia(5.0));
        $this->assertSame('-3', DetalleResultado::diferencia(-3.0));
        $this->assertSame('—', DetalleResultado::diferencia(null));
    }

    // ─── La pantalla ──────────────────────────────────────────────────────

    private function ejecucion(): ValidadorEjecucion
    {
        $resultados = [
            [
                'regla' => 'A01-VAL001', 'severidad' => 'REVISAR', 'estado' => 'CUMPLE',
                'mensaje' => 'REM A01 | SECCIÓN A | Control Preconcepcional (celda F11)',
                'hoja' => 'A01', 'hoja2' => null,
                'expresion1' => 'F11', 'valor1' => 0.0, 'operador' => '==',
                'expresion2' => '0', 'valor2' => 0.0, 'diferencia' => 0.0,
                'celdas' => ['A01!F11'], 'omision' => null,
                'evidencia' => 'F11 = 0; 0 = 0.', 'bloquea' => true,
            ],
            [
                'regla' => 'A05-VAL005', 'severidad' => 'ERROR', 'estado' => 'HALLAZGO',
                'mensaje' => 'REM A05 | SECCIÓN N | Ingresos al programa (celda C204)',
                'hoja' => 'A05', 'hoja2' => null,
                'expresion1' => 'C204', 'valor1' => 15.0, 'operador' => '<=',
                'expresion2' => 'C205+C206', 'valor2' => 10.0, 'diferencia' => 5.0,
                'celdas' => ['A05!C204', 'A05!C205'], 'omision' => null,
                'evidencia' => 'C204 = 15; C205+C206 = 10.', 'bloquea' => true,
            ],
            [
                'regla' => 'A08-VAL001a', 'severidad' => 'REVISAR', 'estado' => 'OMITIDA',
                'mensaje' => 'REM A08 | SECCIÓN A | Solo titular',
                'hoja' => 'A08', 'hoja2' => null,
                'expresion1' => 'E12', 'valor1' => 0.0, 'operador' => '>',
                'expresion2' => '0', 'valor2' => 0.0, 'diferencia' => null,
                'celdas' => ['A08!E12'], 'omision' => 'SECCION_SIN_ACTIVIDAD',
                'evidencia' => '', 'bloquea' => true,
            ],
        ];

        return ValidadorEjecucion::create([
            'usuario_id'      => $this->encargado->id,
            'nombre_original' => '123100A09.xlsm',
            'ruta'            => 'validador/x.xlsm',
            'sha256'          => str_repeat('a', 64),
            'tamano_bytes'    => 1024,
            'reglas_version'  => '2026.09',
            'hallazgos'       => 1,
            'bloqueantes'     => 1,
            'advertencias'    => 0,
            'duracion_ms'     => 1000,
            'estado'          => ValidadorEjecucion::CON_HALLAZGOS,
            'resultado'       => [
                'metadatos'  => ['nombre' => '123100A09.xlsm', 'codigo' => '123100', 'serie' => 'A', 'mes' => '09'],
                'resumen'    => ['evaluadas' => 2, 'cumplen' => 1, 'hallazgos' => 1, 'porcentaje' => 50.0, 'veredicto' => 'CON_OBSERVACIONES'],
                'resultados' => $resultados,
                'hallazgos'  => [$resultados[1]],
            ],
        ]);
    }

    public function test_sin_seleccion_no_hay_detalle(): void
    {
        $ejecucion = $this->ejecucion();

        $respuesta = $this->actingAs($this->encargado)->get(route('validador.show', $ejecucion));

        $respuesta->assertOk();
        $this->assertNull($respuesta->viewData('seleccionado'));
    }

    public function test_abrir_una_regla_trae_su_detalle(): void
    {
        $ejecucion = $this->ejecucion();

        $respuesta = $this->actingAs($this->encargado)
            ->get(route('validador.show', [$ejecucion, 'ver' => 'A05-VAL005']));

        $respuesta->assertOk();

        $seleccionado = $respuesta->viewData('seleccionado');
        $this->assertNotNull($seleccionado);
        $this->assertSame('A05-VAL005', $seleccionado['regla']);
        $this->assertSame(15, (int) $seleccionado['valor1']);
    }

    public function test_una_regla_inexistente_no_abre_nada(): void
    {
        $ejecucion = $this->ejecucion();

        $respuesta = $this->actingAs($this->encargado)
            ->get(route('validador.show', [$ejecucion, 'ver' => 'NO-EXISTE']));

        $respuesta->assertOk();
        $this->assertNull($respuesta->viewData('seleccionado'));
    }

    /**
     * Un enlace compartido tiene que abrir la regla aunque el filtro de partida
     * la deje fuera de la tabla: el detalle se busca sobre todos los resultados.
     */
    public function test_el_detalle_se_abre_aunque_el_filtro_lo_deje_fuera_de_la_tabla(): void
    {
        $ejecucion = $this->ejecucion();

        // A01-VAL001 cumple: con el filtro de partida (solo hallazgos) no está
        // en la tabla, pero su enlace tiene que abrir el detalle igual.
        $respuesta = $this->actingAs($this->encargado)
            ->get(route('validador.show', [$ejecucion, 'ver' => 'A01-VAL001']));

        $respuesta->assertOk();
        $this->assertNotNull($respuesta->viewData('seleccionado'), 'El detalle se abre igual.');
        $this->assertSame('A01-VAL001', $respuesta->viewData('seleccionado')['regla']);
    }

    /** Abrir un detalle no puede borrar lo que se venía filtrando. */
    public function test_abrir_un_detalle_conserva_la_busqueda(): void
    {
        $ejecucion = $this->ejecucion();

        $respuesta = $this->actingAs($this->encargado)->get(route('validador.show', [
            $ejecucion, 'q' => 'ingresos', 'ver' => 'A05-VAL005',
        ]));

        $respuesta->assertOk();
        $this->assertSame('ingresos', $respuesta->viewData('filtro')->busqueda);
        $this->assertSame('A05-VAL005', $respuesta->viewData('seleccionado')['regla']);
    }

    public function test_el_detalle_muestra_la_comparacion_en_palabras(): void
    {
        $ejecucion = $this->ejecucion();

        $respuesta = $this->actingAs($this->encargado)
            ->get(route('validador.show', [$ejecucion, 'ver' => 'A05-VAL005']));

        $respuesta->assertSee('debe ser menor o igual a', false);
        $respuesta->assertSee('Valor registrado', false);
        $respuesta->assertSee('Valor esperado', false);
        $respuesta->assertSee('C205+C206', false);
    }

    public function test_el_detalle_de_una_regla_omitida_explica_por_que(): void
    {
        $ejecucion = $this->ejecucion();

        $respuesta = $this->actingAs($this->encargado)
            ->get(route('validador.show', [$ejecucion, 'ver' => 'A08-VAL001a']));

        $respuesta->assertOk();
        $respuesta->assertSee('no trae registro este mes', false);
    }

    public function test_otro_usuario_no_ve_el_detalle_ajeno(): void
    {
        $otro = User::factory()->create(['activo' => true]);
        $otro->assignRole('encargado');

        $ejecucion = $this->ejecucion();

        $this->actingAs($otro)
            ->get(route('validador.show', [$ejecucion, 'ver' => 'A05-VAL005']))
            ->assertForbidden();
    }
}
