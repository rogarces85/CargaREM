<?php

namespace App\Providers;

use App\Models\Establecimiento;
use App\Models\Periodo;
use App\Models\Plantilla;
use App\Models\Serie;
use App\Models\Servicio;
use App\Models\User;
use App\Observers\AuditoriaCatalogoObserver;
use App\Policies\AperturaPolicy;
use App\Services\AuditoriaService;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // La policy de aperturas necesita el alcance por constructor. Laravel la
        // resuelve por el contenedor solo si se registra a mano: el nombre
        // `AperturaPolicy` no basta porque el modelo vive en `App\Models` y el
        // descubrimiento por convención ya debería encontrarla, pero se declara
        // explícita para que el lector no tenga que adivinarlo.
        Gate::policy(\App\Models\Apertura::class, AperturaPolicy::class);

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

        foreach ([Servicio::class, Establecimiento::class, Serie::class, Periodo::class, Plantilla::class, User::class] as $modelo) {
            $modelo::observe(AuditoriaCatalogoObserver::class);
        }
    }
}
