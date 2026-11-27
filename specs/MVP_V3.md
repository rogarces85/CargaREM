# MVP_V3.md — Portal M7 (versión 3)

> **Documento vivo.** Única fuente de verdad del proyecto desde el 2026-11-26.
> Reemplaza a `specs/MVP_V2.md`, que queda como historia del corte anterior.
> Canon técnico: Constitución v6.0.0. Rama de trabajo: `feature/mejoras-m7-v3`.

---

## 0. Qué cambia respecto de la v2

La v2 terminaba en la aprobación de la estadística. La v3 opera el ciclo completo de la Unidad de Estadísticas: reparto de la revisión por establecimiento, excepciones de plazo con motivo, corrección posterior a la aprobación, Serie P semestral, calendario generado solo y un panel que muestra qué está pasando.

| # | Antes (v2) | Ahora (v3) | Decisión |
|---|---|---|---|
| 1 | El revisor veía todo el Servicio | Ve solo sus establecimientos asignados | D-042 |
| 2 | No había forma de avisar que se subió una serie | El Equipo REM recibe el aviso, con distintivo de serie suelta o envío completo | D-050 |
| 3 | `aprobado` era final | Se reabre con motivo; el encargado vuelve a subir | D-043 |
| 4 | No había excepciones de plazo | Aperturas solicitadas y resueltas por el administrador, por establecimiento o para todos | D-044 |
| 5 | La serie P se exigía todos los meses | Se exige solo en los períodos 06 y 12 | D-045 |
| 6 | Los períodos se creaban a mano | Se generan solos, con el quinto día hábil verificado | D-046 |
| 7 | La lista de envíos era plana | Filtros por año, mes, comuna, establecimiento y estado, y orden explícito | D-049 |
| 8 | El panel era un menú | Panel con indicadores y gráficos por rol | D-048 |
| 9 | No había módulo de revisión aparte | Módulo Validador, aislado del flujo y con registro de uso | D-047 |
| 10 | El rol se llamaba «revisor» | Se muestra como **Equipo REM** | D-041 |

**Alcance superado.** La v3 **no** cambia el motor V0–V11, ni la regla del quinto día hábil, ni la identidad local, ni el almacenamiento de archivos. Todo eso sigue como en la v2 salvo lo que esta tabla declara.

---

## 1. Qué es

Portal web para que los establecimientos de salud suban su REM mensual (Excel), el sistema lo valide automáticamente y un equipo de cuatro personas —el **Equipo REM**— revise, observe o apruebe con trazabilidad.

**Reemplaza:** el envío por correo y la revisión manual sin trazabilidad.
**No reemplaza:** CARGAS REM, ni el ValidadorRem de escritorio, que sigue siendo la referencia de paridad.

---

## 2. Para quién

| Rol (clave interna) | Nombre en pantalla | Alcance | Qué hace |
|---|---|---|---|
| `encargado` | Encargado de establecimiento | Sus establecimientos asignados | Sube archivos, corrige, envía a revisión, pide apertura de plazo |
| `revisor` | **Equipo REM** | Solo los establecimientos que el administrador le asigna | Observa, aprueba, reabre, pide nueva subida, usa el Validador |
| `admin` | Administrador | Global | Usuarios, asignaciones, catálogos, períodos, aperturas, auditoría, uso del Validador |

**El rol `revisor` conserva su clave interna.** Cambia el nombre visible y su alcance, no la matriz de datos. Migrar la clave obligaría a reescribir permisos, tests y filas de `model_has_roles` sin que el usuario note la diferencia.

### Integrantes del Equipo REM

| Nombre | Asignación |
|---|---|
| Roxana Mancilla | La define el administrador |
| Marcelo Horstmeir | La define el administrador |
| Victoria Martínez | La define el administrador |
| Rodrigo Garcés | La define el administrador |

Ninguno llega con contraseña productiva conocida. En local los siembra `EquipoRemSeeder` con la clave de pruebas; en producción los crea el administrador.

**Regla de asignación.** Un establecimiento puede tener uno o varios revisores. Un revisor sin establecimientos **no ve datos** (ni envíos, ni revisión, ni completitud): su bandeja se muestra vacía con un mensaje, y el administrador recibe un aviso para que le asigne.

---

## 3. Stack

Sin cambios respecto de la v2:

- Laravel 12.72 + PHP 8.2, MySQL 8 (producción y suite).
- Tailwind CSS v4 + componentes Blade propios; Lucide; Inter Variable self-hosted; ApexCharts diferido.
- Sin Node en producción: Vite compila en local y `portal/public/build/` se publica como artefacto.
- Fortify (auth), spatie/laravel-permission (roles), PhpSpreadsheet (lectura Excel).

> Todo cambio de vista, icono o clase exige `npm run build` y versionar `public/build/` (Constitución, principio 31).

---

## 4. Alcance v3.0

### Incluido

| Capacidad | Módulo |
|---|---|
| Login, cuenta propia, 3 roles, **20 permisos**, alta de usuarios | MOD-01 |
| Asignación de establecimientos a cada revisor y a cada encargado | MOD-01 |
| CRUD de catálogos, matriz establecimiento-serie **con meses exigidos** | MOD-02 |
| Calendario de plazos: quinto día hábil, **generación automática** y verificación | MOD-02 |
| Envío mensual, versiones, hash, revalidación y descargas | MOD-03 |
| Validación V0–V11 (sin V8) y reporte de hallazgos | MOD-04 |
| Máquina de estados con **reapertura con motivo** | MOD-05 |
| Bandeja del Equipo REM **acotada a sus establecimientos** | MOD-06 |
| Observaciones por serie y aprobación completa | MOD-06 |
| **Solicitud de nueva subida** (una serie, todas las series, todos los asignados) | MOD-06 |
| **Aperturas de fuera de plazo** (solicitud del encargado, resolución del administrador) | MOD-06 |
| Matriz de completitud con semáforo y **Serie P semestral** | MOD-07 |
| Auditoría append-only, ahora con aperturas y uso del Validador | MOD-08 |
| Notificación en BD, campana **con distintivo** y página de historial | MOD-09 |
| **Módulo Validador** con reglas desde JSON y registro de uso | MOD-12 |
| **Panel con indicadores y gráficos por rol** | MOD-07 |
| Cabeceras de seguridad, CSRF, rate limit, health check | MOD-11 |

### Fuera de v3.0

| Función | Destino |
|---|---|
| Correo de notificación | No previsto |
| 2FA | v2.1 pendiente |
| SAX | Fase posterior |
| Importación histórica de prestaciones | Proyecto aparte |
| Exportación del panel a Excel | Solo si se pide |
| Consolidación y envío a CARGAS REM | Fuera del portal |

---

## 5. Requisitos funcionales

Los RF-001 a RF-039 de la v2 siguen vigentes. Se agregan:

### Validador (MOD-12)

- **RF-040** Módulo de revisión independiente: cualquier usuario autenticado sube un Excel REM y lo contrasta contra el **catálogo real de 144 reglas** de la Unidad (23 hojas REM, series A y P). No crea envíos, no cambia estados y no sustituye la validación V0–V11.
- **RF-040.1** Cada ejecución registra autor, archivo, hash, versión del catálogo, hallazgos y duración.
- **RF-040.2** El administrador ve el registro agregado por usuario, mes y resultado.
- **RF-040.3** La revisión pasa por cinco etapas en este orden: nombre del archivo, apertura del libro, hojas obligatorias de la serie, establecimiento en la nómina y hoja NOMBRE; después, las reglas de negocio de la serie.
- **RF-040.4** Una regla puede terminar en CUMPLE, HALLAZGO, OMITIDA o ERROR_TÉCNICO. Una regla omitida **no cuenta como cumplida**.
- **RF-040.5** El motor debe coincidir con el Validador de escritorio sobre sus archivos de prueba. Las diferencias se declaran con su causa; una diferencia nueva rompe el test.

> **El catálogo no es definitivo.** La Unidad lo está depurando. Actualizarlo es copiar `reglas_finales.json` a `portal/config/validador/reglas.json` y correr la suite: el test de paridad avisa si algo dejó de leerse.

### Avisos de subida (MOD-09)

- **RF-041** Cuando el encargado sube una serie o completa las exigidas, se avisa en la campana a cada revisor asignado al establecimiento.
- **RF-041.1** El aviso distingue **serie suelta** («Serie BS subida, 4 de 6 series») de **envío completo** («Envío completo, 6 de 6 series»).
- **RF-041.2** Sin revisor asignado, el aviso va al administrador y se registra el hecho.
- **RF-041.3** Una subida de varias series seguida produce **un** aviso por revisor y envío, actualizado durante 10 minutos.

### Asignación (MOD-01)

- **RF-042** El administrador asigna establecimientos a cada revisor desde una pantalla de asignación y desde el formulario de usuario.
- **RF-042.1** Un establecimiento puede tener varios revisores; el primero que actúa queda como `revisor_id` del envío.
- **RF-042.2** Un revisor sin asignación no ve datos y el administrador recibe un aviso para asignarle.

### Aperturas de fuera de plazo (MOD-06)

- **RF-043** El encargado pide una apertura desde su menú, con establecimiento, período y motivo obligatorio (≥ 15 caracteres).
- **RF-043.1** La solicitud llega al administrador y a los revisores del establecimiento.
- **RF-043.2** Solo el administrador la resuelve, con motivo de resolución.
- **RF-043.3** El administrador puede abrir una ventana por su cuenta, con motivo, para un establecimiento o para todos.
- **RF-043.4** La ventana tiene fecha de término. Mientras está vigente, ese establecimiento puede crear el envío y subir archivos aunque el período esté cerrado; los demás siguen cerrados.
- **RF-043.5** Una solicitud pendiente del mismo establecimiento y período se reutiliza; no se duplica.
- **RF-043.6** Toda solicitud, resolución y apertura queda auditada.

### Solicitud de nueva subida (MOD-06)

- **RF-044** El Equipo REM pide al encargado que vuelva a subir la estadística, con motivo obligatorio y uno de tres alcances: **una serie**, **todas las series de un envío**, **todos sus establecimientos asignados en un período**.
- **RF-044.1** La solicitud crea una observación por serie afectada, que se resuelve sola al subir la corrección.
- **RF-044.2** Si el envío está aprobado, se reabre primero con motivo y queda en el historial.
- **RF-044.3** El encargado recibe el aviso con el detalle de lo que debe subir.

### Serie P semestral (MOD-04 / MOD-07)

- **RF-045** La serie P se informa dos veces al año: la estadística de **junio** se entrega con el plazo de julio y la de **diciembre** con el plazo de enero.
- **RF-045.1** En los demás períodos no se exige, no cuenta como faltante en V11 ni en la matriz de completitud, y se acepta si llega.
- **RF-045.2** La regla vive en `App\Dominio\MatrizSeries`; la usan la validación, la pantalla del envío, la aprobación y la completitud.

### Calendario automático (MOD-02)

- **RF-046** Los períodos de un año se generan solos: al pedir un período que no existe, el sistema lo crea con su fecha calculada; un comando los genera por año completo.
- **RF-046.1** El plazo es el fin del quinto día hábil del mes siguiente, con sábados, domingos y feriados de Chile como no hábiles.
- **RF-046.2** Los feriados se cargan por año desde la lista oficial verificada (2026 y 2027) y un comando compara lo guardado con lo calculado.
- **RF-046.3** Una fecha fijada a mano se respeta y se muestra como manual.

### Envíos: alcance y lectura ordenada (MOD-03)

- **RF-047** La pantalla de envíos muestra solo los establecimientos del usuario.
- **RF-047.1** Filtros por año («todos» incluido), mes, comuna, establecimiento, estado y situación de plazo.
- **RF-047.2** Orden por período, por comuna → establecimiento → período, por establecimiento → período, o por estado → período.
- **RF-047.3** La paginación conserva los filtros y los años salen de los datos cargados.
- **RF-047.4** El encargado nunca ve un selector con establecimientos ajenos.

### Panel (MOD-07)

- **RF-048** Cada usuario tiene un panel con las cifras de su alcance.
- **RF-048.1** Encargado: series cargadas, cumplimiento de plazo, subidas por mes y estado de sus envíos.
- **RF-048.2** Equipo REM: sus establecimientos y comunas, avance por estado, atrasos y tiempos de revisión.
- **RF-048.3** Administrador: panorama del Servicio por estado, comuna, series faltantes y subidas fuera de plazo.
- **RF-048.4** Cada gráfico va acompañado de la misma información en texto o tabla.

### Nombre del rol (MOD-01)

- **RF-049** El rol que revisa la estadística se muestra como **Equipo REM** en interfaz, manuales y avisos. La clave interna sigue siendo `revisor`.

### Notificaciones (MOD-09)

- **RF-051** Existe una página de notificaciones con historial, filtro por tipo y «marcar todas como leídas».

---

## 6. Permisos (RBAC v3.0)

**20 permisos.**

| Permiso | encargado | Equipo REM | admin |
|---|---|---|---|
| `envios.crear` | ✅ | — | ✅ |
| `envios.ver.propio` | ✅ | — | ✅ |
| `envios.ver.ss` | — | ✅ | ✅ |
| `envios.enviar` | ✅ | — | ✅ |
| `envios.observar` | — | ✅ | ✅ |
| `envios.aprobar` | — | ✅ | ✅ |
| `envios.reabrir` | — | **✅** | ✅ |
| `envios.anular` | — | — | ✅ |
| `archivos.descargar` | ✅ | ✅ | ✅ |
| `reportes.descargar` | ✅ | ✅ | ✅ |
| `completitud.ver` | ✅ | ✅ | ✅ |
| `validador.usar` | ✅ | ✅ | ✅ |
| `validador.estadisticas` | — | — | ✅ |
| `aperturas.solicitar` | ✅ | — | ✅ |
| `aperturas.resolver` | — | — | ✅ |
| `catalogos.importar` | — | — | ✅ |
| `catalogos.gestionar` | — | — | ✅ |
| `usuarios.gestionar` | — | — | ✅ |
| `periodos.gestionar` | — | — | ✅ |
| `auditoria.ver` | — | — | ✅ |

> `envios.ver.ss` conserva el nombre por compatibilidad; su significado en v3 es «ver los envíos de los establecimientos asignados».

**Aislamiento.** Se aplica en la consulta, no en la vista (Constitución, principio 18). La fuente es `usuario_establecimiento`, que ya existía para el encargado y ahora también para el revisor.

---

## 7. Flujos

### FT-01 Subida y validación (v2, sin cambios)
Encargado crea envío → sube archivo → sistema valida → muestra hallazgos → «Enviar a revisión» con 0 bloqueantes y V11 OK.

### FT-02 Revisión y aprobación (v3)
Encargado envía → los revisores **asignados** reciben el aviso → abren su bandeja filtrada → observan una serie o aprueban.

### FT-03 Módulo Validador
Usuario abre Validador → sube un Excel → el sistema aplica las reglas del JSON → muestra hallazgos → registra la ejecución. No toca ningún envío.

### FT-04 Apertura de fuera de plazo
Encargado pide apertura con motivo → el administrador resuelve → si aprueba, fija hasta cuándo → el establecimiento puede cargar → vence y vuelve el bloqueo.

### FT-05 Solicitud de nueva subida
Equipo REM detecta un error → elige alcance y motivo → se reabre si hace falta → el encargado recibe el aviso → sube la corrección → la observación se resuelve sola → vuelve el flujo normal.

### FT-06 Reapertura de un envío aprobado
Equipo REM o administrador reabre con motivo → el envío pasa a `observado` → el encargado sube una versión nueva → `recibido` → valida → a revisión otra vez.

---

## 8. Reglas de negocio

| ID | Regla |
|---|---|
| **R-ALC-01** | El revisor solo alcanza los establecimientos asignados. Sin asignación, no alcanza ninguno (nunca «todos»). |
| **R-ALC-02** | El encargado alcanza sus establecimientos asignados; el administrador, todos. |
| **R-ALC-03** | Un establecimiento puede tener varios revisores; no hay exclusividad. |
| **R-APL-01** | Solo el administrador resuelve aperturas y solo él abre por su cuenta. |
| **R-APL-02** | El motivo de la solicitud y el de la resolución son obligatorios. |
| **R-APL-03** | Una apertura vale para un establecimiento (o todos) y un período, hasta su `vigente_hasta`. |
| **R-APL-04** | Una apertura vigente no cambia el estado del período: habilita a ese establecimiento. |
| **R-APL-05** | Vencida la apertura, el bloqueo vuelve sin intervención. |
| **R-REA-01** | Volver a subir después de aprobado exige reapertura con motivo. |
| **R-REA-02** | La reapertura no se escribe como arista libre: es un método con guardas. |
| **R-REA-03** | La reapertura queda en `envio_transiciones` y en `auditoria`. |
| **R-P-01** | La serie P se exige solo en los períodos 06 y 12. |
| **R-P-02** | En otros meses se acepta un archivo de P y no cuenta para completitud. |
| **R-PLA-01** | El plazo es el fin del quinto día hábil del mes siguiente. |
| **R-PLA-02** | El período que falta se crea al pedirlo, con su fecha calculada. |
| **R-PLA-03** | Una fecha manual no se recalcula nunca. |
| **R-VAL-01** | El Validador no escribe en `envios`, `envio_archivos`, `validaciones` ni `envio_transiciones`. |
| **R-VAL-02** | Un tipo de regla desconocido aborta la ejecución; no se ignora en silencio. |
| **R-VAL-03** | Cada ejecución queda registrada con autor y versión de reglas. |

---

## 9. Criterios de aceptación

1. Un revisor con dos establecimientos ve dos; con ninguno, ve cero y el administrador recibe el aviso.
2. Dos revisores con establecimientos distintos no se ven los envíos del otro, ni entrando por URL directa.
3. Una subida a un establecimiento con revisor asignado produce exactamente un aviso por revisor, y su texto distingue «serie suelta» de «envío completo».
4. Un envío aprobado no se puede reabrir sin motivo, ni sin permiso, ni desde un estado que no sea `aprobado`.
5. Una apertura vigente deja cargar al establecimiento autorizado y a ningún otro.
6. La serie P no aparece como faltante en los meses que no son 06 ni 12.
7. Los doce períodos de un año se generan sin intervención y sus fechas coinciden con el calendario chileno.
8. La pantalla de envíos filtra por año, mes, comuna, establecimiento y estado, y conserva el filtro al paginar.
9. El panel muestra cifras de cada rol y esas cifras siguen legibles con el JavaScript desactivado.
10. El Validador produce hallazgos desde el JSON y no modifica ninguna tabla de envío.
11. La suite completa queda en verde y `public/build/` está recompilado.

---

## 10. Datos

| Cambio | Tabla | Contenido |
|---|---|---|
| Meses exigidos por serie | `series` | `meses_exigidos` JSON nullable (`null` = todos); P = `[6,12]` |
| Aperturas de plazo | `aperturas` (nueva) | Establecimiento nullable, año, mes, origen, motivo, estado, resolución, `vigente_hasta` |
| Uso del Validador | `validador_ejecuciones` (nueva) | Usuario, archivo, hash, versión de reglas, hallazgos, duración |
| Índices de alcance y panel | `usuario_establecimiento`, `envios`, `establecimientos`, `envio_archivos` | Aceleran las consultas nuevas |

Nada de esto toca `envios`, `envio_transiciones` ni `validaciones` en su estructura.

---

## 11. Decisiones abiertas

| ID | Decisión | Quién la cierra |
|---|---|---|
| D5 | Series obligatorias | ✅ **Cerrada (D-038 y D-045):** todas las activas al importar, ajuste manual, P semestral |
| D6 | Tratamiento SAX | ✅ **Cerrada:** fuera del MVP y de la v3 |
| — | Nombres exactos y correos de los cuatro integrantes del Equipo REM | Unidad de Estadísticas |
| — | Formato del JSON del Validador | Se adapta al archivo que entregue la Unidad |

---

## 12. Trazabilidad

| Documento | Qué contiene |
|---|---|
| `02-DOCS/wiki/sdd/constitution.md` | v6.0.0, con el Validador y las aperturas en la auditoría |
| `02-DOCS/wiki/sdd/decisions.md` | C-020 y D-041 a D-050 |
| `docs/validacion/reglas-cierre-periodo.md` | Plazos, aperturas (R-CIE-13 a R-CIE-18) |
| `docs/validacion/matriz-series-obligatorias.md` | Meses exigidos y serie P |
| `02-DOCS/wiki/dominio/` | Artículos de dominio de la v3 |
| `REVIEW.md` | Fallos graves incorporados en la v3 |
