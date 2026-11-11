<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Establecimiento extends Model
{
    use HasFactory;

    protected $table = 'establecimientos';

    /** Un registro nuevo está activo salvo que se diga lo contrario (default de la columna). */
    protected $attributes = ['activo' => true];

    protected $fillable = ['codigo', 'nombre', 'servicio_id', 'comuna', 'dependencia', 'id_comuna', 'id_dependencia', 'tipo', 'activo'];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    public function servicio(): BelongsTo
    {
        return $this->belongsTo(Servicio::class);
    }

    public function series(): BelongsToMany
    {
        return $this->belongsToMany(Serie::class, 'establecimiento_serie')
            ->withPivot(['obligatoria', 'vigente_desde', 'vigente_hasta'])
            ->withTimestamps();
    }

    public function usuarios(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'usuario_establecimiento', 'establecimiento_id', 'usuario_id')
            ->withTimestamps();
    }
}