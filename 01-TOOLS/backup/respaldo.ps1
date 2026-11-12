
# =====================================================================
#  Respaldo del portal Cargas REM (T-F6-10).
#
#  Copia la base y los archivos subidos en una carpeta con la fecha.
#  Se puede programar en el Programador de tareas de Windows.
#
#  Uso:  .\respaldo.ps1
#        .\respaldo.ps1 -Destino D:\respaldos
# =====================================================================

param(
    [string]$Destino = '',
    [string]$BaseDatos = 'cargas_rem',
    [string]$UsuarioMysql = 'root',
    [string]$ClaveMysql = '',
    [int]$DiasConservar = 30
)

$ErrorActionPreference = 'Stop'

$raiz = Split-Path -Parent (Split-Path -Parent $PSScriptRoot)

if ($Destino -eq '') { $Destino = Join-Path $raiz 'backup' }

if ($Destino -eq '') { $Destino = Join-Path $raiz 'backup' }
$portal = Join-Path $raiz 'portal'
$fecha = Get-Date -Format 'yyyy-MM-dd_HHmm'
$mysqldump = 'C:\xampp\mysql\bin\mysqldump.exe'

$argumentosClave = @()
if ($ClaveMysql -ne '') { $argumentosClave = @("-p$ClaveMysql") }

if (-not (Test-Path $mysqldump)) {
    Write-Error "No se encuentra mysqldump en $mysqldump"
    exit 1
}

if (-not (Test-Path $Destino)) {
    New-Item -ItemType Directory -Path $Destino -Force | Out-Null
}

Write-Host ''
Write-Host '=== Respaldo de Cargas REM ===' -ForegroundColor Cyan
Write-Host "Destino: $Destino"
Write-Host ''

# --- Base de datos ---------------------------------------------------
$archivoDb = Join-Path $Destino "db_$fecha.sql"
Write-Host "[1/2] Base $BaseDatos..."

& $mysqldump -u $UsuarioMysql @argumentosClave --single-transaction --routines --triggers --default-character-set=utf8mb4 $BaseDatos 2>$null | Set-Content -Path $archivoDb -Encoding utf8

if (-not (Test-Path $archivoDb) -or (Get-Item $archivoDb).Length -eq 0) {
    Write-Error 'El respaldo de la base quedó vacío.'
    exit 1
}

$tamanoDb = [Math]::Round((Get-Item $archivoDb).Length / 1MB, 1)
Write-Host "      $archivoDb ($tamanoDb MB)" -ForegroundColor Green

# --- Archivos subidos ------------------------------------------------
$archivoStorage = Join-Path $Destino "storage_$fecha.tar.gz"
$privado = Join-Path $portal 'storage\app\private'

Write-Host '[2/2] Archivos subidos...'

if (Test-Path $privado) {
    tar -czf $archivoStorage -C $privado .
    $tamanoStorage = [Math]::Round((Get-Item $archivoStorage).Length / 1MB, 1)
    Write-Host "      $archivoStorage ($tamanoStorage MB)" -ForegroundColor Green
} else {
    Write-Host '      No hay archivos privados todavía.' -ForegroundColor Yellow
}

# --- Limpieza de respaldos viejos ------------------------------------
$limite = (Get-Date).AddDays(-$DiasConservar)
$viejos = Get-ChildItem $Destino -File | Where-Object { $_.LastWriteTime -lt $limite }

if ($viejos.Count -gt 0) {
    Write-Host ''
    Write-Host "Borrando $($viejos.Count) respaldos de más de $DiasConservar días..."
    $viejos | Remove-Item -Force
}

Write-Host ''
Write-Host 'Respaldo terminado.' -ForegroundColor Cyan
Write-Host ''
Write-Host 'Para restaurar:'
Write-Host "  mysql -u root -e `"CREATE DATABASE $BaseDatos`""
Write-Host "  mysql -u root $BaseDatos < $archivoDb"
Write-Host "  tar -xzf $archivoStorage -C portal\storage\app\private"
Write-Host ''
