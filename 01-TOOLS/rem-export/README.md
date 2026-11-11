# rem-export — de Access a CSV

Exporta los catálogos del sistema **Cargas REM** desde sus bases Access a CSV, que después el portal importa a MySQL.

## Por qué existe

El servidor de producción es Linux y **no puede abrir un `.mdb`**. Ni PHP ni MySQL tienen driver para Access. La conversión tiene que ocurrir en Windows, una vez, y dejar un artefacto portable.

```text
.mdb  →  [este script, ACE OLEDB]  →  CSV + manifest.json  →  [importador Laravel]  →  MySQL
```

## Uso

```powershell
.\export-mdb.ps1
```

Parámetros:

| Parámetro | Por defecto | Para qué |
|---|---|---|
| `-SourceDir` | `C:\Program Files (x86)\Minsal\Cargas Rem 2016` | Instalación oficial. No cambiar sin registrar una decisión (C-013) |
| `-OutDir` | `C:\xampp\_snapshot_m7` | Destino de los CSV. Fuera del repo por defecto |
| `-ServicioId` | `23` | Servicio de Salud a exportar (23 = Osorno) |
| `-Only` | todas | Limita a algunas tablas: `-Only diccionario,establecimientos` |
| `-KeepTemp` | — | Conserva la copia temporal para inspección |

Si apuntas la salida a `01-TOOLS/rem-export/out/`, queda ignorada por git.

## Qué exporta

| CSV | Origen | Filas (2026-11-11) |
|---|---|---|
| `servicios.csv` | `Global.mdb` → `mpServicio` | 30 |
| `establecimientos.csv` | `mpEstablecimiento` filtrado por servicio | 86 |
| `series.csv` | `version` (pares distintos) | 15 |
| `plantillas.csv` | `version` | 939 |
| `plantilla_hojas.csv` | `Thojas` | 1.129 |
| `diccionario.csv` | `Diccionario` | **185.526** |
| `excepciones.csv` | `Excep` | 5.664 |
| `excepciones_columnas.csv` | `colexcep` | 2.977 |
| `hist_carga.csv` | `Trans.mdb` → `HistCarga` | 4.000 |
| `servicio_configurado.csv` | `Trans.mdb` → `SetServicio` | 1 |

Total: **200.367 filas en 26 segundos**.

## Qué NO exporta

- **Las bases `DatosSERIE<X><AÑO>.mdb`** con los 393.053 registros históricos. Es otro volumen y otro destino (histórico de completitud, fase F4). Se agrega después, con su propia tarea.
- Temporales (`Temporal*.mdb`) y plantillas (`MasterDB`, `MasterC`, `MASTER`).

## Reglas que respeta

1. **Solo lectura.** Copia el `.mdb` a una carpeta temporal, abre la copia con `Mode=Read` y borra la copia al terminar. El original nunca se abre: Access no puede crear su archivo de bloqueo `.ldb` dentro de `Program Files`.
2. **Fidelidad.** No normaliza nada. Lo que está en Access es lo que sale en el CSV.
3. **Trazabilidad.** Cada CSV lleva `sha256`, número de filas y origen en `manifest.json`.

## Lo que el importador debe hacer con esto

Tres cosas que los datos crudos exigen y que el exportador **no** hace a propósito:

1. **`TRIM()` a los textos.** `mpServicio` trae relleno de espacios: `"Osorno"` viene como `"Osorno"` + 44 espacios. Sin `TRIM` el portal compara mal y muestra columnas torcidas.
2. **Parsear el CSV con un lector real.** 923 filas de `diccionario.csv` contienen saltos de línea dentro de un campo entrecomillado: 186.449 líneas físicas para 185.526 registros. Leer línea a línea rompe. Usar `fgetcsv()` o `LOAD DATA` con `ENCLOSED BY '"'`.
3. **No confiar en el `Mes` del diccionario como entero.** Es texto en `Diccionario` y en `Thojas`, y entero en `version`. Convertir explícitamente según la tabla de destino.

## Verificado

2026-11-11, sobre la instalación oficial:

- 200.367 filas exportadas, 0 errores
- Recuentos idénticos a los medidos directamente en Access
- Sin BOM, UTF-8, acentos correctos
- `diccionario.csv` parseado completo: 185.526 filas × 11 columnas
