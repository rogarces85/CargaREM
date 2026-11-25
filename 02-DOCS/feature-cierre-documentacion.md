---
author: Rodrigo Garces
branch: main
status: in-progress
---
# Cierre de la documentación técnica (ValidadorRem + Portal M7)

## Intent

La documentación ya no describe el sistema que existe. Cuatro documentos dicen «AdminLTE 3.2»
cuando el portal carga Tailwind v4; el índice declara «Constitución v4.2.0» cuando el archivo
dice v5.0.0; el README cita «rsc 4.1.2» y «257 tests» cuando hay rsc 4.1.9 y 261 tests. Quien
lea el repositorio hoy no puede saber qué documento manda.

Cerrar la documentación es dejar una sola verdad navegable antes de empezar las mejoras del
equipo: un índice que declara precedencia, documentos vigentes que coinciden con el código y
documentos superados marcados como tales.

## Scope

**Dentro:**

- Corregir las contradicciones entre documentación y código: canon de UI, versión de la
  Constitución, versión de rsc, conteo de tests, rutas de assets en `REVIEW.md`.
- Agregar al índice de la wiki una sección **Qué documento manda**, con la cadena de precedencia.
- Marcar como superado lo que ya no rige pero se conserva por trazabilidad
  (`stack_modulo_envio.md`, `docs/stack/prototipo-laravel-12.md`).
- Cerrar los pendientes **P1–P13** de `informe.md` §5.4: cada uno clasificado como resuelto,
  fuera del alcance del portal o abierto con su motivo.
- Actualizar las cifras de entrega (tests, aserciones) y la fecha.
- Anotar el cambio en `log.md`.

**Fuera:**

- Cambiar código de la aplicación. Ni una línea de `portal/app`.
- Cambiar reglas de negocio, severidades ni el pipeline V0–V11.
- Reescribir `backup_documentacion/` (documentación de origen, no se toca).
- Reabrir decisiones cerradas (C-015, C-019, D-029, D-030).
- Mejoras del equipo para el M7: son el paso siguiente, no este documento.

## Checklist

| # | Tarea | Cómo se prueba que está hecha |
|---|---|---|
| 1 | `README.md` alineado al canon vigente | Búsqueda de `AdminLTE 3.2`, `v3.1.0`, `rsc 4.1.2` y `257 tests` en el archivo: 0 coincidencias; el stack dice Tailwind v4 y la Constitución v5.0.0 |
| 2 | `02-DOCS/wiki/index.md` alineado y con precedencia declarada | La fila de la Constitución dice v5.0.0 y 31 principios; existe la sección «Qué documento manda» con la cadena completa |
| 3 | `docs/entrega/estado-del-proyecto.md` con cifras reales | Dice 261 tests y 649 aserciones, medido con `php artisan test` el 2026-11-24 |
| 4 | `docs/reestructuracion/matriz-funcionalidades.md` con cifras reales | El pendiente de barrido queda marcado con la evidencia de este documento |
| 5 | `docs/validacion/politica-severidad.md` sin citar la Constitución v3.1.0 | 0 coincidencias de `v3.1.0`; cita v5.0.0 |
| 6 | `REVIEW.md` con las rutas reales | Las rutas ignoradas corresponden a `portal/public/assets/` de hoy y a `public/build/` |
| 7 | `02-DOCS/wiki/stack/restricciones-servidor.md` con puntero vigente | Ya no afirma que D-STACK está reabierta; apunta a la Constitución §0 |
| 8 | Superados marcados | `stack_modulo_envio.md` y `docs/stack/prototipo-laravel-12.md` abren con su estado y su reemplazo |
| 9 | `informe.md` §5.4 cerrado | Los 13 pendientes P1–P13 tienen veredicto: resuelto, fuera de alcance o abierto con motivo |
| 10 | Barrido sin residuos | Búsqueda de referencias al canon viejo en documentos vigentes: solo aparecen en documentos históricos |
| 11 | `log.md` anotado | Existe la fila con fecha y rutas |
| 12 | Documentación del manual de operación al día con la UI | `manual-operacion.md` explica que `public/build/` se compila en local y se sube; el despliegue por FTP lo incluye |

**Estado del checklist:** los 12 puntos están hechos y verificados en la tabla de evidencia. El
trabajo descubrió además tres contradicciones que no estaban planificadas (§ Hallazgo no previsto).

## Evidence

| # | Evidencia observada (2026-11-24) |
|---|---|
| 1 | `README.md`: 0 coincidencias de `AdminLTE 3.2`, `v3.1.0`, `rsc 4.1.2` y `257 tests` como afirmación vigente. Ahora dice Tailwind v4 (C-019), Constitución v5.0.0 con 31 principios, rsc 4.1.9 y 261 tests / 649 aserciones. Las dos menciones que quedan de «AdminLTE» son explicaciones de **por qué se retiró**, no descripciones del estado |
| 2 | `02-DOCS/wiki/index.md`: sección «Qué documento manda» con 6 niveles de precedencia y la lista de 5 documentos superados. La fila de la Constitución dice v5.0.0 y 31 principios; la de `stack/` dice «cerrada (C-015)» y ya no «reabierta (C-014)» |
| 3 | `php artisan test --compact` → **Tests: 261 passed (649 assertions)**, 38,30 s. Actualizado en `docs/entrega/estado-del-proyecto.md`, que además pasa a fecha 24-11 y gana la sección de publicación de la UI |
| 4 | `docs/reestructuracion/matriz-funcionalidades.md`: «Barrido de referencias activas» pasa de `[ ]` a `[x]` con el enlace a este documento; la cifra de tests pasa a 261 |
| 5 | `grep v3.1.0` en `docs/`: 0 coincidencias. `politica-severidad.md` cita la Constitución v5.0.0 en el frontmatter y en el Principio 6 |
| 6 | `REVIEW.md`: la ruta ignorada `public/assets/vendor/` (que ya no existe) se reemplaza por `portal/public/build/`; se agregan los fallos graves 12 (clase Tailwind interpolada) y 13 (UI sin recompilar), ambos de C-019 |
| 7 | `stack/restricciones-servidor.md`: abre con «Evidencia previa a C-015. No es el canon» y apunta a la Constitución §0. La tabla «sin confirmar» marca como resueltas las dos filas de PHP; «Cómo confirmar si hay PHP-FPM» pasa a «Cómo se confirmó» |
| 8 | `stack_modulo_envio.md` y `docs/stack/prototipo-laravel-12.md` abren con su estado. El segundo marca los dos puntos que C-019 dejó obsoletos (`vite.config.js` y los assets de AdminLTE) sin reescribir el registro |
| 9 | `informe.md` §5.4: los 13 puntos tienen veredicto. **3 cerrados** (P11, P13, P5 parcial), **5 fuera del alcance del portal** (P1, P2, P7, P9, P10 — verificado: 0 referencias a `SoapClient` o `minsal.cl` en `portal/app`), **5 abiertos con motivo** (P3, P4, P6, P8, P12). §4.2 marcada como propuesta superada (React vs. Laravel real) |
| 10 | Barrido final sobre `docs/`, `02-DOCS/`, `specs/` y raíz: las únicas coincidencias del canon viejo están en `specs/_historico/`, `log.md`, `decisions.md`, `decision-stack.md`, `opciones-stack.md` y las FTD fechadas. Todas son registros históricos. Enlaces relativos: 0 rotos en documentos vigentes (quedan 2 en `specs/_historico/TASK.md`, que no se usa) |
| 11 | `02-DOCS/wiki/log.md`: fila del 2026-11-24 con las rutas tocadas |

### Hallazgo no previsto al planificar

Al comparar «matriz de series» con el código aparecieron **tres contradicciones reales** que el
checklist no contemplaba. No se corrigió el código (fuera de alcance): quedan documentadas con
archivo y línea en [`docs/validacion/inconsistencias-documentacion-codigo.md`](../docs/validacion/inconsistencias-documentacion-codigo.md)
y avisadas en la cabecera de [`matriz-series-obligatorias.md`](../docs/validacion/matriz-series-obligatorias.md).

| # | Contradicción | Impacto |
|---|---|---|
| A | La matriz de series **no** se deriva del histórico: `ImportadorCatalogo` no escribe `establecimiento_serie`. Los 86 establecimientos del catálogo real entrarían sin matriz y sin serie exigida | Alto, se dispara al importar el catálogo completo |
| B | La ventana `vigente_desde`/`vigente_hasta` se filtra en **2 de 4** consultas. La aprobación (`EstadoEnvioService`) exige series vencidas que V11 ya no exige: bloqueo sin salida | Alto, se dispara al usar `vigente_hasta` |
| C | `grep vigente_hasta` en `portal/tests/` → **0 coincidencias**. R-SER-03 y R-SER-04 no tienen test | Medio: es lo que permitió que A y B pasaran desapercibidos |

## Next

Decidir qué se ataca primero: las mejoras del M7 que trae el equipo, o las tres contradicciones
(A, B, C) antes de tocar la matriz de series. B y C admiten corrección en una sola rama.
