<?php

use App\Http\Controllers\Admin\EstablecimientoController;
use App\Http\Controllers\Admin\MatrizController;
use App\Http\Controllers\Admin\PeriodoController;
use App\Http\Controllers\Admin\PlantillaController;
use App\Http\Controllers\Admin\SerieController;
use App\Http\Controllers\Admin\ServicioController;
use App\Http\Controllers\Admin\UsuarioController;
use App\Http\Controllers\ArchivoController;
use App\Http\Controllers\CompletitudController;
use App\Http\Controllers\EnvioController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\NotificacionController;
use App\Http\Controllers\RevisionController;
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

Route::middleware('auth')->group(function () {
    Route::get('/panel', fn () => view('panel'))->name('panel');
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
});
/*
 * Completitud del Servicio de Salud: matriz con semáforo y totales.
 */
Route::middleware(['auth'])->prefix('completitud')->name('completitud.')->group(function () {
    Route::get('/', [CompletitudController::class, 'index'])->name('index');
});
