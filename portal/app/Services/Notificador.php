<?php

namespace App\Services;

use App\Dominio\MatrizSeries;
use App\Models\Envio;
use App\Models\Serie;
use App\Models\User;
use App\Notifications\EnvioNotificacion;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Avisos de los movimientos del envío (T-F3-10, RF-041, OP-17).
 *
 * Reglas:
 * 1. Nunca se avisa a quien provocó el cambio.
 * 2. El aviso vive en la tabla `notifications`: la campana de la cabecera lo
 *    muestra. No hay correo de notificación.
 * 3. Si no hay a quién avisar, no es un error: se deja constancia.
 * 4. **v3:** los avisos de revisión llegan a los revisores **asignados** al
 *    establecimiento; sin asignación, al administrador (R-ALC-01).
 * 5. **v3:** una tanda de subidas produce **un** aviso por destinatario y
 *    envío, actualizado durante 10 minutos (RF-041.3). Seis archivos subidos
 *    seguidos no son seis avisos: son uno que pasa de «1 de 6» a «6 de 6».
 */
class Notificador
{
    public function __construct(private readonly MatrizSeries $matriz)
    {
    }

    /**
     * @return array{enviados: int, destinatarios: list<string>}
     */
    public function enviar(Envio $envio, string $evento, ?User $actor = null, array $extra = []): array
    {
        $envio->loadMissing('establecimiento', 'archivosVigentes');

        $destinatarios = $this->destinatarios($envio, $evento, $actor);

        if ($destinatarios === []) {
            Log::info('Sin destinatarios para la notificación.', [
                'envio' => $envio->id,
                'evento' => $evento,
            ]);

            return ['enviados' => 0, 'destinatarios' => []];
        }

        $enviados = 0;
        $avisados = [];

        foreach ($destinatarios as $usuario) {
            try {
                $usuario->notify(new EnvioNotificacion($envio, $evento, $actor, $extra));
                $enviados++;
                $avisados[] = $usuario->email;
            } catch (Throwable $e) {
                // El estado ya cambió; el aviso es secundario.
                report($e);
                Log::warning('No se pudo registrar la notificación.', [
                    'envio'  => $envio->id,
                    'evento' => $evento,
                    'para'   => $usuario->email,
                    'error'  => $e->getMessage(),
                ]);
            }
        }

        AuditoriaService::registrarSobre('envio.notificado', $envio, [
            'evento'        => $evento,
            'destinatarios' => count($avisados),
        ]);

        return ['enviados' => $enviados, 'destinatarios' => $avisados];
    }

    /**
     * Avisa al Equipo REM de que el encargado subió una serie (RF-041).
     *
     * Calcula en el mismo lugar si con esa subida quedaron todas las series
     * exigidas, y elige el evento:
     *
     * - `subida_serie`: falta alguna (`alcance = serie`).
     * - `subida_completa`: están todas las del período (`alcance = completo`).
     *
     * Los avisos de subida de un mismo envío, destinatario y ventana de diez
     * minutos se **actualizan** en vez de acumularse: el revisor quiere saber
     * en qué va el envío, no cuántas veces se guardó un archivo.
     *
     * @return array{enviados: int, destinatarios: list<string>, alcance: string}
     */
    public function subida(Envio $envio, Serie $serie, User $actor): array
    {
        $envio->loadMissing('establecimiento');

        $cierre = $this->matriz->cierreDelPeriodo((int) $envio->anio, (int) $envio->mes);
        $exigidas = $this->matriz->exigidas($envio->establecimiento, $cierre);

        $vigentes = $envio->archivosVigentes()->with('serie')->get();
        $codigosVigentes = $vigentes->map(fn ($archivo) => $archivo->serie?->codigo)->filter()->values();

        $faltan = $exigidas
            ->reject(fn (Serie $exigida) => $codigosVigentes->contains($exigida->codigo))
            ->values();

        $completo = $exigidas->isNotEmpty() && $faltan->isEmpty();

        $evento = $completo ? 'subida_completa' : 'subida_serie';

        $extra = [
            'series'    => [$serie->codigo],
            'presentes' => $codigosVigentes->count(),
            'exigidas'  => $exigidas->count(),
            'faltantes' => $faltan->pluck('codigo')->values()->all(),
            'alcance'   => $completo ? 'completo' : 'serie',
        ];

        return $this->enviarSubida($envio, $evento, $actor, $extra);
    }

    /**
     * Envía o actualiza el aviso de subida.
     *
     * @param  array<string, mixed>  $extra
     * @return array{enviados: int, destinatarios: list<string>, alcance: string}
     */
    private function enviarSubida(Envio $envio, string $evento, User $actor, array $extra): array
    {
        $destinatarios = $this->destinatarios($envio, $evento, $actor);
        $avisados = [];

        foreach ($destinatarios as $usuario) {
            try {
                $existente = $this->avisoDeSubidaAbierto($usuario, $envio);

                if ($existente !== null) {
                    // Se reescribe el aviso abierto: la campana muestra el
                    // estado actual del envío, no la última vez que se guardó.
                    $nueva = (new EnvioNotificacion($envio, $evento, $actor, $extra))->toArray($usuario);
                    $existente->update([
                        'data'       => $nueva,
                        'updated_at' => now(),
                    ]);

                    $avisados[] = $usuario->email;

                    continue;
                }

                $usuario->notify(new EnvioNotificacion($envio, $evento, $actor, $extra));
                $avisados[] = $usuario->email;
            } catch (Throwable $e) {
                report($e);
                Log::warning('No se pudo registrar el aviso de subida.', [
                    'envio' => $envio->id,
                    'para'  => $usuario->email,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($avisados !== []) {
            AuditoriaService::registrarSobre('envio.notificado', $envio, [
                'evento'        => $evento,
                'destinatarios' => count($avisados),
            ]);
        }

        return [
            'enviados'      => count($avisados),
            'destinatarios' => $avisados,
            'alcance'       => $extra['alcance'] ?? ($evento === 'subida_completa' ? 'completo' : 'serie'),
        ];
    }

    /** Aviso de subida sin leer y reciente de este envío para este usuario. */
    private function avisoDeSubidaAbierto(User $usuario, Envio $envio): ?\Illuminate\Notifications\DatabaseNotification
    {
        $ventana = now()->subMinutes(10);

        return $usuario->notifications()
            ->whereNull('read_at')
            ->where('data->tipo', 'subida')
            ->where('data->envio_id', $envio->id)
            ->where('created_at', '>=', $ventana)
            ->first();
    }

    /**
     * A quién le corresponde cada aviso.
     *
     * @return list<User>
     */
    public function destinatarios(Envio $envio, string $evento, ?User $actor = null): array
    {
        $usuarios = match ($evento) {
            // A revisión o a mitad de la carga: los revisores asignados.
            'enviado_a_revision', 'subida_serie', 'subida_completa' => $this->revisores($envio),

            // Observado, aprobado o reabierto: quienes cargaron el envío.
            default => $this->encargadosDelEnvio($envio),
        };

        return array_values(array_filter(
            $usuarios,
            fn (User $usuario) => $usuario->activo && $usuario->id !== $actor?->id
        ));
    }

    /** @return list<User> */
    private function revisores(Envio $envio): array
    {
        // R-ALC-01: cada revisor ve —y recibe— solo sus establecimientos
        // asignados. Antes se avisaba a todos los revisores del Servicio.
        $asignados = $envio->establecimiento->usuarios()
            ->where('activo', true)
            ->whereHas('roles', fn ($q) => $q->where('name', 'revisor'))
            ->get()
            ->all();

        // Sin revisor asignado el aviso no puede quedarse sin destinatario: va
        // al administrador, que es quien puede asignar.
        return $asignados === []
            ? User::role('admin')->where('activo', true)->get()->all()
            : $asignados;
    }

    /** ¿El envío tiene algún revisor asignado al establecimiento? */
    public function tieneRevisorAsignado(Envio $envio): bool
    {
        return $envio->establecimiento->usuarios()
            ->whereHas('roles', fn ($q) => $q->where('name', 'revisor'))
            ->where('activo', true)
            ->exists();
    }

    /** @return list<User> */
    private function encargadosDelEnvio(Envio $envio): array
    {
        $usuarios = $envio->establecimiento->usuarios()->where('activo', true)->get()->all();

        if ($envio->enviado_por !== null) {
            $autor = User::find($envio->enviado_por);

            if ($autor !== null && ! in_array($autor->id, array_map(fn (User $u) => $u->id, $usuarios), true)) {
                $usuarios[] = $autor;
            }
        }

        return $usuarios;
    }
}
