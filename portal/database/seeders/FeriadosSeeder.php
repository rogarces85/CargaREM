<?php

namespace Database\Seeders;

use App\Models\Feriado;
use Illuminate\Database\Seeder;

/**
 * Feriados nacionales de Chile (Ley 19.973 y leyes especiales).
 *
 * **La lista es dato, no regla.** Se siembran los años verificados y el
 * administrador puede corregir o sumar desde la pantalla de Feriados. El
 * cálculo del plazo vive en `CalendarioService`, que lee esta tabla; si un año
 * no tiene feriados, el plazo se calcula solo con fines de semana y la pantalla
 * de calendario lo advierte.
 *
 * **2026** — 16 feriados nacionales. Contrastado contra la fuente oficial de
 * [feriados.cl](https://www.feriados.cl/) (Biblioteca del Congreso Nacional).
 *
 * **2027** — 17 feriados nacionales. Es uno más que 2026 porque la Ley 20.983
 * declara feriado el viernes 17 de septiembre cuando el 18 y el 19 caen sábado
 * y domingo, que es el caso de 2027. Además, San Pedro y San Pablo (29 de
 * junio) y el Encuentro de Dos Mundos (12 de octubre) se trasladan al lunes por
 * la Ley 19.668.
 *
 * `rem:feriados:cargar {anio}` usa el mismo mapa, y `rem:calendario --verificar`
 * comprueba que las fechas guardadas coincidan con las calculadas.
 */
class FeriadosSeeder extends Seeder
{
    /**
     * Feriados por año.
     *
     * @var array<int, array<string, string>>
     */
    public const FERIADOS = [
        2026 => [
            '2026-01-01' => 'Año Nuevo',
            '2026-04-03' => 'Viernes Santo',
            '2026-04-04' => 'Sábado Santo',
            '2026-05-01' => 'Día del Trabajo',
            '2026-05-21' => 'Día de las Glorias Navales',
            '2026-06-21' => 'Día Nacional de los Pueblos Indígenas',
            '2026-06-29' => 'San Pedro y San Pablo',
            '2026-07-16' => 'Día de la Virgen del Carmen',
            '2026-08-15' => 'Asunción de la Virgen',
            '2026-09-18' => 'Independencia Nacional',
            '2026-09-19' => 'Día de las Glorias del Ejército',
            '2026-10-12' => 'Encuentro de Dos Mundos',
            '2026-10-31' => 'Día de las Iglesias Evangélicas',
            '2026-11-01' => 'Todos los Santos',
            '2026-12-08' => 'Inmaculada Concepción',
            '2026-12-25' => 'Navidad',
        ],
        2027 => [
            '2027-01-01' => 'Año Nuevo',
            '2027-03-26' => 'Viernes Santo',
            '2027-03-27' => 'Sábado Santo',
            '2027-05-01' => 'Día del Trabajo',
            '2027-05-21' => 'Día de las Glorias Navales',
            '2027-06-21' => 'Día Nacional de los Pueblos Indígenas',
            '2027-06-28' => 'San Pedro y San Pablo',
            '2027-07-16' => 'Día de la Virgen del Carmen',
            '2027-08-15' => 'Asunción de la Virgen',
            '2027-09-17' => 'Feriado adicional de Fiestas Patrias (Ley 20.983)',
            '2027-09-18' => 'Independencia Nacional',
            '2027-09-19' => 'Día de las Glorias del Ejército',
            '2027-10-11' => 'Encuentro de Dos Mundos',
            '2027-10-31' => 'Día de las Iglesias Evangélicas',
            '2027-11-01' => 'Todos los Santos',
            '2027-12-08' => 'Inmaculada Concepción',
            '2027-12-25' => 'Navidad',
        ],
    ];

    /** Años con lista verificada. */
    public static function anios(): array
    {
        return array_keys(self::FERIADOS);
    }

    public function run(): void
    {
        foreach (self::FERIADOS as $anio => $feriados) {
            $this->sembrar($anio);
        }
    }

    /**
     * Carga un año. Idempotente: `updateOrCreate` y no `insert`, para poder
     * corregir un nombre sin duplicar la fila.
     *
     * @return int filas escritas
     */
    public function sembrar(int $anio): int
    {
        $feriados = self::FERIADOS[$anio] ?? null;

        if ($feriados === null) {
            return 0;
        }

        foreach ($feriados as $fecha => $nombre) {
            Feriado::updateOrCreate(
                ['fecha' => $fecha],
                ['nombre' => $nombre, 'ambito' => Feriado::NACIONAL]
            );
        }

        return count($feriados);
    }
}
