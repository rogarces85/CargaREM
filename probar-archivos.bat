@echo off
setlocal enabledelayedexpansion
chcp 65001 >nul

rem =====================================================================
rem  Prueba los archivos REM de la carpeta portal\archivos.
rem  Muestra qué declara cada uno y compara contra los .esperado.json.
rem =====================================================================

set "PROYECTO=%~dp0"
set "PORTAL=%PROYECTO%portal"
set "PHP=C:\xampp\php\php.exe"
set "ARCHIVOS=%PORTAL%\archivos"

echo.
echo  ============================================
echo   Cargas REM - prueba de archivos
echo  ============================================
echo.

if not exist "%PORTAL%\artisan" (
    echo  [ERROR] Ejecute este archivo desde la raiz del repositorio.
    goto :fin
)

if not exist "%PHP%" set "PHP=php"

cd /d "%PORTAL%"

set "CUANTOS=0"
for %%f in ("%ARCHIVOS%\*.xlsm" "%ARCHIVOS%\*.xlsx") do (
    if exist "%%f" set /a CUANTOS+=1
)

if "%CUANTOS%"=="0" (
    echo  No hay archivos en %ARCHIVOS%
    echo  Copie ahi los .xlsm del Minsal y vuelva a ejecutar.
    goto :fin
)

echo  Archivos encontrados: %CUANTOS%
echo.
echo  --- 1. Que declara cada archivo ----------------------------
echo.
"%PHP%" artisan rem:inspeccionar "%ARCHIVOS%"

echo.
echo  --- 2. Paridad contra el ValidadorRem ----------------------
echo.
"%PHP%" artisan rem:paridad "%ARCHIVOS%"

echo.
echo  --- 3. Reporte completo del primer archivo -----------------
echo.
set "PRIMERO="
for %%f in ("%ARCHIVOS%\*.xlsm" "%ARCHIVOS%\*.xlsx") do (
    if not defined PRIMERO if exist "%%f" set "PRIMERO=%%f"
)

if defined PRIMERO (
    "%PHP%" artisan rem:validar "!PRIMERO!" --detalle
) else (
    echo  No hay archivos Excel que validar.
)

echo.
echo  Para comparar contra el ValidadorRem, cada archivo necesita su
echo  .esperado.json al lado. Ver docs\operacion\paridad.md
echo.

:fin
endlocal
pause
