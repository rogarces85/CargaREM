# Guion de aceptación (UAT)

Para validar el portal con personas reales antes de publicarlo. Cada rol
ejecuta su recorrido y registra si pasa o no.

**Preparación:** un período abierto, el catálogo importado y los usuarios de
prueba creados. Duración estimada: 2 horas por rol.

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
| R5 | Aprobar un envío completo | Cambia a «aprobado» y el encargado recibe el aviso |
| R6 | Confirmar la carga con una referencia | Queda «cargado» y la referencia aparece en el historial |
| R7 | Ver la matriz de completitud | El semáforo coincide con los envíos que conoce |
| R8 | Exportar la matriz | El CSV abre en Excel con las columnas separadas |

**Falla si:** ve envíos de otro servicio, aprueba algo incompleto, o la matriz
no coincide con la realidad.

---

## Rol: administrador

| # | Escenario | Criterio de aceptación |
|---|---|---|
| A1 | Importar un catálogo desde ZIP | Queda activo uno solo y los conteos cuadran con el manifiesto |
| A2 | Editar un establecimiento | El cambio se refleja en la matriz |
| A3 | Asignar series a un establecimiento | Las columnas de la matriz se ajustan |
| A4 | Crear un usuario con un rol | Puede entrar y ve lo que su rol permite |
| A5 | Intentar desactivarse a sí mismo | Se rechaza |
| A6 | Reabrir un envío cargado | Vuelve a revisión y queda el motivo |
| A7 | Anular un envío | Queda «anulado» y no se puede reabrir |
| A8 | Revisar la auditoría | Cada acción anterior aparece con su usuario y su fecha |

**Falla si:** puede dejar el sistema sin administrador, la auditoría tiene
huecos, o dos catálogos quedan activos a la vez.

---

## Registro de incidencias

| Fecha | Rol | Escenario | Qué pasó | Severidad | Estado |
|---|---|---|---|---|---|
| | | | | | |

Severidad: **bloqueante** (impide la tarea), **molesta** (hay que rodearla) o
**cosmética** (no impide nada).
