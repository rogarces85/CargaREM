<?php

namespace App\Services;

use App\Exceptions\EnvioInvalidoException;
use App\Models\Envio;
use App\Models\EnvioArchivo;
use App\Models\User;
use App\Services\Notificador;
use Illuminate\Support\Facades\DB;

/**
 * Máquina de estados del envío (T-F2-26, T-F2-27).
 *
 * Dos caminos distintos, a propósito:
 *
 * - `recalcular` es automático. Mira los archivos y decide si el envío está
 *   validado o con errores. No pregunta a nadie y no tiene actor.
 * - `transicionar` es una acción humana. Exige actor, motivo cuando
 *   corresponde, y deja rastro.
 *
 * Mezclarlos fue lo que en el sistema anterior permitía estados imposibles.
 */
class EstadoEnvioService
{
    /**
     * Transiciones que una persona puede ejecutar.
     *
     * Lo que no está aquí no ocurre: no hay estado «aprobado» sin pasar por
     * revisión. `aprobado` es el estado final del MVP v2: no hay reapertura ni
     * anulación.
     */
    private const PERMITIDAS = [
        Envio::RECIBIDO    => [Envio::EN_REVISION],
        Envio::VALIDADO    => [Envio::EN_REVISION],
        Envio::CON_ERRORES => [],
        Envio::EN_REVISION => [Envio::OBSERVADO, Envio::APROBADO],
        Envio::OBSERVADO   => [Envio::RECIBIDO],
        Envio::APROBADO    => [],
    ];

    /**
     * Recalcula el estado a partir de los archivos vigentes (OP-06).
     *
     * Solo actúa mientras el envío está en manos del establecimiento. Un envío
     * en revisión no cambia de estado porque alguien revalide.
     */
    public function recalcular(Envio $envio): string
    {
        if (! in_array($envio->estado, [Envio::RECIBIDO, Envio::VALIDADO, Envio::CON_ERRORES], true)) {
            return $envio->estado;
        }

        $archivos = $envio->archivosVigentes()->get();

        if ($archivos->isEmpty()) {
            return $this->fijar($envio, Envio::RECIBIDO);
        }

        // Cuenta archivos sin validar como pendientes, no como válidos: un
        // envío a medio validar no está validado.
        $sinValidar = $archivos->contains(
            fn ($a) => ! in_array($a->estado, [\App\Models\EnvioArchivo::VALIDO, \App\Models\EnvioArchivo::CON_ERRORES], true)
        );

        if ($sinValidar) {
            return $this->fijar($envio, Envio::RECIBIDO, 'Hay archivos sin validar.');
        }

        $hayBloqueantes = $envio->validaciones()->where('severidad', 'bloqueante')->exists()
            || $archivos->contains(fn ($a) => $a->estado === \App\Models\EnvioArchivo::CON_ERRORES);

        return $this->fijar(
            $envio,
            $hayBloqueantes ? Envio::CON_ERRORES : Envio::VALIDADO,
            $hayBloqueantes ? 'La validación dejó errores bloqueantes.' : null
        );
    }

    /**
     * Ejecuta una transición pedida por una persona (OP-07).
     */
    public function transicionar(Envio $envio, string $nuevo, User $actor, ?string $motivo = null): Envio
    {
        $permitidos = self::PERMITIDAS[$envio->estado] ?? [];

        if (! in_array($nuevo, $permitidos, true)) {
            throw EnvioInvalidoException::transicionNoPermitida($envio->estado, $nuevo);
        }

        $envio = DB::transaction(function () use ($envio, $nuevo, $actor, $motivo) {
            $anterior = $envio->estado;

            $envio->estado = $nuevo;
            $envio->revisor_id ??= in_array($nuevo, [Envio::EN_REVISION, Envio::OBSERVADO, Envio::APROBADO], true)
                ? $actor->id
                : $envio->revisor_id;

            // El plazo se evalúa una sola vez: cuando el envío llega por
            // primera vez a revisión. Sin fecha límite en el período, la marca
            // queda en null (D4 abierta).
            if ($nuevo === Envio::EN_REVISION
                && $envio->fecha_limite !== null
                && $envio->fuera_de_plazo === null
                && in_array($anterior, [Envio::RECIBIDO, Envio::VALIDADO, Envio::CON_ERRORES], true)) {
                $envio->fuera_de_plazo = $this->fueraDePlazo($envio);
            }

            match ($nuevo) {
                Envio::EN_REVISION => $envio->fecha_revision = now(),
                Envio::APROBADO    => $envio->fecha_aprobacion = now(),
                default            => null,
            };

            $envio->save();

            // El estado del archivo acompaña al del envío: aprobar cierra
            // también los archivos vigentes.
            if ($nuevo === Envio::APROBADO) {
                $envio->archivosVigentes()->update(['estado' => EnvioArchivo::APROBADO]);
            }

            $envio->transiciones()->create([
                'estado_anterior' => $anterior,
                'estado_nuevo'    => $nuevo,
                'actor_id'        => $actor->id,
                'motivo'          => $motivo,
            ]);

            AuditoriaService::registrarSobre('envio.transicion', $envio, [
                'de'     => $anterior,
                'a'      => $nuevo,
                'motivo' => $motivo,
            ]);

            return $envio;
        });

        // Se avisa fuera de la transacción: si el correo falla, el cambio de
        // estado ya está confirmado y no se pierde.
        $this->notificar($envio, $nuevo, $actor);

        return $envio;
    }

    /**
     * Envía el aviso que corresponde al estado nuevo.
     *
     * Un fallo del notificador no propaga: la transición ya ocurrió.
     */
    private function notificar(Envio $envio, string $nuevo, User $actor): void
    {
        $evento = match ($nuevo) {
            Envio::EN_REVISION => 'enviado_a_revision',
            Envio::OBSERVADO   => 'observado',
            Envio::APROBADO    => 'aprobado',
            default            => null,
        };

        if ($evento === null) {
            return;
        }

        try {
            app(Notificador::class)->enviar($envio->fresh(), $evento, $actor);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Aprueba el envío, con las guardas completas (T-F3-06).
     *
     * No alcanza con que el revisor tenga el permiso y el envío esté en
     * revisión: tiene que estar completo, sin bloqueantes y sin observaciones
     * abiertas. Aprobar algo a medias es lo que después aparece como dato
     * faltante en la consolidación.
     */
    public function aprobar(Envio $envio, User $actor, ?string $motivo = null): Envio
    {
        $this->verificarGuardas($envio);

        return $this->transicionar($envio, Envio::APROBADO, $actor, $motivo ?? 'Aprobado sin observaciones.');
    }

    private function verificarGuardas(Envio $envio): void
    {
        if ($envio->estado !== Envio::EN_REVISION) {
            throw EnvioInvalidoException::transicionNoPermitida($envio->estado, Envio::APROBADO);
        }

        $abiertas = $envio->observaciones()->where('resuelta', false)->count();

        if ($abiertas > 0) {
            throw EnvioInvalidoException::aprobacionConObservaciones($abiertas);
        }

        // Se mira el estado del archivo además de sus hallazgos: el estado es
        // el resultado de la última validación y vale aunque el detalle se
        // haya regenerado.
        $bloqueantes = $envio->archivos()->where('es_vigente', true)
            ->where(function ($q) {
                $q->where('estado', \App\Models\EnvioArchivo::CON_ERRORES)
                    ->orWhereHas('validaciones', fn ($v) => $v->where('severidad', 'bloqueante'));
            })
            ->count()
            + $envio->validaciones()->where('severidad', 'bloqueante')->count();

        if ($bloqueantes > 0) {
            throw EnvioInvalidoException::aprobacionConBloqueantes($bloqueantes);
        }

        $exigidas = $envio->establecimiento->series()
            ->wherePivot('obligatoria', true)
            ->pluck('series.id');

        $presentes = $envio->archivos()->where('es_vigente', true)->pluck('serie_id');

        $faltantes = $exigidas->diff($presentes);

        if ($faltantes->isNotEmpty()) {
            throw EnvioInvalidoException::aprobacionIncompleta($faltantes->count());
        }
    }

    /**
     * Devuelve el envío a `recibido` porque llegó una versión nueva.
     *
     * Lo llama la subida, con el usuario que subió como actor: aunque el
     * cambio sea consecuencia del reenvío, alguien lo provocó y la historia
     * tiene que decirlo.
     */
    public function marcarRecibido(Envio $envio, User $actor, string $motivo = 'Se subió una versión nueva.'): string
    {
        $envio->refresh();

        if ($envio->estado === Envio::RECIBIDO) {
            return $envio->estado;
        }

        return DB::transaction(function () use ($envio, $actor, $motivo) {
            $anterior = $envio->estado;

            $envio->update(['estado' => Envio::RECIBIDO]);

            $envio->transiciones()->create([
                'estado_anterior' => $anterior,
                'estado_nuevo'    => Envio::RECIBIDO,
                'actor_id'        => $actor->id,
                'motivo'          => $motivo,
            ]);

            AuditoriaService::registrarSobre('envio.reenvio', $envio, ['de' => $anterior, 'a' => Envio::RECIBIDO]);

            return Envio::RECIBIDO;
        });
    }

    /** ¿El envío llegó a revisión después de la fecha límite? */
    private function fueraDePlazo(Envio $envio): bool
    {
        if ($envio->fecha_limite === null) {
            return false;
        }

        return now()->toDateString() > $envio->fecha_limite->toDateString();
    }

    private function fijar(Envio $envio, string $nuevo, ?string $motivo = null): string
    {
        if ($envio->estado === $nuevo) {
            return $nuevo;
        }

        return DB::transaction(function () use ($envio, $nuevo, $motivo) {
            $anterior = $envio->estado;

            $envio->update(['estado' => $nuevo]);

            $envio->transiciones()->create([
                'estado_anterior' => $anterior,
                'estado_nuevo'    => $nuevo,
                'actor_id'        => null,
                'motivo'          => $motivo ?? 'Recalculado por el sistema.',
            ]);

            AuditoriaService::registrarSobre('envio.recalculado', $envio, ['de' => $anterior, 'a' => $nuevo]);

            return $nuevo;
        });
    }
}
