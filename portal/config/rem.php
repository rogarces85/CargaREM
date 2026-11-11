<?php

/*
|--------------------------------------------------------------------------
| Configuración del dominio REM
|--------------------------------------------------------------------------
|
| Centraliza los números que la Constitución y el PLAN fijan como
| presupuesto. Si un valor cambia, se cambia aquí y se cita la decisión.
|
*/

return [

    /*
    | Archivos que el portal acepta.
    |
    | `max_kb` es una regla de negocio, no un reflejo de php.ini: el servidor
    | de producción tiene upload_max_filesize=2M y el local 256M. El límite
    | se valida en código para que ambos entornos se comporten igual.
    */
    'archivos' => [
        'max_kb'      => (int) env('REM_MAX_ARCHIVO_KB', 2048),
        'extensiones' => ['xls', 'xlsx', 'xlsm'],
        'disco'       => 'local',
        'ruta'        => 'envios',
    ],

    /*
    | Presupuesto de validación (Constitución, principio 25).
    */
    'validacion' => [
        'timeout_segundos'      => 90,
        'memoria_mb'            => 512,
        'concurrentes'          => 2,
        'espera_lock_segundos'  => 10,
    ],

    /*
    | Series del REM.
    |
    | `codigos` son las externas (las que el establecimiento ve en el
    | archivo). `locales` está vacío porque SAX queda fuera del MVP (D-006).
    */
    'series' => [
        'codigos' => ['A', 'BS', 'BM', 'D', 'P', 'F'],
        'locales' => [],
    ],

    /*
    | Puente con CARGAS REM (D-007: opción A, descarga manual).
    */
    'paquete' => [
        'manifiesto' => 'manifiesto.json',
        'algoritmo'  => 'sha256',
        'formato'    => 'zip',
    ],

];
