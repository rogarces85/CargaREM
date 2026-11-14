<?php

namespace App\Notifications;

use App\Models\Envio;
use App\Models\User;
use Illuminate\Notifications\Notification;

/**
 * Aviso en el portal de un movimiento del envío (T-F3-10).
 *
 * En v2.0 la notificación vive solo en la tabla `notifications`: no hay correo
 * saliente para los avisos. La campana de la cabecera la muestra y el usuario
 * la marca como leída.
 */
class EnvioNotificacion extends Notification
{
    public function __construct(
        public readonly Envio $envio,
        public readonly string $evento,
        public readonly ?User $actor = null,
    ) {
    }

    /**
     * Solo base de datos: el portal es el canal.
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** Datos que quedan guardados en la tabla de notificaciones. */
    public function toArray(object $notifiable): array
    {
        return [
            'envio_id' => $this->envio->id,
            'evento'   => $this->evento,
            'titulo'   => $this->titulo(),
            'mensaje'  => $this->mensaje(),
            'periodo'  => sprintf('%02d/%d', $this->envio->mes, $this->envio->anio),
            'estado'   => $this->envio->estado,
        ];
    }

    private function titulo(): string
    {
        return match ($this->evento) {
            'enviado_a_revision' => 'Envío pendiente de revisión',
            'observado'          => 'Envío observado',
            'aprobado'           => 'Envío aprobado',
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
            default              => "{$quien} actualizó el estado del envío.",
        };
    }
}
