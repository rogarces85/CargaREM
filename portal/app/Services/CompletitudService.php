<?php

namespace App\Services;

use App\Dominio\Alcance;
use App\Dominio\MatrizSeries;
use App\Models\Envio;
use App\Models\EnvioArchivo;
use App\Models\Establecimiento;
use App\Models\Serie;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Matriz de completitud del Servicio de Salud (T-F4-01, OP-09).
 *
 * Cruza los establecimientos con las series que la matriz les exige y con lo
 * que efectivamente llegó cada mes. Una celda responde a una pregunta: ¿este
 * establecimiento entregó esta serie, y en qué estado está?
 *
 * Se resuelve con dos consultas y el cruce en memoria, no con una por celda:
 * con 30 establecimientos y 7 series, una consulta por celda serían 210.
 */
class CompletitudService
{
    public const SIN_ENVIO = 'sin_envio';
    public const SIN_ARCHIVO = 'sin_archivo';
    public const CON_ERRORES = 'con_errores';
    public const EN_PROCESO = 'en_proceso';
    public const APROBADO = 'aprobado';

    public function __construct(
        private readonly MatrizSeries $matriz,
        private readonly Alcance $alcance,
    ) {
    }

    public const ETIQUETAS = [
        self::SIN_ENVIO    => 'Sin envío',
        self::SIN_ARCHIVO  => 'Sin archivo',
        self::CON_ERRORES  => 'Con errores',
        self::EN_PROCESO   => 'En proceso',
        self::APROBADO     => 'Aprobado',
    ];

    /**
     * @return array{
     *     filas: list<array{establecimiento: Establecimiento, celdas: array<int, array{estado: string, envio_id: ?int}>, aprobadas: int, exigidas: int}>,
     *     series: Collection<int, Serie>,
     *     totales: array<string, int>,
     *     resumen: array{establecimientos: int, aprobadas: int, exigidas: int}
     * }
     */
    public function matriz(int $anio, int $mes, User $usuario, ?int $establecimientoId = null): array
    {
        $cierre = $this->matriz->cierreDelPeriodo($anio, $mes);

        $establecimientos = $this->establecimientos($usuario, $establecimientoId, $cierre);

        if ($establecimientos->isEmpty()) {
            return [
                'filas'   => [],
                'series'  => collect(),
                'totales' => [],
                'resumen' => ['establecimientos' => 0, 'aprobadas' => 0, 'exigidas' => 0],
            ];
        }

        $envios = Envio::query()
            ->where('anio', $anio)
            ->where('mes', $mes)
            ->whereIn('establecimiento_id', $establecimientos->pluck('id'))
            ->with(['archivosVigentes' => fn ($q) => $q->select('id', 'envio_id', 'serie_id', 'estado')])
            ->get()
            ->keyBy('establecimiento_id');

        // Columnas: la unión de las series que alguien exige, en su orden.
        $series = $establecimientos
            ->flatMap(fn (Establecimiento $e) => $e->series)
            ->unique('id')
            ->sortBy('orden')
            ->values();

        $filas = [];
        $totales = array_fill_keys($series->pluck('id')->all(), 0);
        $aprobadasTotal = 0;
        $exigidasTotal = 0;

        foreach ($establecimientos as $establecimiento) {
            $exigidas = $establecimiento->series->pluck('id');
            $envio = $envios->get($establecimiento->id);

            $celdas = [];
            $aprobadas = 0;

            foreach ($series as $serie) {
                // Una serie se muestra solo si este establecimiento la debe.
                if (! $exigidas->contains($serie->id)) {
                    $celdas[$serie->id] = ['estado' => 'no_aplica', 'envio_id' => $envio?->id];

                    continue;
                }

                $estado = $this->estadoCelda($envio, $serie->id);
                $celdas[$serie->id] = ['estado' => $estado, 'envio_id' => $envio?->id];

                if ($estado === self::APROBADO) {
                    $aprobadas++;
                    $totales[$serie->id]++;
                }
            }

            $aprobadasTotal += $aprobadas;
            $exigidasTotal += $exigidas->count();

            $filas[] = [
                'establecimiento' => $establecimiento,
                'celdas'          => $celdas,
                'aprobadas'       => $aprobadas,
                'exigidas'        => $exigidas->count(),
                'fuera_de_plazo'  => $envio?->fuera_de_plazo === true,
            ];
        }

        return [
            'filas'   => $filas,
            'series'  => $series,
            'totales' => $totales,
            'resumen' => [
                'establecimientos' => $establecimientos->count(),
                'aprobadas'        => $aprobadasTotal,
                'exigidas'         => $exigidasTotal,
            ],
        ];
    }

    /**
     * Estado de una celda: qué pasó con esa serie en ese establecimiento.
     */
    public function estadoCelda(?Envio $envio, int $serieId): string
    {
        if ($envio === null) {
            return self::SIN_ENVIO;
        }

        $archivo = $envio->archivosVigentes->firstWhere('serie_id', $serieId);

        if ($archivo === null) {
            return self::SIN_ARCHIVO;
        }

        if ($archivo->estado === EnvioArchivo::CON_ERRORES) {
            return self::CON_ERRORES;
        }

        if ($envio->estado === Envio::APROBADO) {
            return self::APROBADO;
        }

        return self::EN_PROCESO;
    }

    /**
     * Establecimientos con sus series exigidas, dentro del alcance del usuario.
     *
     * La condición de la matriz la aplica `MatrizSeries`, con el cierre del
     * período que se está mirando (R-SER-03): así esta pantalla y V11 no pueden
     * volver a discrepar.
     *
     * El alcance lo resuelve `App\Dominio\Alcance` (R-ALC-01): el revisor ve
     * solo lo asignado y sin asignación no ve ninguno. Antes esta pantalla
     * filtraba por `servicio_id`, que daba al revisor el Servicio completo.
     *
     * @return Collection<int, Establecimiento>
     */
    private function establecimientos(User $usuario, ?int $establecimientoId, CarbonInterface $cierre): Collection
    {
        $consulta = Establecimiento::query()
            ->where('activo', true)
            ->with(['series' => function ($q) use ($cierre) {
                $this->matriz->aplicarVigencia($q, $cierre);
                $q->orderBy('orden');
            }])
            ->orderBy('nombre');

        if ($establecimientoId !== null) {
            $consulta->whereKey($establecimientoId);
        }

        $this->alcance->aplicar($consulta, $usuario);

        return $consulta->get();
    }
}