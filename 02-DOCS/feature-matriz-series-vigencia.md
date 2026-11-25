---
author: Rodrigo Garces
branch: feature/matriz-series-vigencia
status: in-progress
---
# Matriz de series: una sola regla, con vigencia real

## Intent

La matriz `establecimiento_serie` decide qué series se le exigen a cada establecimiento. Hoy esa
regla está escrita **cuatro veces** y las cuatro versiones no coinciden:

- Dos filtran la ventana de vigencia (V11 y la matriz de completitud) y dos **no** (la aprobación y
  la pantalla del envío). Un envío que V11 da por completo no se puede aprobar.
- Las dos que filtran lo hacen contra `now()`, no contra el cierre del período que se informa, que es
  lo que R-SER-03 manda. Un mes cerrado se reinterpreta con la vigencia de hoy.
- Ninguna tiene test de la ventana: `grep vigente_hasta` en `portal/tests/` da **0**.

Además, la matriz no se crea al importar el catálogo. Los **86 establecimientos** del Servicio Osorno
se importarían sin ninguna fila, y por R-SER-05 («sin fila, no obliga») no se les exigiría nada.
Decisión del usuario: **al importar, exigirles todas las series activas**; después el administrador
edita cuál sí y cuál no.

## Scope

**Dentro:**

- Un único punto de verdad para la regla: `App\Dominio\MatrizSeries`. Las cuatro consultas lo usan.
- La ventana se evalúa contra el **último día del período informado** y, si no se informa, contra hoy.
- La aprobación y la pantalla del envío pasan a filtrar la ventana (dejan de exigir series vencidas).
- Al importar el catálogo, cada establecimiento recibe las series activas (`obligatoria = 1`,
  `vigente_desde = null`). El administrador las puede quitar después desde la matriz.
- Tests de la ventana: dentro, fuera por `vigente_hasta`, fuera por `vigente_desde`, y que la
  aprobación no exija una serie vencida.
- La importación **no pisa** una matriz ya editada: solo crea filas para los pares que no existen.

**Fuera:**

- Derivar la matriz del histórico de 12 meses (R-SER-01). Es la contradicción **A**, la más grande:
  exige datos históricos que el portal no tiene. Se queda documentada y sin implementar.
- Cambiar la regla de negocio de qué series existen. SAX sigue fuera (D-006).
- Reabrir decisiones cerradas.

## Checklist

| # | Tarea | Cómo se prueba que está hecha |
|---|---|---|
| 1 | `App\Dominio\MatrizSeries` con `exigidas(Establecimiento, ?CarbonInterface $periodo)` | Test unitario: con dos series, una vigente y una vencida, devuelve solo la vigente |
| 2 | La ventana se evalúa contra el fin del período, no contra hoy | Test: serie con `vigente_hasta` pasado pero período anterior a esa fecha → **sí** se exige |
| 3 | `ReglaV11Completitud` usa el dominio | Los 261 tests siguen verdes; el test de ventana pasa |
| 4 | `CompletitudService` usa el dominio | La matriz de completitud muestra la misma verdad |
| 5 | `EstadoEnvioService::verificarGuardas` usa el dominio | Test nuevo: serie vencida **no** bloquea la aprobación; serie vigente **sí** |
| 6 | `EnvioController::show` usa el dominio | La pantalla del envío lista exactamente las exigidas |
| 7 | `ImportadorCatalogo` crea la matriz de las series activas al importar | Test: importar un CSV de 3 establecimientos → 3 × series activas filas |
| 8 | La importación no pisa una matriz ya editada | Test: fila existente con `obligatoria = 0` sigue en 0 tras reimportar |
| 9 | Suite completa en verde | `php artisan test` sin fallos, con los tests nuevos contados |
| 10 | Verificación en la base real con el catálogo de 86 | Consulta SQL: 86 establecimientos × 6 series activas |
| 11 | La siembra no borra el trabajo del administrador | Un `db:seed` no cambia nombres del catálogo ni la matriz editada |
| 12 | Desmarcar una serie deja `obligatoria = 0` y sobrevive a la reimportación | Comprobado en la app real y en la base: F quedó en 0 y siguió en 0 |

**Estado del checklist:** los 12 puntos hechos y verificados en la tabla de evidencia. La verificación en
la base real destapó un cuarto fallo no planificado (el seeder de L1), y la revisión destapó un quinto
(el escape hatch `obligatoria = 0` no existía). Ambos corregidos en la misma rama.

## Evidence

| # | Evidencia observada (2026-11-25) |
|---|---|
| 1 | `App\Dominio\MatrizSeries` creado. `tests/Feature/MatrizSeriesTest.php` → **10 passed (13 assertions)**: sin fila, `obligatoria = 0`, serie inactiva, orden del catálogo, `vigente_hasta`, `vigente_desde`, sin fechas, y el ancla contra el cierre del período |
| 2 | `test_el_ancla_de_la_vigencia_es_el_cierre_del_periodo_evaluado`: con `travelTo('2026-11-25')`, una serie con `vigente_hasta = 2026-06-30` **no** se exige en 11/2026 y **sí** en 6/2026. Es R-SER-04 probado |
| 3 | `ReglaV11Completitud` recibe `MatrizSeries` por constructor. `tests/Feature/ValidadorV11Test.php` → **11 passed**, incluidos los 3 nuevos de ventana |
| 4 | `CompletitudService` carga las series con `aplicarVigencia()` en el eager loading (una sola consulta por matriz, no una por establecimiento). `CompletitudTest` → **12 passed**, 2 nuevos |
| 5 | `EstadoEnvioService::verificarGuardas` usa `idsExigidos()` + `cierreDelPeriodo`. `AprobacionGuardasTest` → **10 passed**, 2 nuevos. **Verificado en contra:** con `EstadoEnvioService.php` revertido, `test_una_serie_con_vigencia_terminada_no_bloquea_la_aprobacion` falla con `APP-ENV-011`. No es un test vacío |
| 6 | `EnvioController::show` usa `exigidas()` con el cierre del período. `EnvioWebTest` → **15 passed**, 2 nuevos que comprueban la colección `$exigidas` de la vista. El desplegable de subida sigue listando todas las series activas, a propósito |
| 7 | `ImportadorCatalogo::crearMatrizInicial()`. `CatalogoSeederTest` ampliado a **10 passed**: importar crea 2 establecimientos × 2 series activas = 4 filas, `vigente_desde` en `null` |
| 8 | `test_reimportar_no_pisa_una_matriz_ya_editada`: se quita BS de un establecimiento, se reimporta → `resumen['matriz'] = 0` y BS sigue fuera. `test_reimportar_no_duplica_la_matriz`: segunda corrida crea 0 filas |
| 9 | `php artisan test --compact` → **Tests: 286 passed (695 assertions)**, 49,24 s. Antes del cambio: 261 (649). **+25 tests** |
| 10 | Base real con el catálogo de `C:\xampp\_snapshot_m7`: `CatalogoSeeder` → «86 establecimientos… Matriz de series: 480 exigencias nuevas». SQL: **87 establecimientos** (86 del catálogo + 1 de pruebas no presente en él), **522 filas de matriz**, **0 establecimientos sin matriz**, 6 series activas. Reimportación: **0 exigencias nuevas** |

### Verificación en la aplicación real

Con el servidor levantado y sesión de revisor:

| Comprobación | Resultado |
|---|---|
| `/health` | 200, `db: ok`, `storage: ok` |
| `/completitud?anio=2026&mes=10` con 87 establecimientos | 200, 502 KB, 89 filas de tabla |
| Tiempo de esa pantalla | **0,74 s** (presupuesto RNF-005: < 2 s) |

### Hallazgo no previsto: el seeder de L1 borraba trabajo

Al verificar en la base real se descubrió que `L1MinimumSeeder` usaba `updateOrCreate` sobre seis
códigos que **existen en el catálogo real** (`123100`…`123105`). Cada `db:seed` —y `iniciar.bat` lo
corre siempre— revertía el nombre del catálogo a la etiqueta corta de L1 y **borraba la matriz editada**
con `detach()` + `attach()`.

Corregido en la misma rama: `firstOrCreate` para el establecimiento y la matriz solo se llena si no hay
filas. Dos tests nuevos en `L1SeederTest` (→ 8 passed). Sin esto, el arreglo de la importación habría
durado hasta el siguiente arranque.

## Revisión

`rsc risk` → **1 revisor, sin aprobación humana obligatoria**. Revisión en contexto fresco, con
verificación por mutación. Veredicto: **0 bloqueantes, 2 importantes, 3 nits**. Los cinco se corrigieron.

### Importante 1 — el escape hatch `obligatoria = 0` no existía

`MatrizController::update` hacía `continue` cuando la casilla venía desmarcada, así que **ninguna ruta
del sistema escribía `obligatoria = false`**: R-SER-06 era inimplementable y desmarcar todas las series
dejaba la matriz vacía, que la siguiente importación rellenaba.

Corregido: la fila se guarda con `obligatoria = false`. Verificado en la aplicación real: se desmarcó F
del establecimiento 1, quedó en `0` en la base, y **siguió en `0` tras reimportar el catálogo**.

### Importante 2 — tres tests no discriminaban

`test_una_serie_con_vigencia_terminada_no_se_reclama` (V11), el equivalente en `CompletitudTest` y
`test_la_tabla_de_series_no_muestra_una_serie_con_vigencia_terminada` **pasaban con el código viejo**:
usaban fechas que «hoy» (25-11) caen del mismo lado que con el arreglo, así que no vigilaban la
regresión.

Corregido: los tres fijan «hoy» el **15-08-2026**, un día en que la serie todavía no venció pero el
período del envío (9/2026) ya cerró después. Es el único par de fechas que separa `now()` de
`cierreDelPeriodo()`. Los tres se verificaron en contra: **pasan con el arreglo y fallan sin él**.

### Nits

| # | Qué | Corregido |
|---|---|---|
| N1 | `app()` como service locator en cuatro sitios, con dos estilos distintos | Inyección por constructor en `CompletitudService`, `EstadoEnvioService` y `EnvioController` |
| N2 | `test_no_aprueba_con_una_serie_exigida_y_bloqueada` duplicaba `test_no_aprueba_con_archivos_bloqueados` | Eliminado |
| N3 | `vigente_desde` con dos valores por defecto: `2026-01-01` en L1, `null` en el importador | `null` en los dos |

## Next

Commit, merge a `main` y, después, la lista de mejoras del equipo para el M7.
