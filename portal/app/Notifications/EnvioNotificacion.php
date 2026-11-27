<?php

namespace App\Notifications;

use App\Models\Envio;
use App\Models\User;
use Illuminate\Notifications\Notification;

/**
 * Aviso en el portal de un movimiento del envío (T-F3-10, RF-041).
 *
 * En v3.0 la notificación vive solo en la tabla `notifications`: no hay correo.
 * La campana de la cabecera la muestra y el usuario la marca como leída.
 *
 * **Distintivo de subida (RF-041.1).** Cuando el aviso es de una subida del
 * encargado, el evento distingue dos casos y la campana los pinta distinto:
 *
 * - `subida_serie`: llegó el archivo de una serie (`alcance = serie`).
 * - `subida_completa`: con esa subida quedaron todas las series exigidas
 *   (`alcance = completo`).
 *
 * Los datos que se guardan son los mínimos para pintar el aviso y para saber a
 * qué pantalla llevar al hacer clic. Nunca contenido de celdas ni observaciones
 * completas (Constitución, principio 22).
 */
class EnvioNotificacion extends Notification
{
    /**
     * @param  array<string, mixed>  $extra
     */
    public function __construct(
        public readonly Envio $envio,
        public readonly string $evento,
        public readonly ?User $actor = null,
        public readonly array $extra = [],
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
            'envio_id'   => $this->envio->id,
            'evento'     => $this->evento,
            'tipo'       => $this->tipo(),
            'alcance'    => $this->alcance(),
            'series'     => $this->extra['series'] ?? [],
            'presentes'  => $this->extra['presentes'] ?? null,
            'exigidas'   => $this->extra['exigidas'] ?? null,
            'titulo'     => $this->titulo(),
            'mensaje'    => $this->mensaje(),
            'periodo'    => sprintf('%02d/%d', $this->envio->mes, $this->envio->anio),
            'estado'     => $this->envio->estado,
            'establecimiento' => $this->envio->establecimiento?->codigo,
            'tono'       => $this->tono(),
        ];
    }

    private function tipo(): string
    {
        return match ($this->evento) {
            'subida_serie', 'subida_completa' => 'subida',
            'enviado_a_revision'             => 'revision',
            'observado', 'aprobado'          => 'revision',
            'reabierto'                      => 'revision',
            'solicitud_reenvio'              => 'revision',
            default                          => 'envio',
        };
    }

    /** `serie` o `completo`; el resto de los eventos no lleva distintivo. */
    private function alcance(): ?string
    {
        return match ($this->evento) {
            'subida_completa' => 'completo',
            'subida_serie'    => 'serie',
            'enviado_a_revision' => 'completo',
            default           => null,
        };
    }

    private function tono(): string
    {
        return match ($this->evento) {
            'subida_completa', 'aprobado' => 'ok',
            'observado', 'reabierto', 'solicitud_reenvio' => 'warn',
            'enviado_a_revision' => 'info',
            default => 'info',
        };
    }

    private function titulo(): string
    {
        return match ($this->evento) {
            'subida_serie'       => 'Serie subida',
            'subida_completa'    => 'Envío completo',
            'enviado_a_revision' => 'Envío pendiente de revisión',
            'observado'          => 'Envío observado',
            'aprobado'           => 'Envío aprobado',
            'reabierto'          => 'Envío reabierto',
            'solicitud_reenvio'  => 'Le piden volver a subir la estadística',
            default              => 'Actualización de envío',
        };
    }

    private function mensaje(): string
    {
        $quien = $this->actor?->name ?? 'El sistema';
        $establecimiento = $this->envio->establecimiento?->codigo ?? 'el establecimiento';
        $series = $this->extra['series'] ?? [];
        $lista = is_array($series) ? implode(', ', $series) : (string) $series;
        $presentes = $this->extra['presentes'] ?? null;
        $exigidas = $this->extra['exigidas'] ?? null;
        $motivo = trim((string) ($this->extra['motivo'] ?? ''));

        $cuenta = ($presentes !== null && $exigidas !== null)
            ? " ({$presentes} de {$exigidas} series)"
            : '';

        return match ($this->evento) {
            'subida_serie' => "{$quien} subió la serie {$lista} de {$establecimiento}{$cuenta}.",
            'subida_completa' => "{$quien} completó las series del período en {$establecimiento}{$cuenta}.",
            'enviado_a_revision' => "{$quien} envió a revisión el período {$establecimiento}. Tiene las series completas por revisar.",
            'observado' => "{$quien} dejó observaciones que debe corregir antes de reenviar."
                . ($motivo !== '' ? " Motivo: {$motivo}" : ''),
            'aprobado' => "{$quien} aprobó el envío. No hay nada más que corregir en este período.",
            'reabierto' => "{$quien} reabrió el envío para que vuelva a subir la estadística."
                . ($motivo !== '' ? " Motivo: {$motivo}" : ''),
            'solicitud_reenvio' => "{$quien} pide volver a subir la serie {$lista}."
                . ($motivo !== '' ? " Motivo: {$motivo}" : ''),
            default => "{$quien} actualizó el estado del envío.",
        };
    }
}
