<?php

namespace App\Services;

use App\Dominio\Alcance;
use App\Models\Envio;
use App\Models\EnvioArchivo;
use App\Models\Establecimiento;
use App\Models\User;
use App\Models\ValidadorEjecucion;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Indicadores del panel (RF-048, D-048).
 *
 * Cada rol ve lo suyo y **solo lo suyo**: las consultas pasan por
 * `App\Dominio\Alcance`, igual que la bandeja. El administrador ve el Servicio
 * completo; el Equipo REM, sus establecimientos; el encargado, los suyos.
 *
 * Dos reglas de la casa que este servicio respeta:
 *
 * 1. **Nada inventado.** Cada cifra sale de una consulta real; si no hay datos,
 *    el indicador muestra 0 y la pantalla lo dice, no un número de relleno.
 * 2. **Presupuesto acotado.** Se resuelve con un puñado de consultas agregadas,
 *    no una por celda, y se limita el rango a los últimos doce períodos.
 *
 * Los gráficos del cliente leen `series` y `categorias`; el mismo dato viaja en
 * `filas` para que la pantalla siga siendo legible sin JavaScript.
 */
class PanelService
{
    /** Cuántos meses hacia atrás se muestran en las series de tiempo. */
    private const MESES = 12;

    public function __construct(private readonly Alcance $alcance)
    {
    }

    /**
     * Todo lo que la vista necesita, según el rol.
     *
     * @return array{
     *     rol: string,
     *     tarjetas: list<array<string, mixed>>,
     *     graficos: list<array<string, mixed>>,
     *     avisos: list<string>
     * }
     */
    public function para(User $usuario): array
    {
        if ($usuario->hasRole('admin')) {
            return $this->administrador($usuario);
        }

        if ($usuario->hasRole('revisor')) {
            return $this->equipoRem($usuario);
        }

        return $this->encargado($usuario);
    }

    // ---------------------------------------------------------------- roles

    /** @return array<string, mixed> */
    private function encargado(User $usuario): array
    {
        $base = Envio::query()->forUser($usuario);

        $porEstado = $this->porEstado(clone $base);
        $total = array_sum($porEstado);

        $fuera = (clone $base)->where('fuera_de_plazo', true)->count();
        $aperturas = (clone $base)->whereNotNull('apertura_id')->count();

        $subidas = $this->subidasPorMes($usuario);

        $tarjetas = [
            $this->tarjeta('file-up', 'Envíos del año', $this->delAnio(clone $base), 'info', 'en todos los períodos'),
            $this->tarjeta('circle-check', 'Aprobados', $porEstado[Envio::APROBADO] ?? 0, 'ok', 'de ' . $total . ' envíos'),
            $this->tarjeta('clock', 'Fuera de plazo', $fuera, $fuera > 0 ? 'danger' : 'ok',
                $aperturas > 0 ? $aperturas . ' con apertura autorizada' : 'sin excepciones'),
            $this->tarjeta('chart-column', 'Subidas en 12 meses', array_sum($subidas['datos']), 'info', 'archivos guardados'),
        ];

        $graficos = [
            $this->grafico(
                'envios-estado',
                'Envíos por estado',
                'barra',
                ['Envíos'],
                array_keys($porEstado),
                array_values($porEstado),
                $this->resumenMapa($porEstado),
            ),
            $this->grafico(
                'subidas-mes',
                'Subidas por mes',
                'area',
                ['Archivos'],
                $subidas['etiquetas'],
                $subidas['datos'],
                $subidas['resumen'],
            ),
        ];

        return [
            'rol'     => 'encargado',
            'tarjetas' => $tarjetas,
            'graficos' => $graficos,
            'avisos'  => $this->avisosDeAsignacion($usuario),
        ];
    }

    /** @return array<string, mixed> */
    private function equipoRem(User $usuario): array
    {
        $base = Envio::query()->forUser($usuario);

        $porEstado = $this->porEstado(clone $base);
        $total = array_sum($porEstado);

        $visados = (clone $base)
            ->whereNotNull('fecha_revision')
            ->whereNotNull('fecha_envio')
            ->get(['fecha_envio', 'fecha_revision']);

        $horas = $visados
            ->filter(fn (Envio $e) => $e->fecha_revision->greaterThan($e->fecha_envio))
            ->map(fn (Envio $e) => $e->fecha_envio->diffInHours($e->fecha_revision))
            ->median();

        $porComuna = $this->porComuna($usuario);

        $tarjetas = [
            $this->tarjeta('clipboard-check', 'Pendientes de revisión', $porEstado[Envio::EN_REVISION] ?? 0,
                ($porEstado[Envio::EN_REVISION] ?? 0) > 0 ? 'warn' : 'ok', 'en sus establecimientos'),
            $this->tarjeta('circle-check', 'Aprobados', $porEstado[Envio::APROBADO] ?? 0, 'ok', 'de ' . $total . ' envíos'),
            $this->tarjeta('triangle-exclamation-point', 'Observados', $porEstado[Envio::OBSERVADO] ?? 0, 'warn',
                'esperando corrección'),
            $this->tarjeta('clock', 'Tiempo medio de revisión',
                $horas !== null ? round((float) $horas) . ' h' : '—', 'info', 'desde que llega hasta que se revisa'),
        ];

        $graficos = [
            $this->grafico(
                'rem-estado',
                'Envíos por estado',
                'barra',
                ['Envíos'],
                array_keys($porEstado),
                array_values($porEstado),
                $this->resumenMapa($porEstado),
            ),
            $this->grafico(
                'rem-comuna',
                'Establecimientos por comuna',
                'barra',
                ['Establecimientos'],
                $porComuna['etiquetas'],
                $porComuna['datos'],
                $porComuna['resumen'],
            ),
        ];

        return [
            'rol'     => 'revisor',
            'tarjetas' => $tarjetas,
            'graficos' => $graficos,
            'avisos'  => $this->avisosDeAsignacion($usuario),
        ];
    }

    /** @return array<string, mixed> */
    private function administrador(User $usuario): array
    {
        $base = Envio::query();

        $porEstado = $this->porEstado(clone $base);
        $total = array_sum($porEstado);

        $establecimientos = Establecimiento::where('activo', true)->count();
        $fuera = (clone $base)->where('fuera_de_plazo', true)->count();
        $sinRevisor = $this->establecimientosSinRevisor();

        $porComuna = $this->porComuna($usuario);

        $tarjetas = [
            $this->tarjeta('building', 'Establecimientos activos', $establecimientos, 'info',
                $sinRevisor . ' sin revisor asignado'),
            $this->tarjeta('file-up', 'Envíos del año', $this->delAnio(clone $base), 'info', 'en todos los períodos'),
            $this->tarjeta('circle-check', 'Aprobados', $porEstado[Envio::APROBADO] ?? 0, 'ok', 'de ' . $total . ' envíos'),
            $this->tarjeta('clock', 'Fuera de plazo', $fuera, $fuera > 0 ? 'danger' : 'ok', 'marcados por el sistema'),
        ];

        $graficos = [
            $this->grafico(
                'admin-estado',
                'Envíos por estado',
                'barra',
                ['Envíos'],
                array_keys($porEstado),
                array_values($porEstado),
                $this->resumenMapa($porEstado),
            ),
            $this->grafico(
                'admin-comuna',
                'Establecimientos por comuna',
                'barra',
                ['Establecimientos'],
                $porComuna['etiquetas'],
                $porComuna['datos'],
                $porComuna['resumen'],
            ),
        ];

        $avisos = [];

        if ($sinRevisor > 0) {
            $avisos[] = "Hay {$sinRevisor} establecimiento(s) sin revisor asignado: su estadística no le llega a nadie del Equipo REM.";
        }

        $sinAsignar = User::role(['revisor', 'encargado'])->whereDoesntHave('establecimientos')->count();

        if ($sinAsignar > 0) {
            $avisos[] = "Hay {$sinAsignar} usuario(s) del equipo sin establecimientos: no ven ningún dato hasta que se les asigne.";
        }

        $usos = ValidadorEjecucion::whereYear('created_at', now()->year)->count();

        if ($usos > 0) {
            $avisos[] = "El Validador se usó {$usos} vez/veces este año. El detalle está en Validador → Registro de uso.";
        }

        return [
            'rol'     => 'admin',
            'tarjetas' => $tarjetas,
            'graficos' => $graficos,
            'avisos'  => $avisos,
        ];
    }

    // ------------------------------------------------------------- consultas

    /**
     * Envíos por estado, en el orden del flujo.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Envio>  $consulta
     * @return array<string, int>
     */
    private function porEstado($consulta): array
    {
        $filas = $consulta
            ->selectRaw('estado, COUNT(*) as total')
            ->groupBy('estado')
            ->pluck('total', 'estado')
            ->all();

        $resultado = [];

        foreach (Envio::ESTADOS as $estado) {
            $resultado[$estado] = (int) ($filas[$estado] ?? 0);
        }

        return $resultado;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Envio>  $consulta
     */
    private function delAnio($consulta): int
    {
        return $consulta->where('anio', (int) now()->year)->count();
    }

    /**
     * Archivos subidos por mes en los últimos doce meses, del alcance del
     * usuario. Se cuenta por envío para no repetir la consulta por archivo.
     *
     * @return array{etiquetas: list<string>, datos: list<int>, resumen: string}
     */
    private function subidasPorMes(User $usuario): array
    {
        $desde = now()->copy()->subMonths(self::MESES - 1)->startOfMonth();

        $envios = Envio::query()->forUser($usuario)->select('id')->pluck('id');

        $filas = EnvioArchivo::query()
            ->whereIn('envio_id', $envios)
            ->where('created_at', '>=', $desde)
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as mes, COUNT(*) as total")
            ->groupBy('mes')
            ->pluck('total', 'mes')
            ->all();

        $etiquetas = [];
        $datos = [];

        for ($i = 0; $i < self::MESES; $i++) {
            $mes = $desde->copy()->addMonths($i);
            $clave = $mes->format('Y-m');

            $etiquetas[] = $mes->translatedFormat('M y');
            $datos[] = (int) ($filas[$clave] ?? 0);
        }

        return [
            'etiquetas' => $etiquetas,
            'datos'     => $datos,
            'resumen'   => 'Total del período: ' . array_sum($datos) . ' archivo(s).',
        ];
    }

    /**
     * Establecimientos del alcance agrupados por comuna.
     *
     * Se agrupa por la columna y se normaliza en PHP: MariaDB no acepta la
     * expresión `COALESCE(...)` como criterio de agrupación con el modo
     * estricto, y una consulta que depende del modo del servidor no vale.
     *
     * @return array{etiquetas: list<string>, datos: list<int>, resumen: string}
     */
    private function porComuna(User $usuario): array
    {
        $consulta = Establecimiento::query()
            ->where('activo', true)
            ->selectRaw('comuna, COUNT(*) as total')
            ->groupBy('comuna');

        $this->alcance->aplicar($consulta, $usuario);

        $filas = [];

        foreach ($consulta->pluck('total', 'comuna')->all() as $comuna => $total) {
            $clave = $comuna !== null && trim((string) $comuna) !== '' ? (string) $comuna : 'Sin comuna';
            $filas[$clave] = ($filas[$clave] ?? 0) + (int) $total;
        }

        arsort($filas);

        return [
            'etiquetas' => array_keys($filas),
            'datos'     => array_values($filas),
            'resumen'   => $filas === []
                ? 'No hay establecimientos en su alcance.'
                : collect($filas)->map(fn ($total, $comuna) => "{$comuna}: {$total}")->implode(', '),
        ];
    }

    /** Establecimientos activos sin ningún revisor asignado. */
    private function establecimientosSinRevisor(): int
    {
        return Establecimiento::query()
            ->where('activo', true)
            ->whereDoesntHave('usuarios', fn ($q) => $q->whereHas('roles', fn ($r) => $r->where('name', 'revisor')))
            ->count();
    }

    // -------------------------------------------------------------- formato

    /**
     * @return array<string, mixed>
     */
    private function tarjeta(string $icono, string $rotulo, mixed $valor, string $tono, ?string $detalle = null): array
    {
        return compact('icono', 'rotulo', 'valor', 'tono', 'detalle');
    }

    /**
     * @param  list<string>  $series
     * @param  list<string>  $categorias
     * @param  list<int|float>  $valores
     * @return array<string, mixed>
     */
    private function grafico(
        string $id,
        string $titulo,
        string $tipo,
        array $series,
        array $categorias,
        array $valores,
        string $resumen,
    ): array {
        return [
            'id'         => $id,
            'titulo'     => $titulo,
            'tipo'       => $tipo,
            'series'     => $series,
            'categorias' => $categorias,
            'valores'    => $valores,
            'resumen'    => $resumen,
        ];
    }

    /** @param  array<string, int>  $mapa */
    private function resumenMapa(array $mapa): string
    {
        $partes = [];

        foreach ($mapa as $estado => $total) {
            if ($total > 0) {
                $partes[] = \App\Support\EstadoUi::etiqueta($estado) . ': ' . $total;
            }
        }

        return $partes === [] ? 'Sin envíos todavía.' : implode(', ', $partes);
    }

    /** @return list<string> */
    private function avisosDeAsignacion(User $usuario): array
    {
        if (! $this->alcance->sinAsignacion($usuario)) {
            return [];
        }

        return [
            'No tiene establecimientos asignados, así que su panel está vacío. '
            . 'Pídale al administrador que le asigne al menos uno en Administración → Asignaciones.',
        ];
    }
}
