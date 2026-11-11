<?php

use App\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas web
|--------------------------------------------------------------------------
|
| El portal M7 se sirve desde aquí. Las rutas de autenticación las registra
| Fortify; las del dominio (envíos, catálogos, revisión) llegan en F2 y F3.
|
*/

Route::get('/', function () {
    return view('welcome');
})->name('inicio');

Route::get('/health', HealthController::class)->name('health');

Route::middleware('auth')->group(function () {
    Route::get('/panel', fn () => view('panel'))->name('panel');
});
