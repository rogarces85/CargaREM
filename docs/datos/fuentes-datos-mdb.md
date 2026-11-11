---
type: datos
title: Fuentes de datos maestros — las bases Access de Cargas REM
description: Inventario verificado de Global.mdb, Trans.mdb y las bases DatosSERIE del sistema ValidadorRem, con volúmenes reales y el mapeo hacia el modelo del portal M7.
tags: [datos, access, mdb, catalogos, importacion, etl]
timestamp: 2026-11-11T16:00:00Z
topic: datos
status: verificado
---

# Fuentes de datos maestros

> **Verificado el 2026-11-11** leyendo las bases reales con `Microsoft.ACE.OLEDB.12.0`.
> Los volúmenes de esta página son conteos, no estimaciones.

---

## 1. Dónde están

```text
C:\Program Files (x86)\Minsal\Cargas Rem 2016\
```

> ### Fuente oficial de los datos
>
> **Esta ruta es la fuente oficial de los datos del portal** (confirmado el 2026-11-11, C-013).
>
> | Regla | Consecuencia |
> |---|---|
> | Toda importación sale de aquí | Ningún catálogo ni histórico se toma de otra copia |
> | Las demás copias del equipo son respaldos o copias de trabajo | Consultarlas no es válido, aunque el archivo se llame igual |
> | Si la ruta cambia, se registra una decisión nueva | Y se actualiza este documento en el mismo commit |
> | El acceso es de solo lectura | Se abre una copia, nunca el original (§5) |

Es la instalación del sistema **Cargas REM** (`ValidadorRem.exe`, CargaRem 2.00.418). Ahí viven todas las bases que alimentan el portal.

**No se pueden abrir en su carpeta.** Access intenta crear un archivo de bloqueo `.ldb` junto al `.mdb`, y `Program Files` está protegido: devuelve «No se pudo bloquear el archivo». La lectura se hace sobre una **copia** en una carpeta escribible. Ver §5.

---

## 2. Catálogos — `Global.mdb` (402,9 MB)

Es el cerebro del validador. Todo lo que el portal necesita para interpretar un Excel REM sale de aquí.

| Tabla | Filas | Qué contiene | Destino en el portal |
|---|---|---|---|
| `Diccionario` | **185.526** | Posición de cada prestación: hoja, línea, columnas `inicio`→`fin`, `TipoDato`, por año/mes/serie | `diccionario` |
| `Thojas` | **1.129** | Qué hojas leer y con qué rango (`A3:L35`) por serie y año | `plantilla_hojas` |
| `Excep` | **5.664** | Celdas que se ignoran por prestación, hoja y serie | `excepciones` |
| `colexcep` | **2.977** | Columnas a omitir por código de prestación | `excepciones` |
| `mpEstablecimiento` | **3.207** | Maestro nacional de establecimientos (86 son del Servicio de Salud Osorno) | `establecimientos` |
| `mpServicio` | **30** | Servicios de salud del país | `servicios` |
| `Meses` | **12** | 1=Enero … 12=Diciembre | catálogo de meses (seeder) |
| `version` | **939** | Versión de plantilla esperada por año/mes/serie | `plantillas.version` |
| `Software` | **1** | `CargaRem` / `2.00.418` | versión del catálogo importado |

### Dos detalles que rompen importaciones ingenuas

1. **`mpEstablecimiento` son 3.207 filas de todo Chile.** Solo 86 son de Osorno. La importación debe filtrar por `IdServicio = 23` y `ElimID = 0` o nulo. Sin ese filtro, el portal muestra establecimientos de otras regiones.
2. **`version` guarda la serie con dos nombres.** `Serie` es el interno (`SERIE BS1`) y `SerieExcel` es el del archivo (`SERIE BS`). El portal debe conservar ambos: el nombre del Excel para validar, el interno para cruzar con `Diccionario` y `Thojas`.

---

## 3. Estado operativo — `Trans.mdb` (0,6 MB)

| Tabla | Filas | Qué contiene |
|---|---|---|
| `HistCarga` | **4.000** | Qué establecimiento cargó qué serie en qué mes y año |
| `LogError` | **0** | Log de errores de la última operación (vacío) |
| `SetServicio` | **1** | El servicio configurado: `IdServicio 23` — **Osorno** |

`HistCarga` es la memoria de "quién ya entregó qué". Es la fuente natural para **sembrar el histórico de completitud** que el portal necesita en el tablero (F4).

`LogError` está vacío: no hay errores históricos que migrar.

---

## 4. Datos — las bases `DatosSERIE<X><AÑO>.mdb`

Una base por serie y año. Se crearon copiando `MasterDB.mdb`, que es la plantilla con `Col01…Col50`.

| Base | Tamaño | Filas |
|---|---|---|
| `DatosSERIEA2025.mdb` | 161,7 MB | **163.105** |
| `DatosSERIEA2026.mdb` | 123,1 MB | **125.843** |
| `DatosSERIEP2025.mdb` | 29,3 MB | **29.850** |
| `DatosSERIEBS2025.mdb` | 25,8 MB | **27.328** |
| `DatosSERIEP2026.mdb` | 15,0 MB | **15.060** |
| `DatosSERIEBM2025.mdb` | 6,1 MB | **11.874** |
| `DatosSERIEBM2026.mdb` | 8,6 MB | **9.415** |
| `DatosSERIED2025.mdb` | 6,7 MB | **6.880** |
| `DatosSERIED2026.mdb` | 2,0 MB | **3.698** |
| **Total** | **378 MB** | **393.053 filas** |

Sin datos (plantilla recién copiada, 0,2 MB): `DatosSERIEBS2026`, `DatosSERIEF2025`, `DatosSERIEF2026`, y todas las `DatosSERIE*2012`.

### Lo que no tiene datos

- **Todas las `ControlSERIE<X><AÑO>.mdb` están vacías** (0,2 MB, fecha 2015). Los datos de control —las hojas resumen A01/RESUMEN— **nunca se cargaron**. Si el portal los necesita, se capturan desde ahora: no hay histórico que migrar.
- **No existe serie SAX en ninguna base.** Confirma D-006: SAX queda fuera del MVP porque no hay datos ni plantilla contra los cuales validar.

### Plantillas y temporales — no se importan

| Archivo | Qué es |
|---|---|
| `MasterDB.mdb` | Plantilla de `DatosSERIE*`: tabla `Datos` con `Col01…Col50` |
| `MasterC.mdb` | Plantilla de `ControlSERIE*`: `col01…col05` |
| `MASTER.mdb` | Plantilla del archivo de carga al Minsal (`Datos`, `DatosC`, `Registros`) |
| `Temporal.mdb` | Matriz de establecimientos × meses, se regenera en cada informe |
| `TemporalControl.mdb` | Establecimientos sin datos de control |
| `TemporalIncorrectos.mdb` | Errores de la carga masiva |
| `Control.mdb`, `DatosSERIE*2012.mdb` | Residuos sin uso (el propio validador no los referencia) |

---

## 5. Cómo se leen: solo en Windows, solo en la fase 1

El servidor de producción es Linux y **no puede abrir un `.mdb`**. Ni PHP, ni MySQL, ni el sistema operativo tienen driver para Access.

Por eso la importación ocurre en la **fase 1**, en la máquina Windows, y produce un artefacto portable:

```text
.mdb  →  [ACE OLEDB, Windows]  →  CSV  →  [Laravel]  →  MySQL
```

Método probado el 2026-11-11 desde PowerShell:

```powershell
$cn = New-Object -ComObject ADODB.Connection
$cn.Open("Provider=Microsoft.ACE.OLEDB.12.0;Data Source=C:\ruta\copia\Global.mdb;Mode=Read;")
$rs = $cn.Execute("SELECT COUNT(*) FROM Diccionario")
```

Reglas del proceso:

1. **Copiar el `.mdb`** antes de abrirlo. Nunca trabajar sobre `Program Files`.
2. **`Mode=Read`** siempre. La fuente no se modifica.
3. **Filtrar en el origen**, no en Laravel: `mpEstablecimiento` por `IdServicio = 23`.
4. **Calcular el `sha256`** de cada CSV generado y guardarlo en `catalogo_snapshots` (PLAN.md DAT-11). Un catálogo sin hash no es reproducible.
5. **Un solo snapshot activo.** Importar genera un snapshot inactivo; activarlo desactiva el anterior (T-F1-21).

Esto mantiene válida la tarea **T-F1-20**: el importador Laravel consume CSV o ZIP, nunca el `.mdb`.

---

## 6. Orden de importación

Las tablas tienen dependencias. Este orden evita claves foráneas rotas:

1. `mpServicio` → `servicios`
2. `mpEstablecimiento` (filtrado) → `establecimientos`
3. Series (`A`, `BS`, `BM`, `D`, `P`, `F`) → `series`
4. `version` → `plantillas`
5. `Thojas` → `plantilla_hojas`
6. `Diccionario` → `diccionario`
7. `Excep` + `colexcep` → `excepciones`
8. `HistCarga` → histórico de completitud

`Meses` y `Software` no son tablas del portal: alimentan el *seeder* y la traza del snapshot.

---

## 7. Copias fuera de la instalación

Estas existen en el equipo pero **no son fuente**: son copias de trabajo o respaldos.

| Archivo | Dónde | Nota |
|---|---|---|
| `SA_2026.mdb` (61,7 MB) | `Desktop\REM Proyecto` | Acompañado de `INFORME.md` con el análisis del sistema |
| `SERIEA-08-15.mdb` (47,7 MB) | `Documents\REM-respaldo-datos` | Respaldo con `Diccionario.xlsx` y `glosa_a_2026.sql` |
| `anexo_2026.mdb` (59,6 MB) | `Desktop`, `Downloads` | Serie Anexo; no forma parte del validador |
| `Cargas Rem 2016\` completo | `C:\Users\Lenovo\dyad-apps\Cargas REM\` | **Copia antigua del sistema** (2025-11-18), dentro de un repo git con `AI_RULES.md`. `Global.mdb` de 83 MB contra 403 MB de la instalación; `DatosSERIEA2025` de 106 MB contra 161 MB. **No usar como fuente** |
| `ValidacionesQuery.mdb`, `DBEgreso.mdb` | `Documents\MINSAL\VEDatabases` | Otros sistemas (Egresos) |

La fuente de verdad es `C:\Program Files (x86)\Minsal\Cargas Rem 2016`, porque es la que el sistema mantiene actualizada contra los servicios web del Minsal.

---

## 8. Riesgos detectados al leer

| # | Riesgo | Evidencia |
|---|---|---|
| 1 | **Un catálogo importado puede quedar viejo en silencio.** La base que existía en MariaDB tenía 180.536 filas de `Diccionario`; la real tiene **185.526**. Faltaban 4.990 prestaciones | Comparación 2026-11-11 |
| 2 | **`Diccionario` es por año y mes.** Una prestación puede existir en 2026 y no en 2025. Importar solo el año actual rompe la consulta histórica | Columnas `Year`, `Mes` |
| 3 | **La regla de decimales no está en el catálogo.** El validador acepta un decimal solo en las hojas `A09`, `A21` y en las series `BS` y `D`; el resto exige enteros | Código decompilado, `INFORME.md` §4.7 |
| 4 | **Desde 2017 el formato de subida es `.xlsm` exclusivamente.** Años anteriores aceptan `.xls` y `.xlsx` | `CargaDatos.Button1_Click` |
| 5 | **La versión de plantilla se valida contra `version`, que se refresca por web service.** Si el catálogo local está desactualizado, se rechazan archivos válidos | `verifica_version` |

El riesgo 3 y el 4 afectan directamente la paridad (Constitución, principio 10) y deben quedar cubiertos por los *golden files* de T-F0-02/03.

---

## 9. Estado

| Elemento | Estado |
|---|---|
| Inventario de bases | ✅ verificado |
| Volúmenes | ✅ contados |
| Mapeo a tablas del portal | ✅ definido (§2, §6) |
| Exportador `.mdb → CSV` | ✅ [`01-TOOLS/rem-export`](../../01-TOOLS/rem-export/README.md) — 200.367 filas en 26 s |
| Importador Laravel CSV → MySQL | ❌ por construir (T-F1-20) |
| Histórico `DatosSERIE` (393.053 filas) | ❌ fuera del exportador; tarea aparte para F4 |
| `Diccionario.xlsx` como alternativa | 🟡 existe en `REM-respaldo-datos`, sin verificar contra el `.mdb` |
