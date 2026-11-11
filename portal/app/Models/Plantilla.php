<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plantilla extends Model
{
    use HasFactory;

    protected $table = 'plantillas';

    protected $fillable = ['anio', 'mes', 'serie_id', 'serie_excel', 'version', 'snapshot_id'];

    protected function casts(): array
    {
        return ['anio' => 'integer', 'mes' => 'integer'];
    }

    public function serie(): BelongsTo
    {
        return $this->belongsTo(Serie::class);
    }

    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(CatalogoSnapshot::class, 'snapshot_id');
    }

    public function hojas(): HasMany
    {
        return $this->hasMany(PlantillaHoja::class);
    }

    public function cabecera(): HasMany
    {
        return $this->hasMany(PlantillaCabecera::class);
    }
}