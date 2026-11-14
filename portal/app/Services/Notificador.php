<?php

namespace App\Services;

use App\Models\Envio;
use App\Models\User;
use App\Notifications\EnvioNotificacion;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Avisos de los movimientos del envío (T-F3-10, OP-17).
 *
 * Reglas:
 * 1. Nunca se avisa a quien provocó el cambio.
 * 2. El aviso vive en la tabla `notifications`: la campana de la cabecera lo
 *    muestra. En v2.0 no hay correo de notificación.
 * 3. Si no hay a quién avisar, no es un error: se deja constancia.
 */
class Notificador
{
    /**
     * @return array{enviados: int, destinatarios: list<string>}
     */
    public function enviar(Envio $envio, string $evento, ?User $actor = null): array
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
                $usuario->notify(new EnvioNotificacion($envio, $evento, $actor));
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
     * A quién le corresponde cada aviso.
     *
     * @return list<User>
     */
    public function destinatarios(Envio $envio, string $evento, ?User $actor = null): array
    {
        $usuarios = match ($evento) {
            // A revisión: todos los revisores del Servicio único.
            'enviado_a_revision' => $this->revisores($envio),

            // Observado o aprobado: quienes cargaron el envío.
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
        $servicioId = $envio->establecimiento?->servicio_id;

        $consulta = User::role('revisor')
            ->where('activo', true);

        // Se avisa a los revisores del Servicio del envío. Sin Servicio
        // declarado no se filtra: es preferible un aviso de más a ninguno.
        if ($servicioId !== null) {
            $consulta->where('servicio_id', $servicioId);
        }

        $revisores = $consulta->get()->all();

        // Sin revisor asignado, el aviso no puede quedarse sin destinatario.
        if ($revisores === []) {
            return User::role('admin')->where('activo', true)->get()->all();
        }

        return $revisores;
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
