<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EstablecimientoSerie extends Model
{
    use HasFactory;

    protected $table = 'establecimiento_serie';

    protected $fillable = ['establecimiento_id', 'serie_id', 'obligatoria', 'vigente_desde', 'vigente_hasta'];

    protected function casts(): array
    {
        return [
            'obligatoria'   => 'boolean',
            'vigente_desde' => 'date',
            'vigente_hasta' => 'date',
        ];
    }

    public function establecimiento(): BelongsTo
    {
        return $this->belongsTo(Establecimiento::class);
    }

    public function serie(): BelongsTo
    {
        return $this->belongsTo(Serie::class);
    }
}