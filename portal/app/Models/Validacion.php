<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Validacion extends Model
{
    use HasFactory;

    protected $table = 'validaciones';

    /** Solo `created_at` (tabla de eventos). */
    const UPDATED_AT = null;

    protected $fillable = [
        'envio_archivo_id', 'etapa', 'codigo', 'severidad', 'regla', 'mensaje', 'hoja', 'celda', 'valor',
    ];

    public function archivo(): BelongsTo
    {
        return $this->belongsTo(EnvioArchivo::class, 'envio_archivo_id');
    }
}