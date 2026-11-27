<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Apertura de plazo (RF-043, D-044).
 *
 * Una ventana con motivo que habilita a un establecimiento a cargar un período
 * cerrado. La pide el encargado o la abre el administrador; solo el
 * administrador la resuelve.
 */
class Apertura extends Model
{
    use HasFactory;

    public const PENDIENTE = 'pendiente';
    public const APROBADA = 'aprobada';
    public const RECHAZADA = 'rechazada';
    public const ANULADA = 'anulada';

    public const ORIGEN_SOLICITUD = 'solicitud';
    public const ORIGEN_ADMIN = 'admin';

    public const ESTADOS = [
        self::PENDIENTE => 'Pendiente',
        self::APROBADA => 'Aprobada',
        self::RECHAZADA => 'Rechazada',
        self::ANULADA => 'Anulada',
    ];

    protected $table = 'aperturas';

    protected $fillable = [
        'establecimiento_id', 'anio', 'mes', 'origen', 'motivo', 'solicitado_por',
        'estado', 'resuelto_por', 'resuelto_en', 'motivo_resolucion', 'vigente_hasta',
    ];

    protected function casts(): array
    {
        return [
            'anio'         => 'integer',
            'mes'          => 'integer',
            'resuelto_en'  => 'datetime',
            'vigente_hasta' => 'date',
        ];
    }

    public function establecimiento(): BelongsTo
    {
        return $this->belongsTo(Establecimiento::class);
    }

    public function solicitante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'solicitado_por');
    }

    public function resolvedor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resuelto_por');
    }

    /** ¿Abre a todos los establecimientos del Servicio? */
    public function esParaTodos(): bool
    {
        return $this->establecimiento_id === null;
    }

    /** ¿Está aprobada y dentro de su ventana? */
    public function estaVigente(): bool
    {
        if ($this->estado !== self::APROBADA) {
            return false;
        }

        return $this->vigente_hasta === null || $this->vigente_hasta->endOfDay()->isFuture();
    }

    /** Etiqueta del período, «09/2026». */
    public function periodoLegible(): string
    {
        return sprintf('%02d/%d', $this->mes, $this->anio);
    }

    /**
     * Aperturas que pueden habilitar una carga: aprobadas y vigentes.
     *
     * @param  Builder<Apertura>  $consulta
     * @return Builder<Apertura>
     */
    public function scopeVigentes(Builder $consulta): Builder
    {
        return $consulta
            ->where('estado', self::APROBADA)
            ->where(function (Builder $q) {
                $q->whereNull('vigente_hasta')
                    ->orWhere('vigente_hasta', '>=', now()->toDateString());
            });
    }
}
