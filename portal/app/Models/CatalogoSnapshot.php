<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Foto del catálogo importado. Solo uno puede estar activo (T-F1-21).
 */
class CatalogoSnapshot extends Model
{
    use HasFactory;

    protected $table = 'catalogo_snapshots';

    protected $fillable = [
        'origen', 'hash_paquete', 'filas_diccionario', 'filas_establecimientos',
        'activo', 'importado_por',
    ];

    protected function casts(): array
    {
        return [
            'activo'      => 'boolean',
            'filas_diccionario'      => 'integer',
            'filas_establecimientos' => 'integer',
        ];
    }

    public function scopeActivo(Builder $query): Builder
    {
        return $query->where('activo', true);
    }

    public function importador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'importado_por');
    }

    public function diccionario(): HasMany
    {
        return $this->hasMany(Diccionario::class, 'snapshot_id');
    }

    public function excepciones(): HasMany
    {
        return $this->hasMany(Excepcion::class, 'snapshot_id');
    }
}