<?php

namespace Database\Seeders;

use App\Models\Feriado;
use Illuminate\Database\Seeder;

/**
 * Feriados nacionales de Chile (Ley 19.973).
 *
 * Se siembran los del año en curso. Los años siguientes se agregan con la
 * misma lista cuando el calendario oficial se publica; el administrador puede
 * corregir o sumar feriados desde la pantalla de Feriados.
 *
 * `rem:calendario` avisa cuando un año no tiene feriados cargados.
 */
class FeriadosSeeder extends Seeder
{
    /**
     * Feriados nacionales de 2026 (16 jornadas).
     *
     * @var array<string, string>
     */
    public const FERIADOS_2026 = [
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
    ];

    public function run(): void
    {
        foreach (self::FERIADOS_2026 as $fecha => $nombre) {
            Feriado::updateOrCreate(
                ['fecha' => $fecha],
                ['nombre' => $nombre, 'ambito' => Feriado::NACIONAL]
            );
        }
    }
}
