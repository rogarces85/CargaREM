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

**Fuera:**

- Cambiar reglas de negocio, severidades o el pipeline de validación.
- Decidir si la serie F se exige o no: es dato del catálogo y lo decide la Unidad de
  Estadísticas con el usuario.
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

## Evidence

| # | Evidencia observada |
|---|---|
| 1-5 | Escritos y commiteados. Las cifras de catálogo (7 establecimientos, series A/BS/BM/D/P activas, F inactiva exigida en 4, período 10/2026 con límite 06-11-2026 vencido) salen de consultar la base de desarrollo, no de suponer. |
| 6 | Con el envío #1 en `en_revision`, la bandeja renderiza `data-url="http://localhost/Cargas/portal/public/revision/1/observar"` y, al abrir el diálogo, `formObservar.action` es esa misma dirección. Antes el JavaScript armaba `'/revision/1/observar'`, que apunta a `http://localhost/revision/1/observar` y da 404 en el subdirectorio. Estado del envío restaurado a `con_errores` después de la prueba. |
| 7 | `EnvioPolicy::enviar` quedó con la misma lista de estados que `EstadoEnvioService::PERMITIDAS` (sin `con_errores`) y exige al menos un archivo vigente. Dos tests nuevos en `EnvioPolicyTest`: `test_no_se_envia_un_envio_con_errores` y `test_no_se_envia_un_envio_sin_archivos`. |
| 8 | `php vendor/bin/phpunit` → **OK (259 tests, 647 assertions)**. `npm run build` → limpio. |

> Nota: en una corrida intermedia falló `RendimientoTest::test_la_bandeja_responde_rapido_con_cien_envios`
> con 2,03 s contra un umbral de 2,0 s. Se repitió tres veces aislado y pasó las tres; la
> suite completa volvió a pasar. Es un umbral ajustado bajo carga de la máquina, no una
> regresión de este cambio.

## Next

Decidir la serie F (opción A, B o C del análisis) antes de la demostración. Es lo único que
impide que 4 de los 7 establecimientos recorran el flujo completo.
