#Requires -Version 7.0
<#
.SYNOPSIS
    Exporta los catálogos de las bases Access de Cargas REM a CSV.

.DESCRIPTION
    Lee la instalación oficial de ValidadorRem en solo lectura y produce los CSV
    que el portal M7 importa a MySQL. El servidor de producción es Linux y no
    puede abrir un .mdb, por eso la conversión ocurre aquí, en Windows.

    Flujo: .mdb -> copia temporal -> ADODB/ACE -> CSV + manifest.json

    El .mdb original nunca se abre en su carpeta: Access necesita crear un
    archivo de bloqueo .ldb y Program Files no lo permite.

.PARAMETER SourceDir
    Instalación oficial del sistema. No cambiar sin registrar una decisión.

.PARAMETER OutDir
    Destino de los CSV. Por defecto fuera del repositorio, porque los catálogos
    no se versionan.

.PARAMETER ServicioId
    Id del Servicio de Salud a exportar. 23 = Osorno.

.PARAMETER Only
    Limita la exportación a las tablas indicadas, por nombre de archivo.

.EXAMPLE
    .\export-mdb.ps1

.EXAMPLE
    .\export-mdb.ps1 -Only diccionario,establecimientos
#>
[CmdletBinding()]
param(
    [string]$SourceDir = 'C:\Program Files (x86)\Minsal\Cargas Rem 2016',
    [string]$OutDir    = 'C:\xampp\_snapshot_m7',
    [int]$ServicioId   = 23,
    [string[]]$Only    = @(),
    [switch]$KeepTemp
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

# --- Definición de la exportación -------------------------------------------
# El orden imita las dependencias de claves foráneas del portal.
$tablas = @(
    [pscustomobject]@{
        Fuente = 'Global.mdb'; Tabla = 'mpServicio'; Archivo = 'servicios'
        Sql = 'SELECT [IdServicio],[NServicio],[IdRegion],[Orden],[ElimID] FROM [mpServicio]'
    }
    [pscustomobject]@{
        Fuente = 'Global.mdb'; Tabla = 'mpEstablecimiento'; Archivo = 'establecimientos'
        Sql = "SELECT [IdEstablecimiento],[NEstablecimiento],[Direccion],[Telefono],[Fax],[IdServicio],[IdComuna],[IdDependencia],[IdTipo],[ElimID],[Propiedad] FROM [mpEstablecimiento] WHERE [IdServicio] = $ServicioId AND ([ElimID] = 0 OR [ElimID] IS NULL)"
    }
    [pscustomobject]@{
        Fuente = 'Global.mdb'; Tabla = 'version (distinct series)'; Archivo = 'series'
        Sql = 'SELECT DISTINCT [Serie],[SerieExcel] FROM [version]'
    }
    [pscustomobject]@{
        Fuente = 'Global.mdb'; Tabla = 'version'; Archivo = 'plantillas'
        Sql = 'SELECT [mes],[ano],[version],[Serie],[SerieExcel] FROM [version]'
    }
    [pscustomobject]@{
        Fuente = 'Global.mdb'; Tabla = 'Thojas'; Archivo = 'plantilla_hojas'
        Sql = 'SELECT [Serie],[Hoja],[ano],[mes],[Rango] FROM [Thojas]'
    }
    [pscustomobject]@{
        Fuente = 'Global.mdb'; Tabla = 'Diccionario'; Archivo = 'diccionario'
        Sql = 'SELECT [CodigoPrestacion],[Mes],[Year],[TextoPrestacion],[Hoja],[Serie],[Posicion],[tipoDato],[linea],[Inicio],[Fin] FROM [Diccionario]'
    }
    [pscustomobject]@{
        Fuente = 'Global.mdb'; Tabla = 'Excep'; Archivo = 'excepciones'
        Sql = 'SELECT [CodigoPrestacion],[Mes],[Year],[Hoja],[Serie],[Posicion],[tipoDato],[colexcep] FROM [Excep]'
    }
    [pscustomobject]@{
        Fuente = 'Global.mdb'; Tabla = 'colexcep'; Archivo = 'excepciones_columnas'
        Sql = 'SELECT [codpres],[col] FROM [colexcep]'
    }
    [pscustomobject]@{
        Fuente = 'Trans.mdb'; Tabla = 'HistCarga'; Archivo = 'hist_carga'
        Sql = 'SELECT [IdEstablecimiento],[Mes],[Ano],[Serie] FROM [HistCarga]'
    }
    [pscustomobject]@{
        Fuente = 'Trans.mdb'; Tabla = 'SetServicio'; Archivo = 'servicio_configurado'
        Sql = 'SELECT [idServicio],[Nservicio] FROM [SetServicio]'
    }
)

$todasLasTablas = $tablas.Archivo

if ($Only.Count -gt 0) {
    $tablas = @($tablas | Where-Object { $Only -contains $_.Archivo })
    if ($tablas.Count -eq 0) {
        throw "Ninguna coincide con -Only. Disponibles: $($todasLasTablas -join ', ')"
    }
}

# --- Validaciones previas ----------------------------------------------------
$inv  = [System.Globalization.CultureInfo]::InvariantCulture
$utf8 = [System.Text.UTF8Encoding]::new($false)

if (-not (Test-Path -LiteralPath $SourceDir)) {
    throw "No existe la carpeta de origen: $SourceDir"
}

$fuentes = @($tablas | Select-Object -ExpandProperty Fuente -Unique)
foreach ($f in $fuentes) {
    $ruta = Join-Path $SourceDir $f
    if (-not (Test-Path -LiteralPath $ruta)) { throw "Falta la base $f en $SourceDir" }
}

New-Item -ItemType Directory -Path $OutDir -Force | Out-Null
$temp = Join-Path ([System.IO.Path]::GetTempPath()) ('mdb_export_' + [guid]::NewGuid().ToString('N').Substring(0, 8))
New-Item -ItemType Directory -Path $temp -Force | Out-Null

# --- Conversión de un valor a campo CSV --------------------------------------
# inline en el bucle caliente; esta versión se usa solo para la cabecera y casos sueltos
function Format-Campo {
    param($Valor)
    if ($null -eq $Valor -or $Valor -is [System.DBNull]) { return '' }
    if ($Valor -is [string]) {
        if ($Valor.Length -eq 0) { return '' }
        if ($Valor.IndexOfAny([char[]]@('"', ',', "`r", "`n")) -ge 0) {
            return '"' + $Valor.Replace('"', '""') + '"'
        }
        return $Valor
    }
    if ($Valor -is [datetime]) { return $Valor.ToString('yyyy-MM-dd HH:mm:ss', $inv) }
    if ($Valor -is [double] -or $Valor -is [single] -or $Valor -is [decimal]) {
        return $Valor.ToString('0.###############', $inv)
    }
    if ($Valor -is [bool]) { if ($Valor) { return '1' } else { return '0' } }
    return [string]::Format($inv, '{0}', $Valor)
}

# --- Exportación de una tabla ------------------------------------------------
function Export-Tabla {
    param($Connection, $Definicion, $Destino)

    $rs = $Connection.Execute($Definicion.Sql)
    $campos = @()
    for ($i = 0; $i -lt $rs.Fields.Count; $i++) { $campos += $rs.Fields.Item($i).Name }

    $cabecera = @($campos | ForEach-Object { Format-Campo $_ }) -join ','
    $nCampos = $campos.Count
    $filas = 0

    $sw = [System.IO.StreamWriter]::new($Destino, $false, $utf8)
    try {
        $sw.WriteLine($cabecera)

        $lote = 5000
        while ($true) {
            # GetRows trae un SAFEARRAY indexado [campo, fila]; mucho más rápido
            # que recorrer el Recordset campo a campo por COM.
            $arr = $rs.GetRows($lote)
            if ($null -eq $arr) { break }
            $n = $arr.GetLength(1)
            if ($n -eq 0) { break }

            $vals = [object[]]::new($nCampos)
            for ($r = 0; $r -lt $n; $r++) {
                for ($c = 0; $c -lt $nCampos; $c++) {
                    $v = $arr[$c, $r]
                    if ($null -eq $v -or $v -is [System.DBNull]) {
                        $vals[$c] = ''
                    }
                    elseif ($v -is [string]) {
                        if ($v.Length -eq 0) { $vals[$c] = '' }
                        elseif ($v.IndexOfAny([char[]]@('"', ',', "`r", "`n")) -ge 0) {
                            $vals[$c] = '"' + $v.Replace('"', '""') + '"'
                        }
                        else { $vals[$c] = $v }
                    }
                    elseif ($v -is [double] -or $v -is [single] -or $v -is [decimal]) {
                        $vals[$c] = $v.ToString('0.###############', $inv)
                    }
                    elseif ($v -is [datetime]) {
                        $vals[$c] = $v.ToString('yyyy-MM-dd HH:mm:ss', $inv)
                    }
                    else {
                        $vals[$c] = [string]::Format($inv, '{0}', $v)
                    }
                }
                $sw.WriteLine(($vals -join ','))
                $filas++
            }
            if ($n -lt $lote) { break }
        }
    }
    finally {
        $sw.Dispose()
        $rs.Close()
    }

    [pscustomobject]@{
        Archivo = [System.IO.Path]::GetFileName($Destino)
        Fuente  = "$($Definicion.Fuente): $($Definicion.Tabla)"
        Filas   = $filas
        Bytes   = (Get-Item -LiteralPath $Destino).Length
        Sha256  = (Get-FileHash -LiteralPath $Destino -Algorithm SHA256).Hash.ToLowerInvariant()
    }
}

# --- Exportación -------------------------------------------------------------
Write-Host "Origen  : $SourceDir"
Write-Host "Destino : $OutDir"
Write-Host "Servicio: $ServicioId"
Write-Host ''

$registros = @()
$cronometro = [System.Diagnostics.Stopwatch]::StartNew()

try {
    foreach ($fuente in $fuentes) {
        $grupo  = @($tablas | Where-Object Fuente -eq $fuente)
        $origen = Join-Path $SourceDir $fuente
        $copia  = Join-Path $temp $fuente

        Write-Host ("Copiando {0} ({1:N1} MB)..." -f $fuente, ((Get-Item -LiteralPath $origen).Length / 1MB))
        Copy-Item -LiteralPath $origen -Destination $copia -Force

        $cn = New-Object -ComObject ADODB.Connection
        try {
            $cn.Open("Provider=Microsoft.ACE.OLEDB.12.0;Data Source=$copia;Mode=Read;")

            foreach ($def in $grupo) {
                $destino = Join-Path $OutDir ($def.Archivo + '.csv')
                Write-Host ("  {0,-24}" -f $def.Archivo) -NoNewline
                $t0 = [System.Diagnostics.Stopwatch]::StartNew()
                $reg = Export-Tabla -Connection $cn -Definicion $def -Destino $destino
                $t0.Stop()
                $registros += $reg
                Write-Host ("{0,9:N0} filas  {1,6:N1} s" -f $reg.Filas, $t0.Elapsed.TotalSeconds)
            }
        }
        finally {
            $cn.Close()
            [System.Runtime.InteropServices.Marshal]::ReleaseComObject($cn) | Out-Null
            Remove-Item -LiteralPath $copia -Force -ErrorAction SilentlyContinue
        }
    }
}
finally {
    if (-not $KeepTemp) { Remove-Item -LiteralPath $temp -Recurse -Force -ErrorAction SilentlyContinue }
    else { Write-Host "Temporal conservado en $temp" }
}

$cronometro.Stop()

# --- Manifiesto --------------------------------------------------------------
$manifest = [ordered]@{
    generado    = (Get-Date).ToString('yyyy-MM-ddTHH:mm:sszzz')
    fuente      = $SourceDir
    servicio_id = $ServicioId
    equipo      = $env:COMPUTERNAME
    duracion_s  = [math]::Round($cronometro.Elapsed.TotalSeconds, 1)
    total_filas = [int](($registros | Measure-Object -Property Filas -Sum).Sum)
    tablas      = $registros
}

$manifestPath = Join-Path $OutDir 'manifest.json'
$manifest | ConvertTo-Json -Depth 5 | Set-Content -LiteralPath $manifestPath -Encoding utf8NoBOM

$readme = @'
Snapshot de catálogos REM — NO versionar

Estos CSV salen de las bases Access oficiales del sistema Cargas REM y se
importan a MySQL con el importador del portal. No se commitean: contienen el
padrón de establecimientos y las posiciones de validación.

Origen oficial: C:\Program Files (x86)\Minsal\Cargas Rem 2016
Procedencia y hashes: manifest.json
'@
Set-Content -LiteralPath (Join-Path $OutDir 'LEEME.txt') -Value $readme -Encoding utf8NoBOM

# --- Resumen -----------------------------------------------------------------
Write-Host ''
Write-Host ("Total: {0:N0} filas en {1} tablas, {2:N1} s" -f $manifest.total_filas, $registros.Count, $manifest.duracion_s)
Write-Host "Manifiesto: $manifestPath"
$registros | Select-Object Archivo, Filas, @{n = 'MB'; e = { [math]::Round($_.Bytes / 1MB, 1) } } | Format-Table -AutoSize
