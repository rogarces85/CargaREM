---
type: dominio
title: Panel con indicadores
description: Qué cifras ve cada rol en el panel, de dónde salen y cómo se garantiza que el dato siga legible sin JavaScript.
tags: [dominio, panel, indicadores, graficos, dashboard]
timestamp: 2026-11-26T12:00:00Z
topic: dominio
---

# Panel con indicadores

> **Decisión:** D-048. **Requisito:** RF-048.
> **Implementación:** `App\Services\PanelService`, `PanelController`,
> `resources/js/ui/charts.js`.

## 1. Qué muestra cada rol

| Rol | Tarjetas | Gráficos |
|---|---|---|
| Encargado | Envíos del año, aprobados, fuera de plazo, subidas en 12 meses | Envíos por estado; subidas por mes |
| Equipo REM | Pendientes, aprobados, observados, tiempo medio de revisión | Envíos por estado; establecimientos por comuna |
| Administrador | Establecimientos activos, envíos del año, aprobados, fuera de plazo | Envíos por estado; establecimientos por comuna |

Además, el administrador ve avisos de gestión: establecimientos sin revisor,
usuarios sin establecimientos y uso del Validador en el año.

## 2. De dónde salen las cifras

Todas salen de consultas reales que pasan por `App\Dominio\Alcance`:

- el encargado cuenta sus envíos;
- el Equipo REM cuenta los de sus establecimientos asignados;
- el administrador cuenta todo.

**No hay números de relleno.** Si no hay envíos, la tarjeta muestra 0 y el
gráfico no se dibuja: un lienzo vacío es más honesto que una barra inventada.
La v2 ya lo decía en el panel («no se muestran números inventados»); la v3 lo
mantiene y le agrega el dato.

## 3. Presupuesto

| Aspecto | Decisión |
|---|---|
| Consultas | Agregadas y acotadas; ninguna por celda |
| Rango | Últimos 12 meses para las series de tiempo |
| ApexCharts | ~992 KB, **cargado con `import()` solo si la página declara `[data-chart]`** |
| Sin JavaScript | La tabla de respaldo sigue mostrando las cifras |

El chunk de ApexCharts se descarga la primera vez que alguien abre un panel con
gráficos. La campana, el listado de envíos y el resto de las pantallas no lo
pagan.

## 4. Accesibilidad

- Cada gráfico es `role="img"` con un `aria-label` que resume los valores.
- Debajo hay un `<details>` con la misma información en tabla: el color y la
  forma no son la única señal (Constitución, principio 30).
- El tema oscuro se respeta: los colores salen de los tokens CSS y el gráfico se
  vuelve a pintar al cambiar de tema.

## 5. Trazabilidad

| Elemento | Dónde |
|---|---|
| Servicio | `portal/app/Services/PanelService.php` |
| Controlador | `portal/app/Http/Controllers/PanelController.php` |
| Vista | `portal/resources/views/panel.blade.php` |
| Componente de gráfico | `portal/resources/views/components/ui/chart.blade.php` |
| Cliente | `portal/resources/js/ui/charts.js` |
| Tests | `portal/tests/Feature/PanelTest.php` |
