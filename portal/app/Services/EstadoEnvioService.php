<?php

namespace App\Services;

use App\Exceptions\EnvioInvalidoException;
use App\Models\Envio;
use App\Models\User;
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
     * revisión, ni «cargado» sin aprobación.
     */
    private const PERMITIDAS = [
        Envio::RECIBIDO    => [Envio::EN_REVISION, Envio::ANULADO],
        Envio::VALIDADO    => [Envio::EN_REVISION, Envio::ANULADO],
        Envio::CON_ERRORES => [Envio::ANULADO],
        Envio::EN_REVISION => [Envio::OBSERVADO, Envio::APROBADO],
        Envio::OBSERVADO   => [Envio::RECIBIDO, Envio::ANULADO],
        Envio::APROBADO    => [Envio::CARGADO, Envio::ANULADO],
        Envio::CARGADO     => [Envio::EN_REVISION],
        Envio::ANULADO     => [],
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

        if ($nuevo === Envio::ANULADO && trim((string) $motivo) === '') {
            throw EnvioInvalidoException::motivoRequerido('anular');
        }

        return DB::transaction(function () use ($envio, $nuevo, $actor, $motivo) {
            $anterior = $envio->estado;

            $envio->estado = $nuevo;
            $envio->revisor_id ??= in_array($nuevo, [Envio::EN_REVISION, Envio::OBSERVADO, Envio::APROBADO], true)
                ? $actor->id
                : $envio->revisor_id;

            match ($nuevo) {
                Envio::EN_REVISION => $envio->fecha_revision = now(),
                Envio::APROBADO    => $envio->fecha_aprobacion = now(),
                Envio::CARGADO     => $envio->fecha_carga = now(),
                default            => null,
            };

            $envio->save();

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
