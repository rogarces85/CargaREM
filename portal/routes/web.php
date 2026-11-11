<?php

use App\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas web
|--------------------------------------------------------------------------
|
| El portal M7 se sirve desde aquí. Las rutas del dominio (envíos, catálogos,
| revisión) llegan en F2 y F3; por ahora solo la comprobación de salud.
|
*/

Route::get('/', function () {
    return view('welcome');
})->name('inicio');

Route::get('/health', HealthController::class)->name('health');
