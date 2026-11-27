<?php

use App\Http\Controllers\Admin\AsignacionController;
use App\Http\Controllers\Admin\CalendarioController;
use App\Http\Controllers\Admin\EstablecimientoController;
use App\Http\Controllers\Admin\FeriadoController;
use App\Http\Controllers\Admin\MatrizController;
use App\Http\Controllers\Admin\PeriodoController;
use App\Http\Controllers\Admin\PlantillaController;
use App\Http\Controllers\Admin\SerieController;
use App\Http\Controllers\Admin\ServicioController;
use App\Http\Controllers\Admin\UsuarioController;
use App\Http\Controllers\ArchivoController;
use App\Http\Controllers\AperturaController;
use App\Http\Controllers\CompletitudController;
use App\Http\Controllers\EnvioController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\NotificacionController;
use App\Http\Controllers\PanelController;
use App\Http\Controllers\PerfilController;
use App\Http\Controllers\RevisionController;
use App\Http\Controllers\ValidadorController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas web
|--------------------------------------------------------------------------
|
| El portal M7 se sirve desde aquí. Las rutas de autenticación las registra
| Fortify; las del dominio (envíos, revisión) llegan en F2 y F3.
|
*/

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('panel')
        : redirect()->route('login');
})->name('inicio');

Route::get('/health', HealthController::class)->name('health');

/*
 * Galería del sistema visual.
 *
 * Herramienta de diseño, no una pantalla del portal: sirve para revisar tokens,
 * estados y componentes sin depender de datos ni de sesión. Solo existe en
 * local y se retira en la Fase 6 del rediseño.
 */
if (app()->environment('local')) {
    Route::view('/componentes', 'componentes')->name('componentes');
}

Route::middleware('auth')->group(function () {
    Route::get('/panel', [PanelController::class, 'index'])->name('panel');

    Route::get('perfil', [PerfilController::class, 'edit'])->name('perfil.edit');
    Route::put('perfil', [PerfilController::class, 'update'])->name('perfil.update');
});

/*
 * Administración de catálogos (T-F1-22, T-F1-23).
 *
 * El permiso `catalogos.gestionar` es solo de admin y se comprueba en el
 * servidor, no en la vista (Constitución, principio 18).
 */
Route::middleware(['auth', 'permission:catalogos.gestionar'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::resource('servicios', ServicioController::class)->except(['show', 'destroy']);
        Route::resource('establecimientos', EstablecimientoController::class)->except(['show', 'destroy']);
        Route::resource('series', SerieController::class)->except(['show', 'destroy']);
        Route::resource('periodos', PeriodoController::class)->except(['show', 'destroy']);

        Route::resource('feriados', FeriadoController::class)->except(['show', 'destroy']);

        Route::get('calendario', [CalendarioController::class, 'index'])->name('calendario.index');
        Route::post('calendario/aplicar', [CalendarioController::class, 'aplicar'])->name('calendario.aplicar');

        Route::resource('plantillas', PlantillaController::class)->except(['show', 'destroy']);

        Route::get('matriz', [MatrizController::class, 'index'])->name('matriz.index');
        Route::get('matriz/{establecimiento}/edit', [MatrizController::class, 'edit'])->name('matriz.edit');
        Route::put('matriz/{establecimiento}', [MatrizController::class, 'update'])->name('matriz.update');
    });

/*
 * Usuarios: permiso propio usuarios.gestionar.
 */
Route::middleware(['auth', 'permission:usuarios.gestionar'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::resource('usuarios', UsuarioController::class)->except(['show', 'destroy']);

        // Reparto de establecimientos (RF-042, D-042). Va con los usuarios
        // porque es la misma decisión: quién puede ver qué.
        Route::get('asignaciones', [AsignacionController::class, 'index'])->name('asignaciones.index');
        Route::get('asignaciones/{usuario}/edit', [AsignacionController::class, 'edit'])->name('asignaciones.edit');
        Route::put('asignaciones/{usuario}', [AsignacionController::class, 'update'])->name('asignaciones.update');
    });
/*
 * Flujo de envío: dashboard, detalle, subida, descargas y transiciones.
 */
Route::middleware(['auth'])->group(function () {
    Route::get('envios', [EnvioController::class, 'index'])->name('envios.index');
    Route::post('envios', [EnvioController::class, 'store'])->name('envios.store');
    Route::get('envios/{envio}', [EnvioController::class, 'show'])->name('envios.show');
    Route::post('envios/{envio}/archivos', [EnvioController::class, 'subir'])->name('envios.subir')->middleware('throttle:10,60');
    Route::post('envios/{envio}/transicion', [EnvioController::class, 'transicion'])->name('envios.transicion');
    Route::get('envios/{envio}/reporte', [EnvioController::class, 'reporte'])->name('envios.reporte')->middleware('throttle:30,1');
    Route::get('envios/{envio}/archivos/{archivo}/descargar', [ArchivoController::class, 'descargar'])
        ->name('envios.archivo.descargar')->middleware('throttle:30,1');

    Route::get('notificaciones', [NotificacionController::class, 'index'])->name('notificaciones.index');
    Route::post('notificaciones/{notificacion}/leer', [NotificacionController::class, 'leer'])->name('notificaciones.leer');
    Route::post('notificaciones/leer-todas', [NotificacionController::class, 'leerTodas'])->name('notificaciones.leer-todas');
});
/*
 * Bandeja de revisión: revisor y admin del Servicio de Salud.
 */
Route::middleware(['auth', 'permission:envios.ver.ss'])->prefix('revision')->name('revision.')->group(function () {
    Route::get('/', [RevisionController::class, 'index'])->name('index');
    Route::post('{envio}/observar', [RevisionController::class, 'observar'])->name('observar');
    Route::post('{envio}/aprobar', [RevisionController::class, 'aprobar'])->name('aprobar');
    Route::post('{envio}/reabrir', [RevisionController::class, 'reabrir'])->name('reabrir');
    Route::get('reenvio', [RevisionController::class, 'formularioReenvio'])->name('reenvio.form');
    Route::post('reenvio', [RevisionController::class, 'solicitarReenvio'])->name('reenvio');
});
/*
 * Completitud del Servicio de Salud: matriz con semáforo y totales.
 */
Route::middleware(['auth'])->prefix('completitud')->name('completitud.')->group(function () {
    Route::get('/', [CompletitudController::class, 'index'])->name('index');
});

/*
 * Aperturas de fuera de plazo (RF-043, D-044).
 *
 * El encargado pide y consulta; el administrador resuelve, abre por su cuenta
 * y anula. La separación real la aplican la policy y el servicio; las rutas
 * solo agrupan.
 */
Route::middleware(['auth'])->prefix('aperturas')->name('aperturas.')->group(function () {
    Route::get('/', [AperturaController::class, 'index'])->name('index');
    Route::post('/', [AperturaController::class, 'store'])
        ->middleware('permission:aperturas.solicitar')->name('store');
    Route::post('otorgar', [AperturaController::class, 'otorgar'])
        ->middleware('permission:aperturas.resolver')->name('otorgar');
    Route::post('{apertura}/resolver', [AperturaController::class, 'resolver'])
        ->middleware('permission:aperturas.resolver')->name('resolver');
    Route::post('{apertura}/anular', [AperturaController::class, 'anular'])
        ->middleware('permission:aperturas.resolver')->name('anular');
});

/*
 * Módulo Validador (RF-040, D-047).
 *
 * Aislado del flujo: revisa un archivo suelto y registra el uso. El listado de
 * uso agregado es solo del administrador.
 */
Route::middleware(['auth', 'permission:validador.usar'])
    ->prefix('validador')
    ->name('validador.')
    ->group(function () {
        Route::get('/', [ValidadorController::class, 'index'])->name('index');
        // Misma tasa que `envios.subir`: la lectura abre un Excel y consume
        // memoria, así que no puede ser la única subida sin límite del portal.
        Route::post('/', [ValidadorController::class, 'store'])
            ->name('store')
            ->middleware('throttle:10,60');
        Route::get('{ejecucion}/reporte', [ValidadorController::class, 'reporte'])
            ->name('reporte')
            ->middleware('throttle:30,1');
        Route::get('estadisticas', [ValidadorController::class, 'estadisticas'])->name('estadisticas');
        Route::get('{ejecucion}', [ValidadorController::class, 'show'])->name('show');
    });
