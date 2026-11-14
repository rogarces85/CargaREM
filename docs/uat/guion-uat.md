# Guion de aceptación (UAT)

Para validar el portal con personas reales antes de publicarlo. Cada rol
ejecuta su recorrido y registra si pasa o no.

**Preparación:** un período abierto, el catálogo sembrado y los usuarios de
prueba creados. Duración estimada: 2 horas por rol.

> **Antecedente:** el recorrido técnico del 14-11-2026 está en
> [`resultado-2026-11-14.md`](resultado-2026-11-14.md). Cubrió el flujo y las
> reglas por HTTP; falta la sesión con personas (visual, accesibilidad y
> tiempos percibidos).

---

## Rol: encargado

| # | Escenario | Criterio de aceptación |
|---|---|---|
| E1 | Entrar con su cuenta y ver el tablero | Ve **solo** sus establecimientos |
| E2 | Crear el envío del mes | Aparece en estado «recibido» y no deja crear otro igual |
| E3 | Subir los Excel de las series exigidas | Cada uno se valida al subir; el contador de series subidas coincide con la matriz |
| E4 | Ver el reporte de un archivo con errores | Los hallazgos están agrupados por código y dicen qué corregir |
| E5 | Descargar el reporte en Excel | El archivo abre con la hoja «Resumen» y la hoja «Hallazgos» |
| E6 | Intentar subir un archivo de otro establecimiento | Se rechaza con un mensaje que explica por qué |
| E7 | Corregir y reenviar una serie observada | La observación queda resuelta sola y la versión anterior sigue en el historial |
| E8 | Enviar a revisión | El estado cambia y no puede seguir editando |

**Falla si:** ve establecimientos ajenos, puede subir sin validación, pierde una
versión anterior, o el reporte no dice qué corregir.

---

## Rol: revisor

| # | Escenario | Criterio de aceptación |
|---|---|---|
| R1 | Abrir la bandeja | Ve los envíos de su Servicio de Salud, los más antiguos primero |
| R2 | Filtrar por antigüedad | Solo aparecen los que llevan más días esperando |
| R3 | Observar un envío apuntando a una serie | La observación queda asociada a esa serie y el envío vuelve al establecimiento |
| R4 | Intentar aprobar con observaciones abiertas | Se rechaza con un mensaje claro |
| R5 | Aprobar un envío completo | Cambia a «aprobado» y el encargado ve el aviso en la campana |
| R6 | Abrir el aviso de la campana | Lleva al envío y queda marcado como leído |
| R7 | Ver la matriz de completitud | El semáforo coincide con los envíos que conoce |
| R8 | Confirmar que la matriz no exporta ni navega por celda | No hay botones de CSV/Excel y la celda no es un enlace |

**Falla si:** ve envíos de otro servicio, aprueba algo incompleto, o la matriz
no coincide con la realidad.

---

## Rol: administrador

| # | Escenario | Criterio de aceptación |
|---|---|---|
| A1 | Sembrar el catálogo desde `REM_CATALOGO_DIR` | Los conteos cuadran y la validación deja de pedir plantilla |
| A2 | Editar un establecimiento y asignarle Servicio | El cambio se refleja en la matriz |
| A3 | Asignar series a un establecimiento | Las columnas de la matriz se ajustan |
| A4 | Crear un usuario con un rol | Puede entrar y ve lo que su rol permite |
| A5 | Intentar desactivarse a sí mismo | Se rechaza |
| A6 | Editar una plantilla y sus hojas | La validación usa la versión nueva |
| A7 | Confirmar que un envío aprobado no se reabre ni se anula | No hay botón ni ruta; el estado final es «aprobado» |
| A8 | Revisar la bitácora en base de datos | Cada acción anterior aparece con su usuario y su fecha |

**Falla si:** puede dejar el sistema sin administrador, la bitácora tiene
huecos, o el catálogo queda a medias tras una carga fallida.

---

## Registro de incidencias

| Fecha | Rol | Escenario | Qué pasó | Severidad | Estado |
|---|---|---|---|---|---|
| | | | | | |

Severidad: **bloqueante** (impide la tarea), **molesta** (hay que rodearla) o
**cosmética** (no impide nada).
