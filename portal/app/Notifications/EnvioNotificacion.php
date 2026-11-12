<?php

namespace App\Notifications;

use App\Models\Envio;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Aviso por correo de un movimiento del envío (T-F3-10).
 *
 * No se encola: el servidor no tiene worker garantizado y una notificación
 * que nunca sale es peor que una que tarda un segundo.
 */
class EnvioNotificacion extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Envio $envio,
        public readonly string $evento,
        public readonly ?User $actor = null,
    ) {
    }

    /**
     * Correo y base de datos.
     *
     * La fila en `notifications` es el respaldo: si el servidor de correo está
     * caído, el usuario ve el aviso al entrar al portal.
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $envio = $this->envio;

        return (new MailMessage())
            ->subject($this->asunto())
            ->view('mail.envio', [
                'envio'        => $envio,
                'evento'       => $this->evento,
                'actor'        => $this->actor,
                'destinatario' => $notifiable,
                'titulo'       => $this->titulo(),
                'mensaje'      => $this->mensaje(),
                'periodo'      => sprintf('%02d/%d', $envio->mes, $envio->anio),
            ]);
    }

    /** Datos que quedan guardados en la tabla de notificaciones. */
    public function toArray(object $notifiable): array
    {
        return [
            'envio_id' => $this->envio->id,
            'evento'   => $this->evento,
            'periodo'  => sprintf('%02d/%d', $this->envio->mes, $this->envio->anio),
            'estado'   => $this->envio->estado,
        ];
    }

    private function asunto(): string
    {
        return sprintf(
            '[Cargas REM] %s · %s · %02d/%d',
            $this->titulo(),
            $this->envio->establecimiento->codigo,
            $this->envio->mes,
            $this->envio->anio
        );
    }

    private function titulo(): string
    {
        return match ($this->evento) {
            'enviado_a_revision' => 'Envío pendiente de revisión',
            'observado'          => 'Envío observado',
            'aprobado'           => 'Envío aprobado',
            'cargado'            => 'Envío cargado',
            default              => 'Actualización de envío',
        };
    }

    private function mensaje(): string
    {
        $quien = $this->actor?->name ?? 'El sistema';

        return match ($this->evento) {
            'enviado_a_revision' => "{$quien} envió a revisión el período. Tiene observaciones abiertas por revisar.",
            'observado'          => "{$quien} dejó observaciones que debe corregir antes de reenviar.",
            'aprobado'           => "{$quien} aprobó el envío. No hay nada más que corregir en este período.",
            'cargado'            => "{$quien} confirmó la carga de los datos en el sistema institucional.",
            default              => "{$quien} actualizó el estado del envío.",
        };
    }
}