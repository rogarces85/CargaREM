<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * Aviso de gestión, sin envío detrás (v3).
 *
 * Los avisos de envío tienen su propia clase porque llevan el objeto `Envio`.
 * Este cubre lo que no cuelga de un envío: asignaciones, aperturas de plazo,
 * avisos al administrador. La campana lo pinta con los mismos campos
 * (`titulo`, `mensaje`, `tipo`), así que una sola vista sirve para los dos.
 *
 * Lo que se guarda es lo mínimo para pintar el aviso y para saber a qué
 * pantalla llevar al hacer clic. Nunca datos de celdas ni observaciones
 * completas (Constitución, principio 22).
 */
class SistemaNotificacion extends Notification
{
    /**
     * @param  array<string, mixed>  $datos
     */
    public function __construct(
        public readonly string $clave,
        public readonly array $datos = [],
    ) {
    }

    /** Solo base de datos: el portal es el canal. */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'evento'   => $this->clave,
            'tipo'     => $this->tipo(),
            'titulo'   => $this->titulo(),
            'mensaje'  => $this->mensaje(),
            'url'      => $this->url(),
            'tono'     => $this->tono(),
        ];
    }

    private function tipo(): string
    {
        return match ($this->clave) {
            'sin_asignacion', 'asignacion' => 'asignacion',
            'apertura_solicitada', 'apertura_aprobada', 'apertura_rechazada', 'apertura_abierta' => 'apertura',
            'reapertura' => 'revision',
            'validador' => 'validador',
            default => 'sistema',
        };
    }

    private function tono(): string
    {
        return match ($this->clave) {
            'sin_asignacion' => 'warn',
            'apertura_rechazada' => 'danger',
            'apertura_aprobada', 'apertura_abierta', 'asignacion' => 'ok',
            default => 'info',
        };
    }

    private function titulo(): string
    {
        return match ($this->clave) {
            'sin_asignacion'      => 'Usuario sin establecimientos',
            'asignacion'          => 'Nuevos establecimientos asignados',
            'apertura_solicitada' => 'Solicitud de apertura de plazo',
            'apertura_aprobada'   => 'Apertura de plazo aprobada',
            'apertura_abierta'    => 'Plazo abierto por el administrador',
            'apertura_rechazada'  => 'Solicitud de apertura rechazada',
            'reapertura'          => 'Envío reabierto',
            default               => 'Aviso del portal',
        };
    }

    private function mensaje(): string
    {
        $usuario = (string) ($this->datos['usuario'] ?? 'Un usuario');
        $rol = (string) ($this->datos['rol'] ?? 'usuario');
        $establecimiento = (string) ($this->datos['establecimiento'] ?? '');
        $periodo = (string) ($this->datos['periodo'] ?? '');
        $motivo = (string) ($this->datos['motivo'] ?? '');

        return match ($this->clave) {
            'sin_asignacion' => "{$usuario} ({$rol}) no tiene establecimientos asignados: no verá ningún dato hasta que le asigne al menos uno.",

            'asignacion' => $establecimiento !== ''
                ? "Ahora tiene a su cargo {$establecimiento} ({$periodo})."
                : 'Su reparto de establecimientos cambió.',

            'apertura_solicitada' => "{$establecimiento} pidió abrir el período {$periodo}. Motivo: {$motivo}",

            'apertura_aprobada' => "El período {$periodo} quedó abierto para {$establecimiento}.",

            'apertura_abierta' => "El administrador abrió el período {$periodo} para {$establecimiento}. Motivo: {$motivo}",

            'apertura_rechazada' => "La solicitud para el período {$periodo} de {$establecimiento} fue rechazada. Motivo: {$motivo}",

            'reapertura' => "El envío {$establecimiento} de {$periodo} se reabrió: hay correcciones pendientes. Motivo: {$motivo}",

            default => 'Hay una novedad en el portal.',
        };
    }

    private function url(): ?string
    {
        return isset($this->datos['url']) && $this->datos['url'] !== ''
            ? (string) $this->datos['url']
            : null;
    }
}
