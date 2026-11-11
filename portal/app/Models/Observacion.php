<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Observacion extends Model
{
    use HasFactory;

    protected $table = 'observaciones';

    protected $fillable = ['envio_id', 'envio_archivo_id', 'autor_id', 'texto', 'resuelta', 'resuelta_en'];

    protected function casts(): array
    {
        return ['resuelta' => 'boolean', 'resuelta_en' => 'datetime'];
    }

    public function envio(): BelongsTo
    {
        return $this->belongsTo(Envio::class);
    }

    public function archivo(): BelongsTo
    {
        return $this->belongsTo(EnvioArchivo::class, 'envio_archivo_id');
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'autor_id');
    }
}