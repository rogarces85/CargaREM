# V3_PLAN.md — plan técnico de la versión 3

> Plan aprobado el 2026-11-26 antes de escribir código. Su ejecución está en
> [`V3_TAREAS.md`](V3_TAREAS.md) y la evidencia en
> [`02-DOCS/feature-mejoras-v3.md`](../02-DOCS/feature-mejoras-v3.md).
>
> **Desvío declarado:** el plan preveía un documento de evidencia por épica
> (`feature-validador-modulo.md`, `feature-revision-asignada.md`, …). Se entregó
> **uno solo** con una sección por mejora: cinco documentos de dos páginas cada
> uno habrían duplicado el mismo checklist y el mismo recuento de tests. La
> trazabilidad por mejora se conserva en la tabla de decisiones (D-041 a D-050).

## Fases

| # | Fase | Depende de | Contenido |
|---|---|---|---|
| 0 | Documento | — | `specs/MVP_V3.md`, decisiones y este plan |
| 1 | RBAC y asignación | 0 | 20 permisos, fixture v3.0, pantalla de asignación, alcance en las consultas, aviso al administrador |
| 2 | Avisos de subida | 1 | Eventos, distintivo, fusión, destinatarios, página de notificaciones |
| 3 | Reapertura y nueva subida | 1 | `reabrir`, `SolicitudReenvioService`, tres alcances |
| 4 | Aperturas de plazo | 1, 3 | Tabla, servicio, pantallas, integración con `EnvioService`, auditoría |
| 5 | Serie P y calendario | 0 | `meses_exigidos`, regla única, generación automática, feriados 2027, comandos |
| 6 | Módulo Envíos | 1 | Filtros, órdenes, multi-año, resumen |
| 7 | Panel e indicadores | 2, 5 | `PanelService`, `charts.js`, bloques por rol, respaldo en texto |
| 8 | Módulo Validador | 0 + JSON | Contrato, motor, pantalla, registro de uso, estadísticas |
| 9 | Cierre documental | todas | Manuales, UAT, entrega, README, `REVIEW.md`, wiki |

Las fases 2, 4 y 8 eran paralelizables cuando estuviera la 1. Se ejecutaron en
orden: con un solo agente, la coordinación cuesta más que el paralelismo.

## Decisiones de diseño que el plan fijó

| Tema | Decisión | Por qué |
|---|---|---|
| Alcance | `App\Dominio\Alcance` como única regla | La regla estaba escrita en cinco lugares y no coincidían |
| Reapertura | Método con guardas, no arista en `PERMITIDAS` | Una arista libre la aceptaría cualquier `transicionar()` |
| Aperturas | Fila por establecimiento o «todos», con vencimiento | El período cerrado sigue cerrado para los demás |
| Serie P | `series.meses_exigidos` y la regla en `MatrizSeries` | Cuatro lectores tienen que decir lo mismo |
| Calendario | Generación perezosa + comando | No depender de cron (Constitución, principio 3) |
| Validador | Módulo aparte, con su propia tabla | Si bloqueara o aprobara, duplicaría V0–V11 con otra severidad |
| Gráficos | ApexCharts con `import()` y respaldo en texto | 992 KB no se pagan en cada visita |
| Envíos | Filtros y orden en servidor | Se van a acumular años; el cliente no escala |

## Riesgos que el plan identificó

| Riesgo | Cómo se mitigó |
|---|---|
| El revisor sin asignación cae a «todo» | Sin fallback: el alcance es la asignación; se avisa |
| La reapertura abre una puerta trasera | Método con guardas + test de que la transición directa falla |
| Aperturas que reabren el período para todos | La ventana es por establecimiento; test de «el otro sigue cerrado» |
| La P se exige en los cuatro lectores | Regla única y test que cubre los cuatro |
| Dependencia de cron | Generación perezosa; el comando es una comodidad |
| El Validador toca el flujo | Test que cuenta las cuatro tablas antes y después |
| El panel rompe el presupuesto | Rango de 12 meses, consultas agregadas y ApexCharts diferido |
