# Guía del revisor del Servicio de Salud

> Para quien revisa la estadística que entregan los establecimientos y la aprueba.
> Usuario de la demo: `revisor@ssosorno.cl`, clave `Portal2026!`.

---

## 1. Lo que ves al entrar

Tu menú tiene **tres** pantallas de operación, una más que el encargado:

| Sección | Pantalla | Para qué |
|---|---|---|
| General | **Panel** | Punto de partida |
| Operación | **Envíos** | El detalle de cualquier envío del Servicio |
| Operación | **Revisión** | **Tu bandeja de trabajo** |
| Operación | **Completitud** | Quién ha entregado y quién no |

Ves todo el Servicio de Salud Osorno: 7 establecimientos. No ves otros Servicios.

La **campana** de la cabecera te avisa cuando un establecimiento manda algo a revisión.

---

## 2. Tu mes, paso a paso

### Paso 1 — Abrir la bandeja

Menú **Revisión**.

La bandeja abre **filtrada en «En revisión»**, que es tu trabajo pendiente. Ordenada por
**los más antiguos primero**: lo que lleva más tiempo esperando aparece arriba.

| Columna | Cómo leerla |
|---|---|
| Establecimiento | Código y nombre |
| Período | `10/2026` |
| Estado | Dónde está el envío |
| **Series** | Cuántos archivos vigentes tiene |
| **Observaciones** | Cuántas quedan sin resolver (en ámbar) |
| Esperando desde | Hace cuánto llegó |
| Acciones | Ver · Observar · Aprobar |

> Un envío con pocas series es la primera señal de que algo falta. Compáralo con lo que la
> matriz exige: el Hospital Base debe entregar 5.

### Paso 2 — Abrir el envío

Pulsa **Ver**. Es la pantalla más importante y conviene conocerla entera:

| Bloque | Qué trae |
|---|---|
| Cabecera | Establecimiento, período, código y **fecha límite** |
| Estado | El del envío, más la marca **«Fuera de plazo»** si llegó tarde |
| Indicadores | Bloqueantes, advertencias, series exigidas, series subidas |
| **Series del período** | Una fila por serie: archivo, versión, estado, bloqueantes |
| **Hallazgos** | Todo lo que la validación encontró, con hoja y celda |
| **Historial** | Quién movió el envío, cuándo y por qué |

El historial es tu respaldo. Si alguien pregunta «¿cuándo llegó esto?», está ahí.

### Paso 3 — Revisar el contenido

Mira, en este orden:

1. **¿Están todas las series exigidas?** Compara «Series subidas» con «Series exigidas».
2. **¿Hay bloqueantes?** Si los hay, el envío no debió llegar. No lo apruebes.
3. **¿Llegó fuera de plazo?** La marca roja lo dice.
4. **¿Las advertencias son aceptables?** Una advertencia no impide aprobar, pero **debes
   verla**. Si se repite siempre y nadie la mira, hay que convertirla en bloqueante: eso se
   decide con la Unidad de Estadísticas.

Puedes descargar el archivo de cada serie desde la tabla de series, y el **reporte** completo
desde el botón de la cabecera.

### Paso 4 — Decidir: observar o aprobar

Ambas acciones están en la bandeja, en la fila del envío. No hace falta entrar al detalle.

#### Observar

Pulsa **Observar**. Se abre un diálogo con dos campos:

| Campo | Para qué |
|---|---|
| **¿Sobre qué serie?** | Si eliges una serie, la observación **se resuelve sola** cuando el encargado suba la corrección de esa serie. Si eliges «Todo el envío», la resuelve el revisor |
| **¿Qué debe corregir?** | Obligatorio. Escríbelo como instrucción: qué está mal y qué se espera |

Al confirmar, el envío pasa a **observado** y vuelve al establecimiento.

> **Elige la serie siempre que puedas.** Ata la observación al archivo, y el sistema la cierra
> cuando llega la corrección. Si observas «todo el envío», alguien tiene que acordarse de
> cerrarla a mano.

#### Aprobar

Pulsa **Aprobar**. Se abre un diálogo con un comentario opcional.

**El sistema no te deja aprobar si falta alguna de estas cuatro condiciones:**

| # | Condición | Por qué |
|---|---|---|
| 1 | El envío está **en revisión** | No se aprueba lo que no llegó a revisión |
| 2 | **No hay observaciones abiertas** | Aprobar con algo pendiente es contradecirse |
| 3 | **No hay bloqueantes** | Un bloqueante es un dato que no sirve |
| 4 | **Están todas las series exigidas** | Aprobar a medias deja el dato faltante en la consolidación |

Si alguna falla, el sistema te dice cuál. El diálogo avisa antes de intentarlo: cuando hay
observaciones abiertas, **desactiva el botón** y muestra el motivo.

Al aprobar, el envío pasa a **aprobado**, que es el estado final. Sus archivos también quedan
aprobados.

### Paso 5 — Cerrar el período

Cuando ya no queda nada en la bandeja:

1. Menú **Completitud**. Elige año y mes.
2. Mira el bloque **Avance por serie**. Cada serie tiene una barra apilada con los cinco
   estados del semáforo: verde aprobado, ámbar en proceso, rojo con errores, gris oscuro sin
   archivo y gris claro sin envío. De un vistazo se ve qué serie está frenando el mes y en qué
   punto está trabada.
3. Revisa la matriz: una fila por establecimiento, una columna por serie.
4. La columna **Avance** te dice `aprobadas / exigidas` por establecimiento.
5. El porcentaje de arriba es el del Servicio completo.

Ahí ves de un golpe quién no entregó y qué serie se está quedando atrás. Es el argumento para
llamarlos.

> Cada barra cuenta solo los establecimientos a los que esa serie les corresponde. Una serie
> exigida a 4 establecimientos no se compara contra los 7 del Servicio.

> **Sin envío** y **sin archivo** no son lo mismo: «sin envío» significa que ese
> establecimiento no creó el envío del mes; «sin archivo» significa que el envío existe pero
> le falta esa serie. El primero es un problema de gestión, el segundo de carga.

> La pantalla de completitud **no exporta** en esta versión. Si necesitas el dato en otro
> lado, es una conversación pendiente, no un botón escondido.

---

## 3. Filtros de la bandeja

| Filtro | Para qué sirve |
|---|---|
| Estado | Cambiar de «En revisión» a «Observado» para ver qué está esperando al establecimiento |
| Establecimiento | Un hospital concreto |
| Año | Períodos de otro año |
| Antigüedad | «Más de N días»: lo que se está enfriando |
| Plazo | Solo los que llegaron fuera de plazo |
| **Limpiar** | Vuelve al estado inicial |

El estado **«Todos»** existe y es útil, pero cuidado: mezcla aprobados con pendientes.

---

## 4. Lo que el sistema te dice y qué hacer

| Mensaje | Qué significa |
|---|---|
| «Este envío tiene observaciones sin resolver» | Aprobaste antes de cerrar lo que pediste. Resuélvelo o espera la corrección |
| «No se puede aprobar: el envío tiene N bloqueante(s)» | Datos que no sirven. Observa y pide corrección |
| «No se puede aprobar: faltan N serie(s)» | El envío está incompleto. Debió no haber llegado |
| «Transición no permitida» | El envío cambió de estado mientras mirabas. Recarga |

---

## 5. Lo que no puedes hacer

- **Ver otro Servicio de Salud.** Tu alcance es el tuyo.
- **Aprobar un envío donde tú eres el encargado.** Es segregación de funciones: quien carga
  no aprueba (RN-ENV-7).
- **Reabrir un envío aprobado.** «Aprobado» es final en esta versión.
- **Anular un envío.** Solo el admin tiene esos permisos.
- **Editar catálogos ni usuarios.**

---

## 6. Un aviso sobre las notificaciones

Los avisos llegan a la **campana del portal**, no por correo. En esta versión no se envía
correo.

Consecuencia práctica: **si un encargado no entra al portal, no se enteró de la
observación.** Para el período de demostración conviene avisar también por teléfono o
WhatsApp, como se hacía antes.
