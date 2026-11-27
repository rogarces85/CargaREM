<?php

namespace App\Services;

use App\Dominio\Alcance;
use App\Exceptions\EnvioInvalidoException;
use App\Models\Envio;
use App\Models\Serie;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Solicitud de nueva subida (RF-044, D-043).
 *
 * El Equipo REM detecta un error y pide al encargado que vuelva a subir. Hay
 * tres alcances y cada uno responde a un caso real de la Unidad:
 *
 * - **serie**: un archivo concreto salió mal. Es el caso fino.
 * - **envio**: todo el período está mal y hay que rehacerlo.
 * - **asignados**: el problema es del mes, no de un establecimiento; se pide a
 *   todos los que revisa esa persona en ese período.
 *
 * En los tres casos el efecto es el mismo: una observación por serie afectada
 * —que se resuelve sola cuando el encargado sube la corrección— y, si el envío
 * ya estaba aprobado, una reapertura con motivo.
 *
 * **Por qué observaciones y no un estado nuevo.** La v2 ya resuelve
 * observaciones por serie al subir la versión siguiente. Un estado paralelo
 * habría obligado a duplicar esa resolución y a decidir qué pasa cuando se
 * cruzan los dos caminos.
 */
class SolicitudReenvioService
{
    public const ALCANCE_SERIE = 'serie';
    public const ALCANCE_ENVIO = 'envio';
    public const ALCANCE_ASIGNADOS = 'asignados';

    public function __construct(
        private readonly ObservacionService $observaciones,
        private readonly EstadoEnvioService $estados,
        private readonly Alcance $alcance,
    ) {
    }

    /**
     * Pide una nueva subida.
     *
     * @return array{envios: int, observaciones: int, reabiertos: int}
     */
    public function solicitar(
        User $autor,
        string $alcance,
        string $motivo,
        ?Envio $envio = null,
        ?Serie $serie = null,
        ?int $anio = null,
        ?int $mes = null,
    ): array {
        $motivo = trim($motivo);

        if (mb_strlen($motivo) < 10) {
            throw EnvioInvalidoException::reenvioSinMotivo();
        }

        return match ($alcance) {
            self::ALCANCE_SERIE => $this->unaSerie($autor, $envio, $serie, $motivo),
            self::ALCANCE_ENVIO => $this->unEnvio($autor, $envio, $motivo),
            self::ALCANCE_ASIGNADOS => $this->todosLosAsignados($autor, $anio, $mes, $motivo),
            default => throw EnvioInvalidoException::reenvioSinAlcance(),
        };
    }

    /**
     * Envíos del alcance del autor para un período.
     *
     * @return Collection<int, Envio>
     */
    public function enviosDelPeriodo(User $autor, int $anio, int $mes): Collection
    {
        return Envio::query()
            ->forUser($autor)
            ->where('anio', $anio)
            ->where('mes', $mes)
            ->with(['establecimiento', 'archivosVigentes.serie'])
            ->orderBy('establecimiento_id')
            ->get();
    }

    /** @return array{envios: int, observaciones: int, reabiertos: int} */
    private function unaSerie(User $autor, ?Envio $envio, ?Serie $serie, string $motivo): array
    {
        if ($envio === null || $serie === null) {
            throw EnvioInvalidoException::reenvioSinAlcance();
        }

        if (! $this->alcance->incluye($autor, $envio->establecimiento_id)) {
            throw EnvioInvalidoException::reenvioSinAlcance();
        }

        $reabierto = $this->reabrirSiHaceFalta($envio, $autor, $motivo);

        $this->registrarObservacion($envio, $autor, $serie, $motivo);

        return ['envios' => 1, 'observaciones' => 1, 'reabiertos' => $reabierto ? 1 : 0];
    }

    /** @return array{envios: int, observaciones: int, reabiertos: int} */
    private function unEnvio(User $autor, ?Envio $envio, string $motivo): array
    {
        if ($envio === null) {
            throw EnvioInvalidoException::reenvioSinAlcance();
        }

        if (! $this->alcance->incluye($autor, $envio->establecimiento_id)) {
            throw EnvioInvalidoException::reenvioSinAlcance();
        }

        $reabierto = $this->reabrirSiHaceFalta($envio, $autor, $motivo);

        $series = $envio->archivosVigentes()->with('serie')->get()->pluck('serie')->filter();

        // Un envío sin archivos no tiene series que observar: la observación
        // queda sobre el envío completo, que igual frena la aprobación.
        if ($series->isEmpty()) {
            $this->observaciones->registrar($envio, $autor, $this->texto($motivo, null));

            return ['envios' => 1, 'observaciones' => 1, 'reabiertos' => $reabierto ? 1 : 0];
        }

        foreach ($series as $serie) {
            $this->registrarObservacion($envio, $autor, $serie, $motivo);
        }

        return ['envios' => 1, 'observaciones' => $series->count(), 'reabiertos' => $reabierto ? 1 : 0];
    }

    /** @return array{envios: int, observaciones: int, reabiertos: int} */
    private function todosLosAsignados(User $autor, ?int $anio, ?int $mes, string $motivo): array
    {
        if ($anio === null || $mes === null) {
            throw EnvioInvalidoException::reenvioSinAlcance();
        }

        $envios = $this->enviosDelPeriodo($autor, $anio, $mes);

        if ($envios->isEmpty()) {
            throw EnvioInvalidoException::periodoInexistente($anio, $mes);
        }

        $resultado = ['envios' => 0, 'observaciones' => 0, 'reabiertos' => 0];

        DB::transaction(function () use ($envios, $autor, $motivo, &$resultado) {
            foreach ($envios as $envio) {
                // Los envíos que todavía no llegaron a revisión no necesitan
                // que se les pida nada: el encargado sigue trabajando en ellos.
                if (in_array($envio->estado, [Envio::RECIBIDO, Envio::VALIDADO, Envio::CON_ERRORES], true)) {
                    continue;
                }

                $parcial = $this->unEnvio($autor, $envio, $motivo);

                $resultado['envios']++;
                $resultado['observaciones'] += $parcial['observaciones'];
                $resultado['reabiertos'] += $parcial['reabiertos'];
            }
        });

        return $resultado;
    }

    /** Reabre si el envío está aprobado; si no, no hace nada. */
    private function reabrirSiHaceFalta(Envio $envio, User $autor, string $motivo): bool
    {
        if ($envio->estado !== Envio::APROBADO) {
            return false;
        }

        $this->estados->reabrir($envio, $autor, $motivo);

        return true;
    }

    private function registrarObservacion(Envio $envio, User $autor, Serie $serie, string $motivo): void
    {
        $archivo = $envio->archivosVigentes()->where('serie_id', $serie->id)->first();

        $this->observaciones->registrar($envio, $autor, $this->texto($motivo, $serie), $archivo);
    }

    private function texto(string $motivo, ?Serie $serie): string
    {
        $cabeza = $serie !== null
            ? "Vuelva a subir la serie {$serie->codigo}."
            : 'Vuelva a subir la estadística de este período.';

        return $cabeza . ' Motivo: ' . $motivo;
    }
}
