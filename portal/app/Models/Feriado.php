<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Día no hábil (feriado) del calendario.
 *
 * Además se consideran no hábiles los sábados y domingos. El ámbito queda
 * registrado para futuras expansiones; como el portal sirve a un solo
 * Servicio, todo feriado cargado entra en el cómputo.
 */
class Feriado extends Model
{
    use HasFactory;

    protected $table = 'feriados';

    protected $fillable = ['fecha', 'nombre', 'ambito', 'region'];

    protected function casts(): array
    {
        return ['fecha' => 'date'];
    }

    public const NACIONAL = 'nacional';
    public const REGIONAL = 'regional';
}
