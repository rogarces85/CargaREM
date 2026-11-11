<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Hojas y rangos que hay que leer de un Excel, por año y serie.
 *
 * Viene de la tabla `Thojas` del Minsal. No depende del mes: la estructura de
 * la planilla es la misma durante todo el año.
 */
class PlantillaHoja extends Model
{
    use HasFactory;

    protected $table = 'plantilla_hojas';

    public $timestamps = false;

    protected $fillable = ['anio', 'serie_interna', 'hoja', 'rango', 'obligatoria'];

    protected function casts(): array
    {
        return ['anio' => 'integer', 'obligatoria' => 'boolean'];
    }

    public function scopeDeSerie(Builder $query, int $anio, string $serieInterna): Builder
    {
        return $query->where('anio', $anio)->where('serie_interna', $serieInterna)->orderBy('id');
    }

    public function serie(): BelongsTo
    {
        return $this->belongsTo(Serie::class);
    }
}
