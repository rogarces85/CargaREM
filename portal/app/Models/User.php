<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'activo',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'ultimo_acceso'     => 'datetime',
            'password'          => 'hashed',
            'activo'            => 'boolean',
        ];
    }

    /**
     * Establecimientos que este usuario puede ver y operar (RN-ENV-1).
     */
    public function establecimientos(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Establecimiento::class, 'usuario_establecimiento', 'usuario_id', 'establecimiento_id')
            ->withTimestamps();
    }

    /**
     * Un usuario desactivado no puede autenticarse (R-ID-04).
     */
    public function puedeEntrar(): bool
    {
        return (bool) $this->activo;
    }
}
