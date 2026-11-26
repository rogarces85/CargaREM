<?php

namespace App\Dominio;

use App\Models\Establecimiento;
use App\Models\Serie;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Qué series se le exigen a un establecimiento (matriz `establecimiento_serie`).
 *
 * Esta regla estaba escrita cuatro veces y las copias no coincidían: la
 * aprobación y la pantalla del envío no filtraban la vigencia, y las dos que sí
 * lo hacían la evaluaban contra `now()` en vez de contra el fin del período
 * informado. La consecuencia era un envío imposible de aprobar: V11 lo daba por
 * completo y `verificarGuardas()` seguía reclamando una serie vencida.
 *
 * Aquí vive la única versión de la condición. Cualquier lugar que necesite saber
 * «¿qué series debe este establecimiento?» llama a `exigidas()` o, si necesita
 * cargarlas en lote, pasa `aplicarVigencia()` al eager loading. Ninguno repite
 * el `where`.
 *
 * Reglas que implementa (docs/validacion/matriz-series-obligatorias.md):
 *
 * - **R-SER-03**: la vigencia se evalúa contra el último día del período
 *   informado, no contra la fecha en que corre la consulta. Un envío de
 *   septiembre se juzga con la matriz que regía el 30 de septiembre.
 * - **R-SER-04**: por lo mismo, cambiar la matriz hoy no reinterpreta un mes ya
 *   cerrado: mientras se evalúe un envío de ese mes, la fecha es la del mes.
 * - **R-SER-05**: sin fila, no obliga. Solo cuentan las filas del pivot.
 * - **R-SER-06**: `obligatoria = 0` significa «se acepta, no se cuenta».
 * - **R-SER-07**: una serie inactiva no se espera de nadie.
 * - **R-P-01** (v3): una serie con `meses_exigidos` solo se exige en esos
 *   meses. La P se informa en 6 y 12.
 *
 * **R-SER-01 (derivar del histórico de 12 meses) no está implementada**: exige
 * datos históricos que el portal no tiene. Al importar el catálogo se exigen
 * todas las series activas y el administrador ajusta desde la matriz.
 */
final class MatrizSeries
{
    /**
     * Series activas y exigidas a un establecimiento, en el orden del catálogo.
     *
     * @param  CarbonInterface|null  $periodo  último día del período evaluado;
     *                                         `null` usa la fecha de hoy
     * @return Collection<int, Serie>
     */
    public function exigidas(Establecimiento $establecimiento, ?CarbonInterface $periodo = null): Collection
    {
        $consulta = $establecimiento->series();

        $this->aplicarVigencia($consulta, $periodo);

        return $consulta
            ->orderBy('orden')
            ->orderBy('codigo')
            ->get();
    }

    /**
     * Aplica la condición completa a una consulta sobre la relación `series`.
     *
     * Existe para que el eager loading y el `withCount` usen exactamente la
     * misma condición que `exigidas()`: sin esto habría dos versiones del
     * `where`, que es justo el fallo que esta clase viene a cerrar.
     *
     * **Meses (R-P-01).** La serie P no se informa todos los meses: se exige
     * solo en los períodos de junio y diciembre. El mes se toma del ancla del
     * período —el cierre del mes informado—, no de la fecha en que corre la
     * consulta. Un período de septiembre se juzga con los meses de septiembre
     * aunque la pantalla se abra en diciembre.
     *
     * @param  Builder<Serie>|mixed  $consulta  consulta sobre la relación `series`
     */
    public function aplicarVigencia($consulta, ?CarbonInterface $periodo = null): void
    {
        $ancla = $periodo ?? now();
        $fecha = $ancla->toDateString();
        $mes = (int) $ancla->month;

        $consulta
            ->where('series.activo', true)
            ->wherePivot('obligatoria', true)
            // `null` = todos los meses. `whereJsonContains` se resuelve como
            // JSON_CONTAINS, que MariaDB 10.4 y MySQL 8 entienden igual.
            ->where(function ($q) use ($mes) {
                $q->whereNull('series.meses_exigidos')
                    ->orWhereJsonContains('series.meses_exigidos', $mes);
            })
            ->where(function ($q) use ($fecha) {
                $q->whereNull('establecimiento_serie.vigente_desde')
                    ->orWhere('establecimiento_serie.vigente_desde', '<=', $fecha);
            })
            ->where(function ($q) use ($fecha) {
                $q->whereNull('establecimiento_serie.vigente_hasta')
                    ->orWhere('establecimiento_serie.vigente_hasta', '>=', $fecha);
            });
    }

    /**
     * Solo los identificadores, para comparar contra lo que llegó.
     *
     * @return Collection<int, int>
     */
    public function idsExigidos(Establecimiento $establecimiento, ?CarbonInterface $periodo = null): Collection
    {
        return $this->exigidas($establecimiento, $periodo)->pluck('id');
    }

    /**
     * Último día del período que se está informando.
     *
     * Es el ancla de la vigencia (R-SER-03). Se usa el fin de mes y no la fecha
     * límite: la fecha límite puede ser un override manual del administrador y
     * la vigencia de la matriz no depende de cuándo vence la entrega.
     */
    public function cierreDelPeriodo(int $anio, int $mes): CarbonInterface
    {
        return now()
            ->setDate($anio, $mes, 1)
            ->endOfMonth()
            ->startOfDay();
    }
}
