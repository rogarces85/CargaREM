<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EnvioTransicion extends Model
{
    use HasFactory;

    protected $table = 'envio_transiciones';

    const UPDATED_AT = null;

    protected $fillable = ['envio_id', 'estado_anterior', 'estado_nuevo', 'actor_id', 'motivo'];

    public function envio(): BelongsTo
    {
        return $this->belongsTo(Envio::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}