<?php

use App\Http\Controllers\Admin\AuditoriaController;
use App\Http\Controllers\Admin\CatalogoSnapshotController;
use App\Http\Controllers\Admin\EstablecimientoController;
use App\Http\Controllers\Admin\MatrizController;
use App\Http\Controllers\Admin\PeriodoController;
use App\Http\Controllers\Admin\PlantillaController;
use App\Http\Controllers\Admin\SerieController;
use App\Http\Controllers\Admin\UsuarioController;
use App\Http\Controllers\Admin\ServicioController;
use App\Http\Controllers\ArchivoController;
use App\Http\Controllers\EnvioController;
use App\Http\Controllers\HealthController;
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
    return view('welcome');
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

        Route::get('plantillas', [PlantillaController::class, 'index'])->name('plantillas.index');

        Route::get('matriz', [MatrizController::class, 'index'])->name('matriz.index');
        Route::get('matriz/{establecimiento}/edit', [MatrizController::class, 'edit'])->name('matriz.edit');
        Route::put('matriz/{establecimiento}', [MatrizController::class, 'update'])->name('matriz.update');

        Route::get('snapshots', [CatalogoSnapshotController::class, 'index'])->name('snapshots.index');
        Route::post('snapshots/importar', [CatalogoSnapshotController::class, 'importar'])->name('snapshots.importar');
        Route::post('snapshots/{snapshot}/activar', [CatalogoSnapshotController::class, 'activar'])->name('snapshots.activar');
    });

/*
 * Auditoría: permiso propio, no el de catálogos.
 */
Route::middleware(['auth', 'permission:auditoria.ver'])
    ->get('admin/auditoria', [AuditoriaController::class, 'index'])
    ->name('admin.auditoria.index');
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
    Route::post('envios/{envio}/archivos', [EnvioController::class, 'subir'])->name('envios.subir');
    Route::post('envios/{envio}/transicion', [EnvioController::class, 'transicion'])->name('envios.transicion');
    Route::get('envios/{envio}/reporte', [EnvioController::class, 'reporte'])->name('envios.reporte');
    Route::get('envios/{envio}/archivos/{archivo}/descargar', [ArchivoController::class, 'descargar'])
        ->name('envios.archivo.descargar');
});
/*
 * Bandeja de revisión: revisor, admin y consulta del Servicio de Salud.
 */
Route::middleware(['auth', 'permission:envios.ver.ss'])->prefix('revision')->name('revision.')->group(function () {
    Route::get('/', [RevisionController::class, 'index'])->name('index');
    Route::post('{envio}/observar', [RevisionController::class, 'observar'])->name('observar');
    Route::post('{envio}/aprobar', [RevisionController::class, 'aprobar'])->name('aprobar');
    Route::post('{envio}/cargar', [RevisionController::class, 'cargar'])->name('cargar');
});
