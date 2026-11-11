<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Periodo extends Model
{
    use HasFactory;

    public const ABIERTO = 'abierto';
    public const EN_REVISION = 'en_revision';
    public const CERRADO = 'cerrado';

    protected $table = 'periodos';

    protected $fillable = ['anio', 'mes', 'fecha_limite', 'estado'];

    protected function casts(): array
    {
        return [
            'anio'         => 'integer',
            'mes'          => 'integer',
            'fecha_limite' => 'date',
        ];
    }

    public function estaAbierto(): bool
    {
        return $this->estado === self::ABIERTO;
    }
}