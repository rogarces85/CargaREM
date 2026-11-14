<?php

namespace App\Models;

use App\Services\CalendarioService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Período mensual.
 *
 * La fecha límite se calcula sola con el calendario (D-029): fin del quinto
 * día hábil del mes siguiente. El administrador puede fijar una fecha manual
 * por excepción; en ese caso `fecha_limite_manual` queda en true y el sistema
 * no la vuelve a calcular.
 */
class Periodo extends Model
{
    use HasFactory;

    public const ABIERTO = 'abierto';
    public const EN_REVISION = 'en_revision';
    public const CERRADO = 'cerrado';

    protected $table = 'periodos';

    /** Un período nuevo no tiene override manual. */
    protected $attributes = ['fecha_limite_manual' => false];

    protected $fillable = ['anio', 'mes', 'fecha_limite', 'fecha_limite_manual', 'estado'];

    protected function casts(): array
    {
        return [
            'anio'                => 'integer',
            'mes'                 => 'integer',
            'fecha_limite'        => 'date',
            'fecha_limite_manual' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Periodo $periodo) {
            if ($periodo->fecha_limite_manual) {
                return; // excepción declarada: se respeta
            }

            if (! self::periodoValido($periodo->anio, $periodo->mes)) {
                return;
            }

            $creando = ! $periodo->exists;
            $sinFecha = $periodo->fecha_limite === null;
            $cambioPeriodo = ! $creando && $periodo->isDirty(['anio', 'mes']);

            if ($sinFecha || $cambioPeriodo) {
                $periodo->fecha_limite = app(CalendarioService::class)
                    ->fechaLimite((int) $periodo->anio, (int) $periodo->mes)
                    ->toDateString();
            }
        });
    }

    public function estaAbierto(): bool
    {
        return $this->estado === self::ABIERTO;
    }

    /** La fecha que corresponde al período según el calendario, aunque haya override. */
    public function fechaLimiteCalculada(): Carbon
    {
        return app(CalendarioService::class)->fechaLimite((int) $this->anio, (int) $this->mes);
    }

    private static function periodoValido(mixed $anio, mixed $mes): bool
    {
        return (int) $anio >= 2000 && (int) $mes >= 1 && (int) $mes <= 12;
    }
}
