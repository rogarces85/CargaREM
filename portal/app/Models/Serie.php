<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Serie extends Model
{
    use HasFactory;

    protected $table = 'series';

    /** Un registro nuevo está activo salvo que se diga lo contrario (default de la columna). */
    protected $attributes = ['activo' => true];

    protected $fillable = ['codigo', 'nombre', 'orden', 'activo'];

    protected function casts(): array
    {
        return ['activo' => 'boolean', 'orden' => 'integer'];
    }

    public function establecimientos(): BelongsToMany
    {
        return $this->belongsToMany(Establecimiento::class, 'establecimiento_serie')
            ->withPivot(['obligatoria', 'vigente_desde', 'vigente_hasta'])
            ->withTimestamps();
    }

    public function plantillas(): HasMany
    {
        return $this->hasMany(Plantilla::class);
    }
}