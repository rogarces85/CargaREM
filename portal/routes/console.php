<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Tareas programadas del portal (RF-046)
|--------------------------------------------------------------------------
|
| **El portal no depende de que estas tareas corran.** El calendario se genera
| al pedir un período que no existe y las aperturas vencidas se controlan en
| cada carga. Lo de aquí son comodidades: mantener el calendario al día sin que
| nadie lo recuerde.
|
| Si el servidor no tiene cron, el sistema funciona igual. Es la regla de la
| casa (Constitución, principio 3).
|
*/

// El año siguiente, antes de que empiece: los períodos quedan creados y el
// administrador los ve en el calendario.
Schedule::command('rem:periodos:generar --siguiente')
    ->yearlyOn(12, 1, '03:00')
    ->description('Genera los períodos del año siguiente');

// Primero de cada mes: completa faltantes y corrige fechas que se movieron
// porque se cargó un feriado después.
Schedule::command('rem:periodos:generar')
    ->monthlyOn(1, '03:15')
    ->description('Mantiene los períodos del año en curso');

// Verificación semanal sin cambios: si alguien cargó un feriado y no recalculó,
// el comando deja constancia en el log.
Schedule::command('rem:calendario --verificar')
    ->weeklyOn(1, '04:00')
    ->description('Verifica el calendario contra los feriados cargados');

Schedule::command('rem:aperturas:cerrar')
    ->dailyAt('04:30')
    ->description('Cierra las aperturas de plazo vencidas');

Schedule::command('rem:validador:limpiar')
    ->monthlyOn(1, '05:00')
    ->description('Borra los archivos del Validador fuera de retención');
