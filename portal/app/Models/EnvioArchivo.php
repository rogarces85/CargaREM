<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EnvioArchivo extends Model
{
    use HasFactory;

    public const SUBIDO      = 'subido';
    public const VALIDANDO   = 'validando';
    public const VALIDO      = 'valido';
    public const CON_ERRORES = 'con_errores';
    public const OBSERVADO   = 'observado';
    public const APROBADO    = 'aprobado';
    public const CARGADO     = 'cargado';
    public const REEMPLAZADO = 'reemplazado';

    protected $table = 'envio_archivos';

    protected $fillable = [
        'envio_id', 'serie_id', 'version', 'es_vigente', 'nombre_original', 'ruta', 'disco',
        'sha256', 'tamano_bytes', 'mime', 'estado', 'plantilla_id', 'snapshot_id',
        'filas_leidas', 'filas_validas', 'filas_error', 'validado_en', 'subido_por',
    ];

    protected function casts(): array
    {
        return [
            'version'      => 'integer',
            'es_vigente'   => 'boolean',
            'tamano_bytes' => 'integer',
            'validado_en'  => 'datetime',
        ];
    }

    /** Un archivo se ve si se ve su envío (mismo aislamiento). */
    public function scopeForUser(Builder $query, User $usuario): Builder
    {
        return $query->whereHas('envio', fn (Builder $envio) => $envio->forUser($usuario));
    }

    public function envio(): BelongsTo
    {
        return $this->belongsTo(Envio::class);
    }

    public function serie(): BelongsTo
    {
        return $this->belongsTo(Serie::class);
    }

    public function plantilla(): BelongsTo
    {
        return $this->belongsTo(Plantilla::class);
    }

    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(CatalogoSnapshot::class, 'snapshot_id');
    }

    public function subidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subido_por');
    }

    public function validaciones(): HasMany
    {
        return $this->hasMany(Validacion::class);
    }

    public function bloqueantes(): HasMany
    {
        return $this->hasMany(Validacion::class)->where('severidad', 'bloqueante');
    }
}