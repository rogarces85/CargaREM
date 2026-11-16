---
author: Rodrigo Garces
branch: feature/rediseno-ui-clinova
status: in-progress
---
# Guías de uso por rol + mapa de módulos

## Intent

El portal está terminado como software y todavía no se ha mostrado a nadie. Antes de
mostrarlo hacen falta tres cosas que el manual oficial no da:

1. **Una guía por rol.** El manual existente describe el sistema; nadie lo lee antes de
   trabajar. Cada persona necesita el camino exacto de su mes, no el catálogo de pantallas.
2. **El mapa de cómo se conectan los módulos.** Hay dudas reales sobre qué alimenta a qué.
   Sin ese mapa no se puede explicar el sistema en una reunión ni decidir qué sobra.
3. **La evidencia de dónde el flujo se traba.** Al recorrer el mes 10 con cada rol
   aparecieron bloqueos que hoy impiden demostrar el sistema completo.

Vale la pena porque el resultado sirve dos veces: para la demostración y para decidir qué
módulos simplificar después.

## Scope

**Dentro:**

- `docs/manual/00-como-funciona-el-sistema.md` — mapa de módulos, ciclo de vida del envío,
  alcances por rol y el mes 10 recorrido en el tiempo con los tres roles en paralelo.
- `docs/manual/guia-encargado-establecimiento.md` — guía de uso del rol encargado.
- `docs/manual/guia-revisor-servicio-salud.md` — guía de uso del rol revisor.
- `docs/manual/guia-administrador.md` — guía de uso del rol admin.
- `docs/manual/analisis-simplificacion.md` — hallazgos con evidencia y propuestas.
- Corrección de los dos defectos que impiden demostrar el flujo (ver Checklist).
- Red de seguridad: una serie desactivada deja de exigirse, para que el interruptor del
  catálogo sea reversible y no deje envíos bloqueados.
- Icono de estadísticas (`chart-column`) y gráfico de avance por serie en Completitud.

**Fuera:**

- Cambiar reglas de negocio, severidades o el pipeline de validación.
- Retirar la serie F del catálogo: se conserva como pieza de demostración.
- Fusionar o retirar módulos. El análisis propone; la decisión no es del agente.
- Capturas de pantalla. Las guías se escriben antes de congelar la interfaz.
- Reemplazar `docs/manual/manual-usuario.md`: las guías lo complementan, no lo sustituyen.

## Checklist

| # | Tarea | Cómo se prueba que está hecha |
|---|---|---|
| 1 | Mapa de módulos con el ciclo de vida del envío | El documento nombra las 6 entidades, los 6 estados y quién mueve cada transición; cada afirmación se puede rastrear a `EstadoEnvioService::PERMITIDAS` |
| 2 | Guía del encargado | Recorre alta → subida → validación → envío a revisión con los textos reales de la interfaz |
| 3 | Guía del revisor | Recorre bandeja → observar → aprobar, y enumera las 4 guardas de aprobación |
| 4 | Guía del administrador | Recorre catálogos, matriz, calendario y usuarios; explica el cálculo del plazo |
| 5 | Análisis de simplificación | Cada hallazgo trae archivo:línea y qué se rompe hoy |
| 6 | Corregir la URL absoluta de `revision/index` | Las acciones Observar/Aprobar apuntan a la ruta con prefijo del subdirectorio; probado en navegador |
| 7 | Corregir el botón «Enviar a revisión» en estado `con_errores` | El botón deja de ofrecerse cuando la máquina de estados no admite la transición |
| 8 | Suite verde y build | `php vendor/bin/phpunit` OK y `npm run build` sin errores |
| 9 | Red de seguridad: una serie desactivada no se exige | V11 no la reclama y la aprobación no la cuenta como faltante; dos tests lo prueban |
| 10 | Icono de estadísticas y gráfico de avance por serie | La pantalla de Completitud muestra una barra por serie con sus cifras |

## Evidence

| # | Evidencia observada |
|---|---|
| 1-5 | Escritos y commiteados. Las cifras de catálogo (7 establecimientos, series A/BS/BM/D/P/F activas, período 10/2026 con límite 06-11-2026 vencido) salen de consultar la base de desarrollo, no de suponer. |
| 6 | Con el envío #1 en `en_revision`, la bandeja renderiza `data-url="http://localhost/Cargas/portal/public/revision/1/observar"` y, al abrir el diálogo, `formObservar.action` es esa misma dirección. Estado del envío restaurado a `con_errores` después de la prueba. |
| 7 | `EnvioPolicy::enviar` quedó con la misma lista de estados que `EstadoEnvioService::PERMITIDAS` (sin `con_errores`) y exige al menos un archivo vigente. Dos tests nuevos en `EnvioPolicyTest`. |
| 8 | `php vendor/bin/phpunit` → **OK (261 tests, 649 assertions)**. `npm run build` → limpio. |
| 9 | Filtro `series.activo` aplicado en `ReglaV11Completitud`, `EstadoEnvioService::verificarGuardas`, `CompletitudService` y `EnvioController::show`. Tests `test_una_serie_desactivada_no_se_exige` y `test_una_serie_reactivada_vuelve_a_exigirse` en verde. La matriz quedó con F activa en los 7 establecimientos tras `db:seed --class=L1MinimumSeeder`. |
| 10 | En `/completitud?anio=2026&mes=10` la página muestra 6 barras (A, BS, BM, D, P, F) con `0 / 7 · 0 %`, y la matriz confirma 6 × 7 = 42 series exigidas. Icono `chart-column` visible en la barra lateral y en la tarjeta del panel. Cero errores de consola. |

> Nota: en una corrida intermedia falló `RendimientoTest::test_la_bandeja_responde_rapido_con_cien_envios`
> con 2,03 s contra un umbral de 2,0 s. Se repitió tres veces aislado y pasó las tres; la
> suite completa volvió a pasar. Es un umbral ajustado bajo carga de la máquina, no una
> regresión de este cambio.

## Next

Commit, push y merge a `main`.
