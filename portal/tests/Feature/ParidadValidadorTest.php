<?php

namespace Tests\Feature;

use App\Validador\ValidadorEstadistico;
use Tests\TestCase;

/**
 * Paridad del Validador con la herramienta de escritorio (RF-040).
 *
 * El Validador de escritorio de la Unidad genera archivos REM de prueba con un
 * índice que declara el estado esperado de cada regla: `cumple` (el archivo
 * debe pasar) y `rompe` (el archivo debe fallar en una regla concreta). Es el
 * único insumo real que existe para decir «este motor valida igual que aquel».
 *
 * **Las pruebas se saltan si no están los archivos.** No se versionan en este
 * repositorio porque son datos de la Unidad; se buscan en la ruta del proyecto
 * de escritorio y, si no están, el test se marca como omitido en lugar de
 * fallar. Un test que no se pudo ejecutar se informa como no ejecutado.
 *
 * La ruta se puede cambiar con `VALIDADOR_ESCRITORIO`.
 */
class ParidadValidadorTest extends TestCase
{
    private function basePruebas(): string
    {
        return rtrim(
            env('VALIDADOR_ESCRITORIO', 'C:\xampp\htdocs\www\Validador2026\Validador2026'),
            '\\/'
        ) . DIRECTORY_SEPARATOR . 'pruebas-generadas';
    }

    private function disponible(): bool
    {
        return is_file($this->basePruebas() . DIRECTORY_SEPARATOR . 'indice.json');
    }

    /**
     * Corre el motor sobre los archivos de prueba y compara el estado de cada
     * regla objetivo con el que declara el índice.
     *
     * @return array{total: int, aciertos: int, fallos: list<string>}
     */
    private function comparar(): array
    {
        $base = $this->basePruebas();
        $indice = json_decode((string) file_get_contents($base . '/indice.json'), true);

        $porArchivo = [];

        foreach ($indice['filas'] as $fila) {
            if (! ($fila['esObjetivo'] ?? false)) {
                continue;
            }

            $clave = $fila['escenario'] . '/' . $fila['carpeta'] . '/' . $fila['archivo'];
            $porArchivo[$clave][] = $fila;
        }

        $motor = app(ValidadorEstadistico::class);
        $total = 0;
        $aciertos = 0;
        $fallos = [];

        foreach ($porArchivo as $clave => $esperadas) {
            $ruta = $base . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $clave);

            if (! is_file($ruta)) {
                $fallos[] = "No existe el archivo de prueba {$clave}.";

                continue;
            }

            try {
                $resultado = $motor->validar($ruta, basename($ruta));
            } catch (\Throwable $e) {
                $fallos[] = "{$clave}: no se pudo validar — {$e->getMessage()}";

                continue;
            }

            $estados = [];

            foreach ($resultado->resultados as $r) {
                $estados[$r->reglaId] = $r->estado;
            }

            foreach ($esperadas as $esperada) {
                $total++;
                $obtenido = $estados[$esperada['reglaId']] ?? 'AUSENTE';
                $esperado = strtoupper($esperada['estado']);

                if ($obtenido === $esperado) {
                    $aciertos++;

                    continue;
                }

                $fallos[] = sprintf(
                    '%s · %s: esperado %s, obtenido %s',
                    $clave,
                    $esperada['reglaId'],
                    $esperado,
                    $obtenido,
                );
            }
        }

        return ['total' => $total, 'aciertos' => $aciertos, 'fallos' => $fallos];
    }

    public function test_los_archivos_de_prueba_del_escritorio_estan_disponibles(): void
    {
        $this->assertTrue(
            $this->disponible(),
            'No se encontraron los archivos de prueba del Validador de escritorio en ' . $this->basePruebas()
            . '. Definir VALIDADOR_ESCRITORIO si están en otra ruta.',
        );
    }

    /**
     * Diferencias conocidas y aceptadas, con su motivo.
     *
     * Una diferencia documentada no se esconde: se declara, se explica y se
     * deja de vigilar. Aparecer una **nueva** es lo que rompe el test.
     *
     * @var array<string, string>
     */
    private const DIFERENCIAS_CONOCIDAS = [
        // El catálogo es más nuevo que los archivos de prueba.
        //
        // `reglas_finales.json` se regeneró el 2026-09-09; las pruebas se
        // generaron el 2026-08-12. Entre esas fechas el catálogo cambió
        // A04-VAL006 de `==` a `>=` (commit `cf5676f`, «Despacho Completo y
        // Oportuno para tipo Crónica es un subconjunto de Despacho Completo»).
        // Las pruebas siguen esperando el operador viejo, así que el motor
        // actual —que aplica el nuevo— acierta y la expectativa está obsoleta.
        //
        // Se resuelve regenerando las pruebas en el proyecto de escritorio.
        'A04-VAL006' => 'El catálogo cambió el operador a >= (2026-09-09); las pruebas son de 2026-08-12.',
    ];

    public function test_el_motor_acierta_el_estado_de_cada_regla_objetivo(): void
    {
        if (! $this->disponible()) {
            $this->markTestSkipped('Los archivos de prueba del Validador de escritorio no están disponibles.');
        }

        $r = $this->comparar();

        $this->assertGreaterThan(200, $r['total'], 'El índice debería traer más de 200 comparaciones');

        // Se separan los fallos que ya conocemos de los que no.
        $nuevos = [];
        $conocidos = 0;

        foreach ($r['fallos'] as $fallo) {
            $regla = $this->reglaDelFallo($fallo);

            if ($regla !== null && isset(self::DIFERENCIAS_CONOCIDAS[$regla])) {
                $conocidos++;

                continue;
            }

            $nuevos[] = $fallo;
        }

        $this->assertSame(
            [],
            $nuevos,
            sprintf(
                "Paridad %d/%d (%d diferencia(s) conocida(s)). Diferencias NUEVAS:\n%s",
                $r['aciertos'],
                $r['total'],
                $conocidos,
                implode("\n", $nuevos),
            ),
        );
    }

    public function test_las_diferencias_conocidas_siguen_siendo_solo_esas(): void
    {
        if (! $this->disponible()) {
            $this->markTestSkipped('Los archivos de prueba del Validador de escritorio no están disponibles.');
        }

        $r = $this->comparar();
        $presentes = [];

        foreach ($r['fallos'] as $fallo) {
            $regla = $this->reglaDelFallo($fallo);

            if ($regla !== null) {
                $presentes[$regla] = true;
            }
        }

        // Si una diferencia conocida dejó de aparecer —porque se regeneraron
        // las pruebas—, esta prueba avisa: hay que borrarla de la lista para
        // no dejar una excusa que ya no hace falta.
        $sobrantes = array_diff_key(self::DIFERENCIAS_CONOCIDAS, $presentes);

        $this->assertSame(
            [],
            array_keys($sobrantes),
            'Estas diferencias ya no ocurren: quítelas de DIFERENCIAS_CONOCIDAS. '
            . 'Motivos registrados: ' . json_encode($sobrantes, JSON_UNESCAPED_UNICODE),
        );
    }

    /** Extrae el identificador de regla de una línea de fallo. */
    private function reglaDelFallo(string $fallo): ?string
    {
        return preg_match('/([A-Z0-9]+-VAL[0-9]+[a-z]?):/', $fallo, $m) ? $m[1] : null;
    }

    /**
     * Un archivo de prueba marcado «cumple» no da veredicto APROBADO, y eso es
     * correcto: el generador escribe los valores de las reglas **objetivo** y
     * deja el resto del libro en cero. Una regla que compara una celda con
     * datos contra un rango vacío salta, aunque no sea la que el escenario
     * pretendía probar.
     *
     * Lo que sí se puede afirmar: **las reglas objetivo de ese archivo cumplen
     * todas**.
     */
    public function test_las_reglas_objetivo_de_un_archivo_que_cumple_cumplen(): void
    {
        if (! $this->disponible()) {
            $this->markTestSkipped('Los archivos de prueba del Validador de escritorio no están disponibles.');
        }

        $base = $this->basePruebas();
        $indice = json_decode((string) file_get_contents($base . '/indice.json'), true);
        $ruta = $base . DIRECTORY_SEPARATOR . 'cumple' . DIRECTORY_SEPARATOR . '01-123100-A' . DIRECTORY_SEPARATOR . '123100A01.xlsx';

        if (! is_file($ruta)) {
            $this->markTestSkipped('Falta el archivo de prueba ' . $ruta);
        }

        $resultado = app(ValidadorEstadistico::class)->validar($ruta, '123100A01.xlsx');

        $estados = [];
        foreach ($resultado->resultados as $r) {
            $estados[$r->reglaId] = $r->estado;
        }

        $incumplidas = [];

        foreach ($indice['filas'] as $fila) {
            if ($fila['escenario'] !== 'cumple' || $fila['carpeta'] !== '01-123100-A') {
                continue;
            }

            if (! ($fila['esObjetivo'] ?? false) || strtoupper($fila['estado']) !== 'CUMPLE') {
                continue;
            }

            if (isset(self::DIFERENCIAS_CONOCIDAS[$fila['reglaId']])) {
                continue;
            }

            $obtenido = $estados[$fila['reglaId']] ?? 'AUSENTE';

            if ($obtenido !== 'CUMPLE') {
                $incumplidas[] = "{$fila['reglaId']}: {$obtenido}";
            }
        }

        $this->assertSame([], $incumplidas, 'Reglas objetivo que no cumplen: ' . implode(', ', $incumplidas));
        $this->assertGreaterThan(0, $resultado->resumen->evaluadas);
    }
}
