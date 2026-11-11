<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Espejo de la tabla Diccionario del Minsal: dónde está cada prestación.
 */
class Diccionario extends Model
{
    use HasFactory;

    protected $table = 'diccionario';

    public $timestamps = false;

    protected $fillable = [
        'snapshot_id', 'anio', 'mes', 'serie', 'hoja', 'codigo_prestacion',
        'texto_prestacion', 'linea', 'inicio', 'fin', 'posicion', 'tipo_dato',
    ];

    protected function casts(): array
    {
        return [
            'anio'     => 'integer',
            'mes'      => 'integer',
            'linea'    => 'integer',
            'posicion' => 'integer',
        ];
    }

    public function scopeDeHoja(Builder $query, int $snapshotId, int $anio, string $serie, string $hoja): Builder
    {
        return $query->where('snapshot_id', $snapshotId)
            ->where('anio', $anio)
            ->where('serie', $serie)
            ->where('hoja', $hoja);
    }

    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(CatalogoSnapshot::class, 'snapshot_id');
    }
}