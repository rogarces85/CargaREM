<?php

namespace App\Providers;

use App\Models\CatalogoSnapshot;
use App\Observers\CatalogoSnapshotObserver;
use App\Services\AuditoriaService;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        /*
         * Auditoría de acceso (T-F1-27, principio 21).
         *
         * El login fallido se registra con el correo intentado, porque es la
         * señal que sirve para detectar ataques. No se guarda la contraseña
         * ni el motivo del fallo.
         */
        Event::listen(Login::class, function (Login $evento) {
            AuditoriaService::registrar('acceso.login', 'User', $evento->user->getAuthIdentifier());
        });

        Event::listen(Logout::class, function (Logout $evento) {
            if ($evento->user !== null) {
                AuditoriaService::registrar('acceso.logout', 'User', $evento->user->getAuthIdentifier());
            }
        });

        Event::listen(Failed::class, function (Failed $evento) {
            AuditoriaService::registrar('acceso.fallido', 'User', null, [
                'email' => mb_substr((string) $evento->credentials['email'] ?? '', 0, 150),
            ]);
        });

        CatalogoSnapshot::observe(CatalogoSnapshotObserver::class);
    }
}
