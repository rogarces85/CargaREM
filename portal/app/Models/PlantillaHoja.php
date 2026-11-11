<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlantillaHoja extends Model
{
    use HasFactory;

    protected $table = 'plantilla_hojas';

    public $timestamps = false;

    protected $fillable = ['plantilla_id', 'hoja', 'rango', 'obligatoria'];

    protected function casts(): array
    {
        return ['obligatoria' => 'boolean'];
    }

    public function plantilla(): BelongsTo
    {
        return $this->belongsTo(Plantilla::class);
    }
}