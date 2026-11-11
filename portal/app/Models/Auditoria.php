<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * Fila de la bitácora de auditoría (principio 21).
 *
 * Append-only de verdad: intentar modificarla o borrarla lanza una excepción,
 * además de que la interfaz no ofrece esas acciones. Se prefiere que falle
 * ruidosamente a que alguien reescriba la historia sin darse cuenta.
 */
class Auditoria extends Model
{
    use HasFactory;

    protected $table = 'auditoria';

    /** Solo `created_at`: no existe `updated_at` en una bitácora. */
    const UPDATED_AT = null;

    protected $fillable = [
        'usuario_id', 'evento', 'entidad', 'entidad_id', 'datos', 'ip', 'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'datos' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function () {
            throw new RuntimeException('La auditoría es append-only: no se modifica.');
        });

        static::deleting(function () {
            throw new RuntimeException('La auditoría es append-only: no se borra.');
        });
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
