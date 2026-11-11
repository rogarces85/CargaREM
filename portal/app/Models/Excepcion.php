<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Celdas que se ignoran por prestación, hoja y serie (Excep / colexcep).
 */
class Excepcion extends Model
{
    use HasFactory;

    protected $table = 'excepciones';

    public $timestamps = false;

    protected $fillable = [
        'snapshot_id', 'anio', 'mes', 'serie', 'hoja',
        'codigo_prestacion', 'columna', 'motivo', 'payload',
    ];

    protected function casts(): array
    {
        return [
            'anio'    => 'integer',
            'mes'     => 'integer',
            'payload' => 'array',
        ];
    }

    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(CatalogoSnapshot::class, 'snapshot_id');
    }
}