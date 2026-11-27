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

    protected $fillable = ['codigo', 'nombre', 'orden', 'activo', 'meses_exigidos'];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'orden'  => 'integer',
            'meses_exigidos' => 'array',
        ];
    }

    /**
     * ¿Esta serie se informa en el mes estadístico indicado?
     *
     * `meses_exigidos = null` significa «todos los meses», que es el caso de
     * las series mensuales. La P viene con `[6, 12]`: se informa dos veces al
     * año (R-P-01). La regla de qué se exige vive en `MatrizSeries`; esto es
     * solo la lectura del dato.
     */
    public function seInformaEn(int $mes): bool
    {
        if ($this->meses_exigidos === null || $this->meses_exigidos === []) {
            return $this->meses_exigidos === null;
        }

        return in_array($mes, array_map('intval', $this->meses_exigidos), true);
    }

    /** «Semestral (jun y dic)» o «Mensual», para la interfaz. */
    public function periodicidadLegible(): string
    {
        if ($this->meses_exigidos === null) {
            return 'Mensual';
        }

        $nombres = [
            1 => 'ene', 2 => 'feb', 3 => 'mar', 4 => 'abr', 5 => 'may', 6 => 'jun',
            7 => 'jul', 8 => 'ago', 9 => 'sep', 10 => 'oct', 11 => 'nov', 12 => 'dic',
        ];

        $meses = array_map(fn ($m) => $nombres[(int) $m] ?? (string) $m, $this->meses_exigidos);

        return 'Solo ' . implode(' y ', $meses);
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