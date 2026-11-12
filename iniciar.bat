@echo off
setlocal enabledelayedexpansion
chcp 65001 >nul

rem =====================================================================
rem  Arranca el portal Cargas REM desde cero o donde quedó.
rem  Se puede ejecutar las veces que haga falta: no repite lo ya hecho.
rem =====================================================================

set "PROYECTO=%~dp0"
set "PORTAL=%PROYECTO%portal"
set "PHP=C:\xampp\php\php.exe"
set "MYSQL=C:\xampp\mysql\bin\mysql.exe"
set "DB=cargas_rem"
set "SNAPSHOT=%SNAPSHOT%"
if "%SNAPSHOT%"=="" set "SNAPSHOT=C:\xampp\_snapshot_m7"
set "URL=http://127.0.0.1:8000/login"

echo.
echo  ============================================
echo   Cargas REM - arranque del portal
echo  ============================================
echo.

if not exist "%PORTAL%\artisan" (
    echo  [ERROR] No se encuentra %PORTAL%\artisan
    echo          Este archivo debe estar en la raiz del repositorio.
    goto :fin_error
)

rem --- PHP ------------------------------------------------------------
if not exist "%PHP%" (
    echo  [AVISO] No está en %PHP%. Se usará el PHP del PATH.
    set "PHP=php"
)

set "TMPV=%TEMP%\rem_version.txt"
"%PHP%" -r "echo PHP_VERSION;" > "%TMPV%" 2>nul
set "VERSION="
if exist "%TMPV%" set /p VERSION=<"%TMPV%"
if "%VERSION%"=="" (
    echo  [ERROR] No se pudo ejecutar PHP.
    goto :fin_error
)

echo  PHP ....... %VERSION%
if "%VERSION:~0,3%" LSS "8.2" (
    echo  [ERROR] El portal necesita PHP 8.2 o superior. Tiene %VERSION%.
    goto :fin_error
)

rem --- MySQL ----------------------------------------------------------
if not exist "%MYSQL%" set "MYSQL=mysql"

"%MYSQL%" -u root -e "SELECT 1" >nul 2>&1
if errorlevel 1 (
    echo  [ERROR] No se pudo conectar a MySQL. ¿Está encendido en XAMPP?
    goto :fin_error
)

echo  MySQL ..... conectado
echo.

cd /d "%PORTAL%"

rem --- Dependencias ---------------------------------------------------
if not exist "vendor\autoload.php" (
    echo  [1/6] Instalando dependencias de Composer...
    call composer install --no-interaction --quiet
    if errorlevel 1 (
        echo  [ERROR] Falló composer install.
        goto :fin_error
    )
) else (
    echo  [1/6] Dependencias ya instaladas.
)

rem --- Configuración --------------------------------------------------
if not exist ".env" (
    echo  [2/6] Creando .env desde .env.example...
    copy /y ".env.example" ".env" >nul
    "%PHP%" artisan key:generate --force --quiet
) else (
    echo  [2/6] .env ya existe.
)

rem --- Base de datos --------------------------------------------------
echo  [3/6] Asegurando la base %DB%...
"%MYSQL%" -u root -e "CREATE DATABASE IF NOT EXISTS %DB% CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>nul

echo  [4/6] Aplicando migraciones...
"%PHP%" artisan migrate --force --quiet
if errorlevel 1 (
    echo  [ERROR] Fallaron las migraciones. Revise la conexión en .env
    goto :fin_error
)

rem --- Datos mínimos --------------------------------------------------
set "TMPU=%TEMP%\rem_usuarios.txt"
"%MYSQL%" -u root -N -e "SELECT COUNT(*) FROM %DB%.users;" > "%TMPU%" 2>nul
set "USUARIOS="
if exist "%TMPU%" set /p USUARIOS=<"%TMPU%"

if "!USUARIOS!"=="0" (
    echo  [5/6] Sembrando roles, permisos y datos mínimos...
    "%PHP%" artisan db:seed --force --quiet
) else (
    echo  [5/6] Datos mínimos ya cargados ^(!USUARIOS! usuarios^).
)

rem --- Catálogo -------------------------------------------------------
set "TMPS=%TEMP%\rem_snapshots.txt"
"%MYSQL%" -u root -N -e "SELECT COUNT(*) FROM %DB%.catalogo_snapshots WHERE activo=1;" > "%TMPS%" 2>nul
set "SNAPSHOTS="
if exist "%TMPS%" set /p SNAPSHOTS=<"%TMPS%"

if "!SNAPSHOTS!"=="0" (
    if exist "%SNAPSHOT%\manifest.json" (
        echo  [6/6] Importando el catálogo desde %SNAPSHOT%...
        "%PHP%" artisan catalogos:importar "%SNAPSHOT%" --activar
    ) else (
        echo  [6/6] Sin catálogo activo y sin snapshot en %SNAPSHOT%
        echo        El portal abre, pero no podrá validar archivos.
        echo        Para regenerarlo: 01-TOOLS\rem-export\export-mdb.ps1
    )
) else (
    echo  [6/6] Catálogo activo.
)

rem --- Servidor -------------------------------------------------------
echo.
echo  ============================================
echo   Listo. Abriendo el portal...
echo  ============================================
echo.
echo   %URL%
echo.
echo   Usuario: admin@ssosorno.cl
echo   Clave:   Portal2026^!
echo.
echo   Para detener este servidor: Ctrl+C
echo.

start "" "%URL%"

"%PHP%" artisan serve --host=127.0.0.1 --port=8000

goto :fin

:fin_error
echo.
echo  El arranque se detuvo. Corrija lo indicado y vuelva a ejecutar.
echo.

:fin
endlocal
pause
