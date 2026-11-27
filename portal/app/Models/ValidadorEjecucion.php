<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una ejecución del Validador (RF-040.1, D-047).
 *
 * Es el registro de uso: quién, cuándo, con qué archivo, con qué versión de
 * reglas y con qué resultado. No cuelga de ningún envío: el Validador trabaja
 * sobre un archivo suelto a propósito.
 */
class ValidadorEjecucion extends Model
{
    use HasFactory;

    public const SIN_HALLAZGOS = 'sin_hallazgos';
    public const CON_HALLAZGOS = 'con_hallazgos';
    public const FALLIDO = 'fallido';

    protected $table = 'validador_ejecuciones';

    protected $fillable = [
        'usuario_id', 'nombre_original', 'ruta', 'sha256', 'tamano_bytes',
        'reglas_version', 'hallazgos', 'bloqueantes', 'advertencias',
        'duracion_ms', 'resultado', 'estado',
    ];

    protected function casts(): array
    {
        return [
            'hallazgos'     => 'integer',
            'bloqueantes'   => 'integer',
            'advertencias'  => 'integer',
            'duracion_ms'   => 'integer',
            'tamano_bytes'  => 'integer',
            'resultado'     => 'array',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    /** @return list<array<string, mixed>> */
    public function listaHallazgos(): array
    {
        return $this->resultado['hallazgos'] ?? [];
    }

    /** Resumen del veredicto, o null si la ejecución no llegó a producir uno. */
    public function resumen(): ?array
    {
        return $this->resultado['resumen'] ?? null;
    }

    public function veredicto(): string
    {
        return $this->resumen()['veredicto'] ?? '—';
    }

    /** Metadatos que el Validador leyó del nombre y de la portada. */
    public function metadatos(): array
    {
        return $this->resultado['metadatos'] ?? [];
    }
}
