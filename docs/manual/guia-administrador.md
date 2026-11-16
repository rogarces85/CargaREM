# Guía del administrador

> Para quien mantiene el catálogo, el calendario y los usuarios.
> Usuario de la demo: `admin@ssosorno.cl`, clave `Portal2026!`.

---

## 1. Lo que ves al entrar

Tu menú es el más largo. Tres secciones:

| Sección | Pantalla | Qué controla |
|---|---|---|
| General | Panel | Punto de partida |
| Operación | Envíos · Revisión · Completitud | Las tres, porque tienes todos los permisos |
| **Administración** | Servicios, Establecimientos, Series, Matriz de series, Períodos, Calendario de plazos, Feriados, Plantillas | El catálogo |
| **Usuarios** | Usuarios | Quién entra y qué puede hacer |

**El orden importa.** El catálogo se arma de arriba abajo: primero el Servicio, después sus
establecimientos, después las series, y al final la matriz que los cruza.

---

## 2. Antes del mes: la preparación

Esta es la secuencia que deja el mes listo para que los encargados trabajen.

### Paso 1 — Verificar los feriados del año

Menú **Feriados**.

Los feriados son lo que hace que el plazo sea correcto. Sin ellos, el sistema cuenta solo
sábados y domingos, y **el plazo queda uno o dos días más largo**.

| Campo | Qué poner |
|---|---|
| Fecha | La del feriado |
| Nombre | El oficial |
| Ámbito | Nacional o Regional |
| Región | Solo si es regional (la nuestra es la 10) |

> **Revisa el año siguiente antes de diciembre.** La pantalla de calendario avisa si falta.

### Paso 2 — Aplicar el calendario del año

Menú **Calendario de plazos**.

Elige el año y pulsa **Aplicar calendario**. Con eso:

- Se **crean** los 12 períodos que faltaban, en estado `abierto`.
- Se **actualiza** la fecha límite de los que existen, salvo los que tengan fecha manual.
- **No cambia el estado** de ningún período existente. Eso es a propósito.

La tabla del año muestra, mes por mes, la fecha calculada y si el período ya existe.

**Cómo se calcula la fecha límite**

> Fin del **quinto día hábil del mes siguiente**.

Ejemplo real: el período **10/2026** vence el **06-11-2026**. Noviembre de 2026 empieza
domingo; los días hábiles son 2, 3, 4, 5 y 6. El quinto es el 6.

**Excepción manual:** si un mes necesita otra fecha, ve a **Períodos**, edita ese registro y
marca «Fecha límite manual». Desde ahí el sistema **no vuelve a calcularla**, aunque apliques
el calendario otra vez. Es la válvula de escape y conviene usarla poco.

### Paso 3 — Revisar la matriz de series

Menú **Matriz de series**.

Es la pantalla más importante del catálogo. Define **qué series le exige el Servicio a cada
establecimiento**. Una fila mal puesta aquí no se queda quieta: cambia el desplegable de
subida, la validación de completitud, el estado del envío y la guarda de aprobación.

**Esta pantalla es la que hay que revisar cada vez que una serie cambia de estado.**

### Paso 4 — Revisar los usuarios

Menú **Usuarios**.

Cada persona necesita:

| Campo | Importa porque |
|---|---|
| Nombre y correo | Identidad. El correo no se cambia desde el perfil |
| **Rol** | Decide qué pantallas ve |
| **Activo** | Un usuario inactivo no recibe avisos |
| Contraseña | Al crear; después la cambia la persona |

> **Los establecimientos de cada encargado también se asignan aquí.** Sin esa asignación, el
> encargado entra y no tiene ningún establecimiento donde crear envíos.

---

## 3. Durante el mes

### Los estados del período

| Estado | Qué permite |
|---|---|
| **Abierto** | Los encargados cargan y envían a revisión. Es el estado normal |
| **En revisión** | Solo el admin puede cargar. Se usa cuando el Servicio toma el control del mes |
| **Cerrado** | Nadie carga. El mes quedó firme |

Se cambia en **Períodos → Editar → Estado**.

> **No hay cierre automático.** El sistema **no** cambia el período a `en_revision` ni a
> `cerrado` cuando pasa la fecha límite. Ese cambio lo hace una persona. Si nadie lo hace, un
> encargado puede cargar el mes 10 en enero.

### Qué vigilar

1. **Completitud**: el porcentaje del Servicio. Si no sube, hay establecimientos que no
   entregaron.
2. **Revisión → filtro «Fuera de plazo»**: quién llegó tarde. Queda marcado en el envío y en
   la matriz.
3. **Revisión → filtro «Antigüedad»**: envíos que llevan días sin resolverse.

---

## 4. Los catálogos, uno por uno

Servicios, Establecimientos, Series, Períodos y Feriados comparten la misma pantalla:
listado, **Nuevo**, y edición por fila. Todos tienen **Guardar** y **Cancelar**.

### Series

| Campo | Nota |
|---|---|
| Código | Hasta 5 caracteres. Único |
| Nombre | |
| Orden | El orden en que aparecen en la matriz |
| **Activa** | Si la desmarcas, deja de ofrecerse al subir |

> **Cuidado con desactivar una serie.** Desactivarla **no** la quita de la matriz. Si sigue
> exigida ahí, los establecimientos quedan con una serie que deben entregar y no pueden
> subir. **Desactiva y corrige la matriz en el mismo momento.** Es lo que pasó con la serie F
> (ver `analisis-simplificacion.md`).

### Períodos

| Campo | Nota |
|---|---|
| Año y mes | Únicos en combinación |
| Fecha límite manual | La excepción. Si está marcada, manda la fecha escrita |
| Fecha límite | Si no es manual, se calcula sola |
| Estado | Abierto, en revisión o cerrado |

### Plantillas

No es un catálogo más: guarda **la versión de plantilla que se espera** por año, mes y serie.
La validación compara el archivo contra eso. Si se sube la plantilla equivocada, los envíos
correctos empiezan a fallar.

---

## 5. Lo que solo puedes hacer tú

| Acción | Permiso |
|---|---|
| Gestionar catálogos | `catalogos.gestionar` |
| Importar catálogos | `catalogos.importar` |
| Gestionar usuarios | `usuarios.gestionar` |
| Gestionar períodos | `periodos.gestionar` |
| Ver la auditoría | `auditoria.ver` |
| **Reabrir** un envío | `envios.reabrir` |
| **Anular** un envío | `envios.anular` |

Nadie más tiene esos dos últimos. Son las acciones que rompen la trazabilidad, y por eso
están en una sola manos.

---

## 6. Lo que conviene saber antes de la demostración

1. **La campana es el único aviso.** No hay correo. Si alguien no entra, no se enteró.
2. **El cierre del período es manual.** Decirlo en voz alta evita la pregunta incómoda.
3. **El mes 10 está vencido desde el 06-11.** Demostrar con octubre es demostrar el camino
   del atraso. Es válido, pero hay que decirlo.
4. **La serie F está inactiva y todavía se exige en 4 establecimientos.** Mientras no se
   corrija, esos cuatro no pueden llegar a aprobación.
