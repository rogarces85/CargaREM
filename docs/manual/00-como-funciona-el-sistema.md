# Cómo funciona el sistema — mapa para explicarlo

> Documento de trabajo para la etapa de demostración. No reemplaza al manual oficial
> (`manual-usuario.md`). Explica cómo se conectan los módulos y qué hace cada rol en el mes.
>
> **Datos reales de la base de desarrollo al 2026-11-16.** Todo lo que dice «hoy» es ese día.

---

## 1. La idea que explica todo lo demás

El sistema tiene **una sola unidad de trabajo**: el **envío**.

Un envío es la estadística de **un establecimiento** en **un período** (mes y año). No hay
envíos por serie, ni por archivo, ni por usuario. Un hospital que debe cinco series tiene
**un** envío con cinco archivos dentro.

Todo lo demás cuelga de ahí:

```text
Servicio de Salud (1)
└── Establecimientos (7)          ← el encargado tiene asignados los suyos
    └── Matriz establecimiento × serie   ← qué series le exige el SS a cada uno
        └── Envío (establecimiento + período)
            ├── Archivo por serie (v1, v2, …)   ← se reemplaza, nunca se borra
            │   └── Hallazgos de validación
            ├── Hallazgos del envío completo (V11: faltan series)
            ├── Observaciones del revisor
            └── Historial de transiciones
```

Si alguien entiende que **el envío es el establecimiento-mes**, entiende el sistema.

---

## 2. Los tres roles y su alcance

| Rol | Quién es en la demo | Qué ve | Qué hace |
|---|---|---|---|
| **encargado** | Encargado Hospital Base (`123100 123101 123102 123103`) y Encargado Postas (`123104 123105 123106`) | Solo sus establecimientos | Crea el envío, sube archivos, envía a revisión |
| **revisor** | Revisor Unidad de Estadísticas | Todo el Servicio (1 servicio, 7 establecimientos) | Observa o aprueba |
| **admin** | Administrador REM | Todo, incluida la administración | Catálogos, matriz, calendario, usuarios, y también puede operar |

El alcance se aplica **en la consulta a la base**, no escondiendo botones
(`Envio::scopeForUser`). Un encargado no puede abrir el envío de otro ni escribiendo la URL.

Hay **dos usuarios encargado** con el mismo rol y distinto alcance. Es a propósito: el
Hospital Base y las Postas Rurales son dos personas.

---

## 3. Los seis estados del envío

```text
                    ┌──────────────┐
   se crea ────────►│  recibido    │
                    └──────┬───────┘
                           │ el sistema recalcula al subir archivos
              ┌────────────┴─────────────┐
              ▼                          ▼
      ┌──────────────┐          ┌──────────────┐
      │  validado    │          │ con_errores  │
      └──────┬───────┘          └──────────────┘
             │ el encargado          ▲
             │ pulsa «Enviar        │ (no sale sin corregir:
             │ a revisión»          │  hay bloqueantes)
             ▼
      ┌──────────────┐  el revisor aprueba   ┌──────────────┐
      │ en_revision  │──────────────────────►│  aprobado    │  FIN
      └──────┬───────┘                       └──────────────┘
             │ el revisor observa
             ▼
      ┌──────────────┐
      │  observado   │──► el encargado sube corrección ──► recibido
      └──────────────┘
```

### Dos tipos de movimiento, y la diferencia importa

| | Quién | Cómo | Deja historial |
|---|---|---|---|
| **Automático** (`recalcular`) | El sistema | Mira los archivos vigentes y decide `recibido`, `validado` o `con_errores` | Sí, con actor `sistema` |
| **Humano** (`transicionar`) | Una persona | Pulsa un botón; hay estados que solo una persona puede producir | Sí, con actor y motivo |

Mezclar los dos fue el defecto del sistema anterior. Aquí no se mezclan: un envío no pasa a
`en_revision` porque los archivos estén bien, pasa porque **alguien lo manda**.

### Las cinco transiciones que existen

Copiadas de `EstadoEnvioService::PERMITIDAS`, que es la única autoridad:

| Desde | Hasta | Quién |
|---|---|---|
| `recibido` | `en_revision` | encargado |
| `validado` | `en_revision` | encargado |
| `en_revision` | `observado` | revisor |
| `en_revision` | `aprobado` | revisor |
| `observado` | `recibido` | automático, al subir la corrección |

`con_errores` y `aprobado` no salen a ningún lado. `aprobado` es final en esta versión.

---

## 4. Cómo se conectan los módulos

Esta es la parte que faltaba. Cada fila es una flecha real del código.

| Módulo | Alimenta a | Qué le entrega |
|---|---|---|
| **Servicios** | Establecimientos, y el alcance del revisor | El Servicio de Salud al que pertenece cada establecimiento |
| **Establecimientos** | Envíos, usuarios, matriz | El sujeto de la estadística |
| **Series** | Matriz, subidas, completitud | Qué se puede subir. Una serie inactiva no se ofrece en el desplegable |
| **Matriz establecimiento × serie** | Envío (V11), completitud, guarda de aprobación | **Qué series le exige el SS a cada establecimiento.** Es la tabla que decide si un envío está completo |
| **Períodos** | Envíos | La fecha límite y si acepta cargas |
| **Feriados** | Calendario de plazos | Qué días no cuentan para el plazo |
| **Calendario de plazos** | Períodos | Calcula la fecha límite: fin del **5.º día hábil del mes siguiente** |
| **Plantillas** | Validación (V3) | La versión de plantilla que se espera por año, mes y serie |
| **Usuarios** | Alcance de todo | Qué establecimientos ve cada encargado y qué Servicio ve cada revisor |
| **Envíos** | Revisión, completitud, reporte | El trabajo del mes |
| **Revisión** | Envíos (observaciones) | Lo que hay que corregir |
| **Completitud** | — | Solo lee. Cruza establecimientos × series × envíos |

### La cadena que más importa

```text
Matriz (obligatoria = sí)
   ├──► desplegable de series al subir
   ├──► V11 «faltan series» → estado con_errores
   ├──► columna de la matriz de completitud
   └──► guarda de aprobación del revisor
```

**Una fila en la matriz cambia cuatro cosas a la vez.** Por eso desactivar una serie sin
tocar la matriz deja el sistema en un estado imposible. Es exactamente lo que pasa hoy con la
serie F (ver §7).

### Para qué sirve la pantalla de completitud

No es un adorno ni un reporte. Responde una sola pregunta:

> De todo lo que el Servicio exige este mes, ¿qué llegó y qué falta?

- El **encargado** la usa para verse a sí mismo: sus filas, sus columnas.
- El **revisor** la usa antes de cerrar el período: qué establecimientos no han aparecido.
- No tiene exportación en esta versión. La pantalla **es** la salida.

---

## 5. El mes 10 recorrido en el tiempo

Este es el guion para la demostración. Período **10/2026**.

| Dato | Valor real |
|---|---|
| Fecha límite del período 10/2026 | **06-11-2026** (5.º día hábil de noviembre) |
| Estado del período hoy | `abierto` |
| Días de atraso al 2026-11-16 | **10** |
| Series exigidas al Hospital Base San José | A, BS, BM, D, P |

> **Consecuencia para la demo:** cualquier envío de octubre que se mande a revisión hoy
> quedará marcado **«Fuera de plazo»**. No es un error: el sistema está diciendo la verdad.
> Conviene decirlo en voz alta durante la demostración, porque es una de las cosas que el
> sistema anterior no hacía.

### Semana a semana, con los tres roles en paralelo

| Momento | Encargado (establecimiento) | Revisor (SS) | Admin |
|---|---|---|---|
| Antes del día 1 | — | — | Confirma que el período 10/2026 existe y su fecha límite es 06-11 |
| Día 1–2 | «Crear envío»: elige su establecimiento y el período 10/2026 | — | — |
| Día 2–3 | Sube cada serie: `123100A10.xlsm`, `123100BS10.xlsm`, … | — | — |
| Al subir | Ve el resultado: bloqueantes y advertencias por archivo | — | — |
| Día 4 | Corrige el Excel y vuelve a subir. Cada subida **crea versión nueva** | — | — |
| Cuando todas están sin bloqueantes | El envío pasa solo a `validado` | — | — |
| Día 5 | Pulsa **«Enviar a revisión»** | — | — |
| — | El envío queda en `en_revision`. Llega un aviso a la campana | Ve el envío en su bandeja | — |
| Día 6–8 | — | Abre el envío, revisa archivos y hallazgos | — |
| Día 8 | — | **Observa** una serie concreta, o **Aprueba** | — |
| Si observó | Recibe el aviso, corrige esa serie y vuelve a subir | La observación se resuelve **sola** | — |
| Si aprobó | — | — | El envío queda `aprobado` y suma en la matriz |
| Fin de mes | Consulta Completitud: cuánto le falta | Consulta Completitud: quién no ha entregado | Cierra el período |

### Lo que el sistema hace sin que nadie se lo pida

| Cuándo | Qué hace solo |
|---|---|
| Al crear el envío | Copia la fecha límite del período |
| Al subir un archivo | Valida V0–V11, guarda hallazgos, recalcula el estado |
| Al subir una corrección | Devuelve el envío a `recibido` y **resuelve** la observación de esa serie |
| Al enviar a revisión | Marca «fuera de plazo» si corresponde, avisa a los revisores |
| Al observar o aprobar | Avisa a los encargados, escribe el historial |
| Siempre | Escribe en auditoría cada cambio |

### Lo que exige una persona, sin excepción

- Crear el envío.
- Subir los archivos.
- Pulsar «Enviar a revisión».
- Observar o aprobar.

No hay ninguna acción que el sistema complete por su cuenta en el flujo de aprobación.

---

## 6. Dónde vive la verdad de cada cosa

| Pregunta | Tabla |
|---|---|
| ¿Qué series se le exigen a este establecimiento? | `establecimiento_serie` (columna `obligatoria`) |
| ¿Qué archivo está vigente? | `envio_archivos.es_vigente` |
| ¿Por qué un archivo falló? | `validaciones` (con etapa V0–V11 y código `ENV-0xx`) |
| ¿Qué se le pidió corregir? | `observaciones` (columna `resuelta`) |
| ¿Quién movió el envío y cuándo? | `envio_transiciones` |
| ¿Cuándo vence el mes? | `periodos.fecha_limite` |
| ¿Qué avisos tiene pendientes una persona? | `notifications` |

**Los avisos son solo en la campana.** En esta versión no se manda correo. Si alguien no
entra al portal, no se enteró.

---

## 7. Lo que hoy no funciona como debería

Esto no es parte del manual. Es lo que hay que revisar antes de mostrar el sistema.
El detalle y la propuesta están en `analisis-simplificacion.md`.

| # | Qué | Estado |
|---|---|---|
| 1 | La serie **F está inactiva pero la matriz la sigue exigiendo** en 4 de 7 establecimientos | **Abierto.** Esos 4 nunca llegan a `validado`: no se puede subir F y V11 la reclama |
| 2 | Las acciones **Observar** y **Aprobar** de la bandeja usaban una URL sin el prefijo del subdirectorio | Corregido el 2026-11-16 |
| 3 | El botón **«Enviar a revisión»** se ofrecía en estado `con_errores`, donde la máquina de estados lo rechaza | Corregido el 2026-11-16 |
| 4 | Un envío **sin ningún archivo** se podía enviar a revisión | Corregido el 2026-11-16 |

Queda **uno** abierto: el de la serie F. Necesita una decisión de negocio, no de código.

> Nota de honestidad: el sistema sigue permitiendo enviar a revisión un envío **incompleto**
> (con 1 de 5 series). No es una incoherencia visible — V11 lo marca como bloqueante en cuanto
> se valida un archivo, así que en la práctica no ocurre. Pero la guarda al enviar es más
> débil que la de aprobar. Está explicado en la Propuesta 1 del análisis.
