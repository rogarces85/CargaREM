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

    public const ESTADOS = [
        self::RECIBIDO, self::VALIDADO, self::CON_ERRORES, self::EN_REVISION,
        self::OBSERVADO, self::APROBADO,
    ];

    protected $table = 'envios';

    protected $fillable = [
        'establecimiento_id', 'anio', 'mes', 'estado', 'enviado_por', 'revisor_id',
        'observacion_general', 'fecha_envio', 'fecha_revision', 'fecha_aprobacion',
        'fecha_limite', 'fuera_de_plazo', 'apertura_id',
    ];

    protected function casts(): array
    {
        return [
            'anio'             => 'integer',
            'mes'              => 'integer',
            'fecha_envio'      => 'datetime',
            'fecha_revision'   => 'datetime',
            'fecha_aprobacion' => 'datetime',
            'fecha_limite'     => 'date',
            'fuera_de_plazo'   => 'boolean',
        ];
    }

    /**
     * Aislamiento por usuario en la CONSULTA, no en la vista (R-ALC-01,
     * principio 18).
     *
     * - admin: todo.
     * - revisor (Equipo REM): solo los establecimientos que el administrador le
     *   asignó. **Sin asignación no ve ninguno.**
     * - encargado: sus establecimientos asignados.
     *
     * Antes el revisor veía todo su Servicio y, si no tenía Servicio declarado,
     * veía todo el portal. La v3 cambia el modelo: el alcance es la asignación
     * explícita y la regla vive en `App\Dominio\Alcance`.
     */
    public function scopeForUser(Builder $query, User $usuario): Builder
    {
        if ($usuario->hasRole('admin')) {
            return $query;
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

    /** Hallazgos del envío completo (V11), sin archivo asociado. */
    public function validaciones(): HasMany
    {
        return $this->hasMany(Validacion::class, 'envio_id');
    }

    public function enviadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enviado_por');
    }

    public function revisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revisor_id');
    }

    /**
     * Apertura que autorizó la carga, si la hubo (RF-043).
     *
     * Un envío cargado fuera de plazo con autorización conserva la marca
     * `fuera_de_plazo` —es un hecho— y además dice con qué permiso entró.
     */
    public function apertura(): BelongsTo
    {
        return $this->belongsTo(Apertura::class);
    }

    /** ¿Se cargó al amparo de una apertura de plazo? */
    public function conApertura(): bool
    {
        return $this->apertura_id !== null;
    }

    public function estaAbierto(): bool
    {
        return in_array($this->estado, [self::RECIBIDO, self::VALIDADO, self::CON_ERRORES, self::OBSERVADO], true);
    }

    /** El plazo ya se evaluó y el envío llegó tarde. */
    public function estaFueraDePlazo(): bool
    {
        return $this->fuera_de_plazo === true;
    }
}