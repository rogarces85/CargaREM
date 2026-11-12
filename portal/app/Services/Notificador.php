<?php

namespace App\Services;

use App\Models\Envio;
use App\Models\User;
use App\Notifications\EnvioNotificacion;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Avisos por correo de los movimientos del envío (T-F3-10, OP-17).
 *
 * Reglas:
 * 1. Nunca se avisa a quien provocó el cambio.
 * 2. Un fallo de correo no puede tumbar la operación: el estado ya cambió y
 *    la notificación es un extra. Se registra y se sigue.
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
            Log::channel('correo')->info('Sin destinatarios para la notificación.', [
                'envio' => $envio->id,
                'evento' => $evento,
            ]);

            return ['enviados' => 0, 'destinatarios' => []];
        }

        $enviados = 0;
        $correos = [];

        foreach ($destinatarios as $usuario) {
            try {
                $usuario->notify(new EnvioNotificacion($envio, $evento, $actor));
                $enviados++;
                $correos[] = $usuario->email;
            } catch (Throwable $e) {
                // El estado ya cambió; el aviso es secundario.
                report($e);
                Log::channel('correo')->warning('No se pudo enviar la notificación.', [
                    'envio'  => $envio->id,
                    'evento' => $evento,
                    'para'   => $usuario->email,
                    'error'  => $e->getMessage(),
                ]);
            }
        }

        AuditoriaService::registrarSobre('envio.notificado', $envio, [
            'evento'        => $evento,
            'destinatarios' => count($correos),
        ]);

        return ['enviados' => $enviados, 'destinatarios' => $correos];
    }

    /**
     * A quién le corresponde cada aviso.
     *
     * @return list<User>
     */
    public function destinatarios(Envio $envio, string $evento, ?User $actor = null): array
    {
        $usuarios = match ($evento) {
            // A revisión: los revisores del mismo Servicio de Salud.
            'enviado_a_revision' => $this->revisoresDelServicio($envio),

            // Observado, aprobado o cargado: quienes cargaron el envío.
            default => $this->encargadosDelEnvio($envio),
        };

        return array_values(array_filter(
            $usuarios,
            fn (User $usuario) => $usuario->activo && $usuario->id !== $actor?->id
        ));
    }

    /** @return list<User> */
    private function revisoresDelServicio(Envio $envio): array
    {
        $servicioId = $envio->establecimiento->servicio_id;

        $revisores = User::role('revisor')
            ->where('activo', true)
            ->where('servicio_id', $servicioId)
            ->get()
            ->all();

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
