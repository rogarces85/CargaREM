<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlantillaCabecera extends Model
{
    use HasFactory;

    protected $table = 'plantilla_cabecera';

    public $timestamps = false;

    protected $fillable = ['plantilla_id', 'campo', 'hoja', 'celda', 'etiqueta', 'obligatorio'];

    protected function casts(): array
    {
        return ['obligatorio' => 'boolean'];
    }

    public function plantilla(): BelongsTo
    {
        return $this->belongsTo(Plantilla::class);
    }
}