---
author: Rodrigo Garces
branch: feature/mejoras-m7-v3
status: shipped
merged: 2026-11-27 (PR #7, merge aa0be79)
---
# Mejoras de la v3: diez cambios de la Unidad de Estadísticas

> **Entregada.** Fusionada a `main` por PR #7 (`aa0be79`, 2026-11-27), tras la revisión de cierre
> en contexto fresco documentada en `02-DOCS/wiki/sdd/reviews/mejoras-m7-v3.md`. Suite en `main`:
> 417 tests, 1044 aserciones, 0 fallos.

## Intent

La v2 dejaba el portal en «recibir, validar y aprobar». La Unidad pidió operar el
ciclo completo: repartir la revisión entre cuatro personas, autorizar cargas
fuera de plazo con motivo, corregir después de aprobar, informar la serie P dos
veces al año, generar el calendario solo, mirar el estado en un panel y tener una
herramienta de calidad que no toque el flujo.

Diez mejoras, una rama, una especificación nueva: `specs/MVP_V3.md`.

## Scope

**Dentro** (cada punto con su decisión):

| # | Mejora | Decisión |
|---|---|---|
| 1 | Módulo Validador, aislado, con registro de uso | D-047 |
| 2 | Aviso de subida al Equipo REM, con distintivo y fusión | D-050 |
| 3 | Reparto de establecimientos y alcance por asignación | D-042 |
| 4 | Aperturas de fuera de plazo | D-044 |
| 5 | Solicitud de nueva subida y reapertura | D-043 |
| 6 | Serie P semestral | D-045 |
| 7 | Calendario automático verificado | D-046 |
| 8 | Filtros y orden del módulo Envíos | D-049 |
| 9 | Panel con indicadores | D-048 |
| 10 | Nombre visible «Equipo REM» | D-041 |

**Fuera:** correo de notificación, 2FA, SAX, importación histórica, exportación
del panel y consolidación.

## Checklist

| # | Tarea | Cómo se prueba que está hecha |
|---|---|---|
| 1 | Los 20 permisos están en el seeder y en el fixture | `AuthHardeningTest` recorre la matriz completa |
| 2 | El revisor ve solo lo asignado; sin asignación, nada | `AsignacionRevisoresTest`: 12 tests |
| 3 | Un aviso por subida y envío, con distintivo | `NotificacionSubidaTest`: 7 tests |
| 4 | Reabrir exige permiso, motivo y estado aprobado | `ReaperturaTest`: 13 tests |
| 5 | La apertura habilita solo a su establecimiento | `AperturaFueraPlazoTest`: 12 tests |
| 6 | La P no se exige fuera de 06 y 12 | `SeriePSemestralTest`: 7 tests |
| 7 | El calendario coincide con los feriados chilenos | `CalendarioAutomaticoTest`: 11 tests |
| 8 | El módulo Envíos filtra y ordena | `EnviosFiltrosTest`: 13 tests |
| 9 | El panel muestra las cifras de cada rol | `PanelTest`: 8 tests |
| 10 | El Validador no toca el flujo | `ModuloValidadorTest`: 12 tests |
| 11 | La suite entera sigue verde | `php artisan test` |
| 12 | El artefacto de UI está recompilado | `npm run build` |

**Estado del checklist:** las doce tareas hechas. Los puntos 1 a 10 se probaron
con tests nuevos; el 11 y el 12, con la corrida final.

## Evidence

| # | Evidencia observada (2026-11-26) |
|---|---|
| 1 | `php artisan test --compact` → **416 passed (1040 assertions)**, 0 fallos. Antes de la rama: 285 (701). **+131 tests** |
| 2 | 4 migraciones nuevas: índices de alcance y panel, `series.meses_exigidos`, `aperturas` + `envios.apertura_id`, `validador_ejecuciones`. Aplicadas en `cargas_rem_test` |
| 3 | `npm run build` → `app.js` 31,8 KB y `apexcharts.esm` 992 KB en **chunk aparte**, cargado solo con `import()` cuando hay `[data-chart]` |
| 4 | `php artisan rem:calendario 2026 --verificar` y `rem:periodos:generar` disponibles; 11 comandos `rem:*` en total (antes 8) |
| 5 | Feriados 2026 (16) y 2027 (17, con el 17-09 de la Ley 20.983) cargados desde el seeder y contrastados con [feriados.cl](https://www.feriados.cl/) |
| 6 | 51 vistas (antes 43) y 18 componentes. `VistasCompilanTest` compila todas |

### Lo que la suite no puede probar

- **Que el JSON del Validador sea el que la Unidad quiere.** El motor y el
  contrato están probados; las reglas concretas llegan con el archivo.
- **Que las fechas de los feriados de 2027 no cambien.** Se contrastaron con la
  fuente oficial; una ley nueva obliga a actualizar el seeder, y el comando de
  verificación lo detecta como desfase una vez que el feriado esté cargado.
- **Que el panel rinda con varios años.** Se midió con un año; el rango está
  acotado a 12 meses.

## Revisión

`rsc risk` no se ejecutó en esta rama: la entrega se hizo con el plan aprobado
por el usuario y la suite como evidencia. Queda pendiente una revisión en
contexto fresco antes de fusionar a `main`.

### Puntos que un revisor debería mirar primero

| Prioridad | Qué | Dónde |
|---|---|---|
| Alta | El alcance del revisor no cae a «todos» en ningún camino | `App\Dominio\Alcance`, `Envio::scopeForUser` |
| Alta | La reapertura no se agregó a `PERMITIDAS` | `EstadoEnvioService::reabrir` |
| Alta | El Validador no escribe en el flujo | `ValidadorEstadisticaService`, su test |
| Media | Una apertura vencida no habilita nada | `Apertura::estaVigente` |
| Media | La P no se exige fuera de su mes en los cuatro lectores | `MatrizSeries`, `SeriePSemestralTest` |

## Next

1. Cargar el `.json` real del Validador en `config/validador/reglas.json` y ajustar
   el contrato si su forma no coincide.
2. Confirmar los nombres y correos de los cuatro integrantes del Equipo REM.
3. Repartir los establecimientos en producción (hoy nadie tiene asignación).
4. Ejecutar el guion de UAT con personas reales.
5. Revisión en contexto fresco y PR a `main`.
