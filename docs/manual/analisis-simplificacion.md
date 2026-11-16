# Análisis: dónde el flujo se traba y qué se puede simplificar

> Documento de decisión, no de operación. Lo que sigue salió de recorrer el mes 10 con los
> tres roles y de leer el código que gobierna cada decisión.
>
> **Verificado el 2026-11-16 contra la base de desarrollo y el código en la rama
> `feature/rediseno-ui-clinova`.**

## Estado de los hallazgos

| # | Hallazgo | Estado |
|---|---|---|
| H1 | Serie F inactiva y todavía exigida | **Resuelto.** F vuelve a estar activa y una serie desactivada ya no se exige |
| H2 | Acciones de Revisión con URL sin subdirectorio | **Corregido** el 2026-11-16 |
| H3 | «Enviar a revisión» ofrecido en `con_errores` | **Corregido** el 2026-11-16 |
| H4 | Enviar a revisión sin ningún archivo | **Corregido** el 2026-11-16 |

H2, H3 y H4 eran incoherencias entre lo que la pantalla ofrecía y lo que el servicio aceptaba.
Se corrigieron sin tocar ninguna regla de negocio.

---

## Parte A — Los cuatro bloqueos encontrados

---

### H1 · La serie F estaba inactiva y la matriz la seguía exigiendo — **RESUELTO**

**Qué se decidió:** la serie F **no existe en la realidad desde hace años**, pero se conserva
como pieza de demostración: sirve para mostrar, dentro del sistema, cómo se deshabilita una
serie y cómo se vuelve a agregar.

**El problema original**

```
series.activo = 0            -> serie F
establecimiento_serie        -> obligatoria = 1 en 4 establecimientos
```

Ese estado era imposible de resolver para el usuario:

| Paso | Código | Qué hacía |
|---|---|---|
| Desplegable de subida | `EnvioController.php:95` | Solo lista series activas. **F no aparecía** |
| Guarda de subida | `EnvioService.php:100` | Rechazaba subir una serie inactiva |
| Validación V11 | `ReglaV11Completitud.php` | Exigía la serie según la matriz, **sin mirar `activo`** → `ENV-025` **bloqueante** |
| Guarda de aprobación | `EstadoEnvioService.php` | Misma consulta, mismo bloqueo |

El envío quedaba en `con_errores` para siempre, sin acción posible por parte del encargado.

**Las dos cosas que se hicieron**

1. **Se restauró el estado de demostración.** La serie F está **activa** y la matriz la exige
   en los 7 establecimientos, igual que en `L1MinimumSeeder`. Ojo: el seeder **ya la definía
   activa**; fue la base de desarrollo la que quedó modificada a mano tras probar el panel de
   Series. Ahora el estado se reproduce con `php artisan db:seed --class=L1MinimumSeeder`.

2. **Se agregó la red de seguridad (opción C del análisis anterior).** Una serie desactivada
   **deja de exigirse**, aunque la matriz conserve la fila. Cuatro lugares aplican el mismo
   criterio:

   | Archivo | Qué hace |
   |---|---|
   | `App\Validacion\Reglas\ReglaV11Completitud` | No reclama series inactivas |
   | `App\Services\EstadoEnvioService` | No cuenta series inactivas como faltantes al aprobar |
   | `App\Services\CompletitudService` | No muestra columna de serie inactiva |
   | `App\Http\Controllers\EnvioController` | No lista serie inactiva como exigida |

**Qué permite ahora la demostración**

| Acción en el panel | Resultado en el acto |
|---|---|
| Series → desmarcar **Activa** en F | F desaparece del desplegable de subida y de la matriz de completitud. Los envíos que la tenían **no se bloquean** |
| Series → volver a marcar **Activa** | F vuelve a exigirse donde la matriz la tenga |

Es reversible y no deja estados imposibles. Dos tests lo cubren:
`test_una_serie_desactivada_no_se_exige` y `test_una_serie_reactivada_vuelve_a_exigirse`.

> **Lo que sigue siendo cierto:** la matriz conserva la fila `obligatoria` aunque la serie esté
> inactiva. Es a propósito — así reactivarla no pierde la configuración. Pero significa que
> **desactivar una serie no la quita de la matriz**: el estado del catálogo es el que manda
> mientras esté inactiva. Si algún día se quiere que la matriz mande, hay que invertir la regla
> y volver a la opción A o B.

---

### H2 · Las acciones Observar y Aprobar apuntaban a una URL sin el prefijo del subdirectorio — **CORREGIDO**

**Qué pasaba:** en la bandeja de revisión los botones **Observar** y **Aprobar** abrían su
diálogo bien, pero al confirmar daban **404**.

**Por qué**

`resources/views/revision/index.blade.php` escribía la ruta a mano desde la raíz del dominio:

```js
document.getElementById('formObservar').action = '/revision/' + id + '/observar';
```

El portal se sirve en un subdirectorio (`APP_URL=http://localhost/Cargas/portal/public`), así
que el navegador enviaba el POST a `http://localhost/revision/...` y no a
`http://localhost/Cargas/portal/public/revision/...`.

Todo el resto de la aplicación usa `route()`, que sí resuelve el prefijo. Solo este bloque no.

**Qué se cambió:** el servidor entrega la URL ya resuelta en un atributo `data-url`, y el
JavaScript la lee. Es la misma técnica que la vista ya usaba para la referencia y las series.

**Cómo se comprobó:** con un envío en `en_revision`, la página renderiza
`data-url="http://localhost/Cargas/portal/public/revision/1/observar"` y, al abrir el diálogo,
`formObservar.action` es exactamente esa dirección.

---

### H3 · El botón «Enviar a revisión» se ofrecía cuando la acción no existía — **CORREGIDO**

**Qué pasaba:** un envío en `con_errores` mostraba el botón, y al pulsarlo fallaba con
«transición no permitida».

**Por qué** — dos listas que no coincidían:

| Dónde | Qué admitía |
|---|---|
| `EnvioPolicy.php:61` | `recibido`, `validado`, **`con_errores`** |
| `EstadoEnvioService.php:36` | `CON_ERRORES => []` — **ninguna** salida |

La vista pregunta a la policy, la policy decía que sí, y el servicio decía que no.

**Qué se cambió:** la policy quedó con la misma lista que la máquina de estados. Un envío con
bloqueantes no se manda; se corrige.

---

### H4 · Un envío sin ningún archivo se podía enviar a revisión — **CORREGIDO**

**Qué pasaba:** se creaba el envío (estado `recibido`, cero archivos) y «Enviar a revisión»
funcionaba. El revisor recibía un envío vacío que no podía aprobar.

**Por qué**

- `EstadoEnvioService.php:34` permite `recibido → en_revision`.
- `EnvioPolicy.php:61` permitía enviar desde `recibido`.
- **No había ninguna guarda de completitud al enviar.**

Al aprobar sí hay cuatro guardas (`EstadoEnvioService.php:186-224`): sin observaciones, sin
bloqueantes, todas las series presentes. **Al enviar no había ninguna.**

**La asimetría era el hallazgo:** el sistema estricto al aprobar y permisivo al enviar. El
trabajo sucio lo absorbía el revisor, que es justo lo que un flujo no debería hacer.

**Qué se cambió:** la policy exige al menos un archivo vigente. La autorización es la puerta:
un POST directo tampoco pasa.

> **Lo que sigue abierto en este punto:** el sistema no exige *todas* las series al enviar,
> solo *alguna*. Un envío con 1 de 5 series sigue pudiendo llegar a revisión. Cerrarlo del
> todo es la Propuesta 1, y esa sí cambia el flujo.

---

## Parte B — Decisiones de diseño que conviene confirmar

No son errores. Son cosas que funcionan y que puede que no quieras que funcionen así.

### D-a · El período no se cierra solo

La fecha límite del período 10/2026 fue el **06-11-2026**. Hoy es 16-11 y el período sigue
`abierto`. Los encargados pueden seguir cargando.

El sistema **marca** el atraso (`fuera_de_plazo` en el envío, insignia «Fuera de plazo» y
filtro en la bandeja), pero **no bloquea**.

**Pregunta:** ¿el plazo es un aviso o una barrera? Hoy es un aviso. Si tiene que ser una
barrera, hay que decidir quién la levanta y cómo se registra la excepción.

### D-b · «En revisión» significa dos cosas distintas

| Dónde | Qué significa |
|---|---|
| `periodos.estado = en_revision` | El Servicio tomó el mes. Casi nadie puede cargar |
| `envios.estado = en_revision` | Ese envío concreto espera al revisor |

Son dos niveles y dos significados con el mismo nombre. En una reunión eso confunde. Sugerencia
de renombrado en pantalla: para el período, **«Recepcionado»** o **«Tomado por el Servicio»**.

### D-c · La fecha límite se copia al crear el envío

Cada envío guarda la fecha límite del período **en el momento de crearse**. Si después se
corrige el calendario, los envíos ya creados conservan la fecha antigua.

Es defendible (el envío conserva la regla con la que nació), pero hay que saberlo: corregir el
calendario **no** repara los envíos del mes.

### D-d · Los avisos son solo campana, sin correo

`Notificador.php` deja el aviso en la tabla `notifications`. **No se envía correo en esta
versión.**

Consecuencia real: si un encargado no entra al portal, no se entera de la observación. Para
la marcha blanca conviene un acuerdo explícito de contacto por otra vía.

---

## Parte C — Qué se puede simplificar

Cinco propuestas, de la más barata a la más profunda. Ninguna está hecha: esto es material
para decidir.

---

### P1 · Quitar el botón «Enviar a revisión» y dejar que el sistema envíe solo

**Qué se ahorra:** un clic por envío, y de golpe desaparecen H3 y H4. Un envío no puede
quedar incoherente porque nadie lo empuja: pasa a revisión **cuando está completo y sin
bloqueantes**, que es exactamente la condición que el revisor exige para aprobar.

**Qué se pierde:** el momento en que una persona declara «mi estadística está terminada».
Eso tiene peso administrativo: si el sistema envía solo, el establecimiento nunca dijo
formalmente que entregó.

**Alternativa intermedia:** dejar el envío automático, pero con un paso de **confirmación**
que quede en el historial como declaración del encargado. Se ahorra el desorden, no la
declaración.

**Coste técnico:** bajo. Es cambiar una transición por un evento tras `recalcular`.

---

### P2 · Permitir abrir el envío desde la matriz de completitud

Hoy la matriz muestra «Sin archivo» en rojo y **la celda no se puede pulsar**. Para ir al
envío hay que salir de la pantalla, entrar a Envíos y buscarlo.

Esto viene de la decisión C-018, que dejó el drill-down fuera del alcance v2.0. Es defendible
como alcance, pero en la práctica es la fricción más tonta del sistema: la pantalla te dice
qué falta y no te deja ir a arreglarlo.

**Coste:** bajo. Enlazar la celda al envío correspondiente cuando el usuario tiene permiso.

**Matiz:** si vuelve el drill-down, hay un test que lo prohíbe
(`CompletitudTest::test_la_celda_ya_no_lleva_al_envio`). Habría que retirar ese test junto con
la decisión, no antes.

---

### P3 · Unir «Envíos» y «Completitud» en una sola pantalla para el encargado

Para un encargado con 3 o 4 establecimientos, las dos pantallas responden casi lo mismo:

| Pregunta | Dónde está hoy |
|---|---|
| ¿Qué envíos tengo y en qué estado? | Envíos |
| ¿Qué me falta entregar? | Completitud |

Una sola tabla — establecimiento × serie, con la acción de subir en la celda — cubriría las
dos. El encargado no tendría que aprender que existen dos pantallas para su mismo trabajo.

**Qué se pierde:** la vista de lista es mejor para ver el estado de un envío concreto; la
matriz es mejor para ver el mes completo. Puede que convenga **no** unirlas y en cambio
enlazarlas (P2).

**Coste:** medio. Es una pantalla nueva, no un ajuste.

---

### P4 · Hacer que la matriz de series avise cuando se desactiva una serie exigida

El bloqueo H1 nació de un dato incoherente que **nadie podía ver**. La pantalla de Series
deja desactivar sin decir nada; la de Matriz no se entera.

Un aviso en el momento — «esta serie está exigida en 4 establecimientos; si la desactiva,
esos envíos no podrán aprobarse» — evita el problema antes de que exista.

**Coste:** bajo. Es una comprobación al guardar.

---

### P5 · Cerrar el período con una acción explícita y validada

Hoy el cierre es editar un registro del catálogo y cambiar un desplegable. No comprueba
nada: se puede cerrar un mes con envíos pendientes.

**Propuesta:** una acción **«Cerrar período»** que muestre antes qué queda pendiente y
registre quién cerró y con qué pendientes.

**Coste:** medio-bajo. Reutiliza la consulta de Completitud.

---

## Resumen para decidir

| # | Qué | Tipo | Prioridad |
|---|---|---|---|
| ~~H1~~ | ~~Serie F inactiva y exigida~~ | Dato + red de seguridad | **Resuelto.** F activa para la demo y serie inactiva ya no se exige |
| ~~H2~~ | ~~URL sin subdirectorio en Revisión~~ | Defecto | **Corregido** |
| ~~H3~~ | ~~Botón «Enviar» en estado imposible~~ | Defecto | **Corregido** |
| ~~H4~~ | ~~Enviar sin archivos~~ | Defecto de coherencia | **Corregido** (queda el caso parcial, ver P1) |
| D-a | El plazo no bloquea | Decisión | Definir con la Unidad |
| D-b | «En revisión» duplicado | Nomenclatura | Cosmético, alto impacto en explicaciones |
| D-c | Fecha límite copiada | Decisión | Documentar |
| D-d | Sin correo | Decisión | Acordar vía alterna |
| P1 | Envío automático | Simplificación | Evaluar |
| P2 | Drill-down en la matriz | Simplificación | Recomendada |
| P3 | Unir Envíos y Completitud | Rediseño | Evaluar |
| P4 | Aviso al desactivar serie | Prevención | Recomendada |
| P5 | Cierre de período validado | Simplificación | Evaluar |
