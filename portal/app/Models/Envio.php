<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Envio extends Model
{
    use HasFactory;

    public const RECIBIDO    = 'recibido';
    public const VALIDADO    = 'validado';
    public const CON_ERRORES = 'con_errores';
    public const EN_REVISION = 'en_revision';
    public const OBSERVADO   = 'observado';
    public const APROBADO    = 'aprobado';
    public const CARGADO     = 'cargado';
    public const ANULADO     = 'anulado';

    public const ESTADOS = [
        self::RECIBIDO, self::VALIDADO, self::CON_ERRORES, self::EN_REVISION,
        self::OBSERVADO, self::APROBADO, self::CARGADO, self::ANULADO,
    ];

    protected $table = 'envios';

    protected $fillable = [
        'establecimiento_id', 'anio', 'mes', 'estado', 'enviado_por', 'revisor_id',
        'observacion_general', 'fecha_envio', 'fecha_revision', 'fecha_aprobacion', 'fecha_carga',
    ];

    protected function casts(): array
    {
        return [
            'anio'             => 'integer',
            'mes'              => 'integer',
            'fecha_envio'      => 'datetime',
            'fecha_revision'   => 'datetime',
            'fecha_aprobacion' => 'datetime',
            'fecha_carga'      => 'datetime',
        ];
    }

    /**
     * Aislamiento por usuario en la CONSULTA, no en la vista (RN-ENV-1,
     * principio 18).
     *
     * - admin: todo.
     * - revisor y consulta: su Servicio de Salud.
     * - encargado: sus establecimientos asignados.
     */
    public function scopeForUser(Builder $query, User $usuario): Builder
    {
        if ($usuario->hasRole('admin')) {
            return $query;
        }

        if ($usuario->servicio_id !== null
            && ($usuario->hasRole('revisor') || $usuario->hasRole('consulta'))) {
            return $query->whereHas('establecimiento', function (Builder $establecimiento) use ($usuario) {
                $establecimiento->where('servicio_id', $usuario->servicio_id);
            });
        }

        return $query->whereIn(
            'establecimiento_id',
            $usuario->establecimientos()->select('establecimientos.id')
        );
    }

    public function establecimiento(): BelongsTo
    {
        return $this->belongsTo(Establecimiento::class);
    }

    public function archivos(): HasMany
    {
        return $this->hasMany(EnvioArchivo::class);
    }

    public function archivosVigentes(): HasMany
    {
        return $this->hasMany(EnvioArchivo::class)->where('es_vigente', true);
    }

    public function observaciones(): HasMany
    {
        return $this->hasMany(Observacion::class);
    }

    public function transiciones(): HasMany
    {
        return $this->hasMany(EnvioTransicion::class);
    }

    public function enviadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enviado_por');
    }

    public function revisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revisor_id');
    }

    public function estaAbierto(): bool
    {
        return in_array($this->estado, [self::RECIBIDO, self::VALIDADO, self::CON_ERRORES, self::OBSERVADO], true);
    }
}