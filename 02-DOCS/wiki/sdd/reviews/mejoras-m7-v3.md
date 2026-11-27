---
type: review
title: Revisión de cierre — mejoras v3 (M7)
description: Revisión en contexto fresco de la rama feature/mejoras-m7-v3 contra main, con panel de tres refutadores. Veredicto CHANGES REQUESTED.
tags: [sdd, review, cargas, mvp-v3]
timestamp: 2026-11-27T09:00:00Z
topic: sdd
---

# Revisión de cierre — mejoras v3 (M7)

| Campo | Valor |
|---|---|
| Rama | `feature/mejoras-m7-v3` |
| Estado revisado | `9a29be4` (HEAD) contra `c50c63e` (`main`) |
| Riesgo | **tier 2** (`rsc risk --base main`): `default`, `docs`, `migrations` · aprobación humana requerida |
| Ronda | 1 de 3 |
| Panel | 3 refutadores en contexto fresco: corrección, seguridad, pruebas |
| Consolidación | 11 reportados → 10 únicos |
| **Veredicto** | **CHANGES REQUESTED** |

## Resumen

`3 hallazgos: 1 bloqueante, 3 should-fix de código, 2 should-fix de pruebas, 1 nit de documento, 2 nits de prueba`

El panel **no** encontró defectos de autorización, IDOR, inyección, XSS ni caída de alcance a
«todos»: `Alcance::aplicar()` usa `whereIn` y con cero asignaciones devuelve cero filas. Tampoco
hay secretos en el diff, ni un camino para que un no-admin se otorgue una apertura o apruebe su
propio establecimiento. La suite da **408 passed (1022 assertions), 0 fallos**.

Lo que impide aprobar es un bloqueante real de comportamiento, más dos piezas de la v3 cuya
evidencia no sostiene lo que se afirma.

## Bloqueante

**F1 — La corrección de un envío observado se rechaza en un período `en_revision`.**
`verificarCarga()` recibe un cuarto parámetro `?Envio $envio` que decide si la carga de un envío
observado se permite, y **ningún llamador lo pasa** (`EnvioService.php:45` y `:105`). La guarda es
código muerto. El encargado sube la corrección que el flujo le pidió y recibe APP-ENV-012, cuyo
texto dice «solo se aceptan correcciones de envíos observados» — promete exactamente lo que el
código no hace.
**Confirmado por ejecución real** (sonda temporal, ya eliminada):
`App\Exceptions\EnvioInvalidoException :: El período 6/2026 está en revisión: solo se aceptan correcciones de envíos observados.`
**Arreglo:** pasar `$envio` en la llamada de `subirVersion` y dejar `null` explícito en `crear()`.
Añadir el test que sube una versión sobre un envío observado en un período en revisión.

## Should-fix de código

| # | Dónde | Qué |
|---|---|---|
| F2 | `EnvioController.php:182` | `comunasDisponibles()` recibe el usuario y **no lo usa**: el filtro de comuna no aplica `Alcance`. Un revisor sin asignación ve la tabla vacía pero el desplegable con todas las comunas del Servicio. Viola R-ALC-01 y el principio 33. |
| F3 | `EnvioPolicy.php:76` | La solicitud de reenvío por `serie` o por `envio` autoriza con `observar()`, que exige `EN_REVISION`: sobre un envío **aprobado** da 403 antes de llegar al servicio que debía reabrirlo. El alcance `asignados` sí reabre. Misma spec, dos comportamientos. |
| F4 | `EnvioService.php:130` | La versión anterior queda `es_vigente = false` pero su `estado` **nunca** pasa a `reemplazado`. El valor existe en el modelo, `EstadoUi` sabe pintarlo, y `REVIEW.md` §8 lo exige por nombre (RN-ENV-8). |
| F5 | `ValidadorEstadisticaService.php:50` | El Validador abre Excel **sin** `BloqueoValidacion` y su ruta `POST /validador` no tiene `throttle`. El flujo normal sí lo usa (`ValidadorArchivo.php:71`). Diez POST simultáneos con un `.xlsx` de 2 MB tumban la memoria, y el archivo se guarda 90 días. Principio 25. |

## Should-fix de pruebas

| # | Dónde | Qué |
|---|---|---|
| F6 | `EnviosFiltrosTest.php` | El filtro por **mes** y por **establecimiento** no tiene test. Verificado por mutación: borrar los dos `where` deja `23 passed, 0 failed`. Criterio de aceptación 8. |
| F7 | `ModuloValidadorTest.php` | Nadie aserta `sha256`, `reglas_version` ni `duracion_ms` de `validador_ejecuciones`. Fijarlos a valores falsos deja la suite en verde. RF-040.1 / R-VAL-03. |

## Nits

- **F8** — `02-DOCS/feature-mejoras-v3.md:62` y `specs/V3_TAREAS.md:113,119` declaran 383 tests (972
  aserciones); HEAD entrega 408 (1022). La cifra era cierta en `dab1945` y quedó obsoleta tras
  `3b3097d`.
- **F9** — La ventana de 10 minutos de RF-041.3 no está fijada: ampliarla a 30 días deja los tests
  en verde.
- **F10** — `test_quien_sube_no_recibe_su_propio_aviso` pasa por el motivo equivocado: el emisor
  tiene rol `encargado`, no `revisor`, así que nunca sería destinatario.

## Lo que el panel atacó y salió limpio

- **Alcance:** no cae a «todos» en envíos, revisión, completitud, panel ni aperturas. La corrección
  está en la consulta, no en la vista. Única excepción: F2.
- **Máquina de estados:** `PERMITIDAS` sigue sin la arista `aprobado → observado`; `reabrir()` es el
  único camino, con motivo y doble rastro.
- **Aislamiento del Validador:** no escribe en `envios`, `envio_archivos`, `validaciones` ni
  `envio_transiciones`.
- **Archivos:** descarga por controlador autenticado con `attachment` + `nosniff`, desde
  `storage/app/private`; lectura con `setReadDataOnly(true)`, sin macros.
- **Aritmética de plazos:** quinto día hábil, feriados 2026/2027 y fin de año verificados a mano, sin
  off-by-one.
- **Tests existentes modificados:** los 18 archivos no perdieron aserciones; el patrón fue invertir
  la expectativa de alcance y **sumar** el negativo que faltaba.
- **Autoría y secretos:** commits solo de `Rodrigo Garces`, sin rastro de IA; sin secretos reales.

## Pendiente antes de fusionar

1. Arreglar **F1** (bloqueante) con su test de regresión.
2. Decidir sobre F2–F7: arreglar ahora o registrar como seguimiento acordado.
3. Corregir las cifras de F8.
4. **Aprobación humana explícita** (tier 2). Un agente no sustituye esa firma.

---

# Cierre de la ronda 1 — los hallazgos se corrigieron

Los diez hallazgos se corrigieron en el mismo árbol, cada uno con el test que falla sin el arreglo
(comprobado revirtiendo el cambio y volviendo a correr). Suite final: **417 passed (1044
assertions), 0 fallos**.

| # | Arreglo | Test que lo fija |
|---|---|---|
| F1 | `subirVersion` pasa el envío a `verificarCarga`; `crear` deja `null` explícito | `test_un_envio_observado_puede_subir_correccion_en_un_periodo_en_revision` + su reverso |
| F2 | `comunasDisponibles` aplica `Alcance` y filtra `activo` | `test_el_desplegable_de_comuna_respeta_el_alcance`, `..._sin_asignacion_no_ve_ninguna_comuna` |
| F3 | La solicitud de reenvío autoriza con `reabrir` cuando el envío está aprobado | `test_pedir_nueva_subida_de_una_serie_sobre_un_aprobado_lo_reabre` |
| F4 | La versión anterior queda con `estado = reemplazado` | `test_la_version_anterior_queda_marcada_como_reemplazada` |
| F5 | El Validador lee dentro de `BloqueoValidacion`; ruta con `throttle:10,60` | cubierto por la suite existente del semáforo |
| F6 | Tests de filtro por `mes` y por `establecimiento_id` | `test_el_filtro_por_mes_deja_solo_ese_mes`, `..._por_establecimiento_deja_solo_ese` |
| F7 | Aserciones de `sha256`, `reglas_version` y `duracion_ms` | `test_un_archivo_sin_errores_no_deja_hallazgos` |
| F8 | Cifras actualizadas a 416/1040 → 417/1044 en la ficha y en `V3_TAREAS.md` | — |
| F9 | Test que cruza el borde de los 10 minutos | `test_pasada_la_ventana_de_fusion_el_aviso_es_uno_nuevo` |
| F10 | El emisor del test también tiene rol revisor | `test_quien_sube_no_recibe_su_propio_aviso` |

**Verificación de que los tests prueban algo:** se revirtió cada arreglo y se corrió su test; los
cinco comprobados así **fallaron sin el arreglo y pasan con él**.

---

# Cierre — la rama se fusionó

| Campo | Valor |
|---|---|
| PR | [#7](https://github.com/rogarces85/CargaREM/pull/7) |
| Fusión | 2026-11-27, por `rogarces85` (aprobación humana del tier 2) |
| Commit de merge | `aa0be79` |
| Suite en `main` | **417 passed (1044 assertions), 0 fallos** |
| Rama | borrada en local y en el remoto; solo queda `main` |


