---
type: archive
title: Entrega — mejoras v3 (M7)
description: Informe final de la rama feature/mejoras-m7-v3, entregada por PR #7 y fusionada el 2026-11-27.
tags: [sdd, archive, cargas, mvp-v3]
timestamp: 2026-11-27T12:55:00Z
topic: sdd
---

# Entrega — mejoras v3 (M7)

## Qué se entregó

Diez mejoras de la Unidad de Estadísticas sobre el portal REM, según `specs/MVP_V3.md`:

1. Módulo Validador aislado con motor de 144 reglas y registro de uso (D-047).
2. Avisos de subida al Equipo REM con distintivo y fusión de 10 minutos (D-050).
3. Reparto de establecimientos y alcance por asignación (D-042).
4. Aperturas de fuera de plazo (D-044).
5. Solicitud de nueva subida y reapertura (D-043).
6. Serie P semestral (D-045).
7. Calendario automático verificado (D-046).
8. Filtros y orden del módulo Envíos (D-049).
9. Panel con indicadores (D-048).
10. Nombre visible «Equipo REM» (D-041).

154 archivos, +16.324 / −698 líneas. Constitución enmendada a v6.0.0 (§10). Cuatro migraciones.

## Por qué

La v2 dejaba el portal en «recibir, validar y aprobar». La Unidad pidió operar el ciclo completo.

## Decisión de aterrizaje

**PR #7**, fusionado el 2026-11-27 por `rogarces85` (commit `aa0be79`), tras la revisión de cierre en
contexto fresco. Registro: D-051 en `02-DOCS/wiki/sdd/decisions.md`.

Se eligió PR y no merge local porque `main` está cerrada para el agente por decisión de proyecto
(principio 15, D-039) y el repo entrega así desde la v2. El tier 2 exige firma humana: la dio el
dueño del proyecto.

## Verificación

| Comprobación | Resultado |
|---|---|
| Suite completa en `main` tras fusionar | **417 passed (1044 assertions), 0 fallos** |
| Cobertura de las diez mejoras | Un test por mejora; los seis negativos clave cubiertos |
| Revisión de cierre | Panel de 3 refutadores · 11 reportados → 10 únicos → 10 corregidos |
| Falsos positivos de la revisión | 0% (10 de 10 confirmados y arreglados) |
| Vistas Blade | Las 51 compilan |
| Artefacto de UI | `public/build/` coincide con lo versionado |
| Autoría | Solo la identidad configurada; sin rastro de IA |
| Secretos en el diff | Ninguno |

## Revisión

Acta completa: [registro de revisión](../../reviews/mejoras-m7-v3.md).

Lo importante: el panel encontró un **bloqueante real** —la corrección de un envío observado se
rechazaba en un período en revisión, porque la guarda que la permitía era código muerto— y lo
reproduje ejecutando código, no leyéndolo. También encontró que el filtro de comuna no aplicaba el
alcance, que el reenvío por serie daba 403 sobre un aprobado, y que la versión anterior nunca se
marcaba `reemplazado`.

Ninguna autorización, IDOR, inyección, XSS ni caída de alcance a «todos» en las rutas nuevas.

## Lo que la suite no prueba

- **Que las 144 reglas sean las que la Unidad quiere.** El motor y el contrato están probados; el
  `.json` que entregue la Unidad puede no coincidir en forma. Hoy `config/validador/reglas.json`
  trae una primera versión.
- **Que los feriados de 2027 no cambien.** Se contrastaron con la fuente oficial; una ley nueva exige
  actualizar el seeder. El comando de verificación detecta el desfase una vez cargado.
- **Que el panel rinda con varios años.** Se midió con uno; el rango está acotado a 12 meses.
- **El reparto en producción.** Hoy nadie tiene establecimientos asignados: hay que hacerlo antes de
  que un revisor entre, o no verá nada (por diseño, R-ALC-01).

## Siguiente

1. Cargar el `.json` real del Validador y ajustar el contrato si su forma no coincide.
2. Confirmar nombres y correos de los cuatro integrantes del Equipo REM.
3. Repartir los establecimientos en producción.
4. Ejecutar el guion de UAT con personas reales (`docs/uat/guion-uat.md`).
5. Desplegar: el servidor no tiene Node, así que `portal/public/build/` es el artefacto.
