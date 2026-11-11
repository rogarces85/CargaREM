<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Servicio extends Model
{
    use HasFactory;

    protected $table = 'servicios';

    /** Un registro nuevo está activo salvo que se diga lo contrario (default de la columna). */
    protected $attributes = ['activo' => true];

    protected $fillable = ['codigo', 'nombre', 'region', 'activo'];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    public function establecimientos(): HasMany
    {
        return $this->hasMany(Establecimiento::class);
    }
}