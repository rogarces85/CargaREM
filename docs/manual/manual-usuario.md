# Manual de usuario

Portal de Cargas REM del Servicio de Salud Osorno.

## Antes de empezar

El portal **no reemplaza** al sistema de escritorio: lo reemplaza para el
circuito de entrega, revisión y carga de los archivos mensuales.

Lo que cambia: los archivos se suben por el navegador, se validan al momento y
el revisor los ve en una bandeja. Lo que no cambia: el archivo sigue siendo el
mismo Excel que entrega el Minsal.

---

## Encargado de establecimiento

### Entrar

`http://<servidor>/login` con el correo y la clave que le dio el administrador.
Si no tiene cuenta, no puede crearla: la crea el administrador.

### Crear el envío del mes

1. Menú **Envíos**.
2. En «Nuevo envío», elegir establecimiento y período.
3. **Crear envío**.

Solo se puede crear uno por establecimiento y período. Si ya existe, se abre el
que hay.

### Subir un archivo

En el detalle del envío:

1. Elegir la serie en el desplegable.
2. Elegir el archivo (`.xls`, `.xlsx` o `.xlsm`).
3. **Subir y validar**.

El sistema valida al momento y muestra el resultado. Si el archivo tiene errores,
el envío queda «con errores» y hay que corregirlo.

**Volver a subir no borra lo anterior**: crea una versión nueva y la anterior
queda en el historial. Es lo que permite ver qué se corrigió.

### Leer el reporte

Cada hallazgo tiene un código y un mensaje que dice qué corregir:

| Código | Qué significa |
|---|---|
| `ENV-001` | El archivo pesa más de lo permitido (2 MB) |
| `ENV-003` | El archivo no es un Excel de verdad, aunque se llame así |
| `ENV-005` | El nombre no sigue el formato código-serie-mes |
| `ENV-007` | El mes del nombre no es el del período |
| `ENV-010` | La serie de la cabecera no es la que se está subiendo |
| `ENV-016` | Falta una hoja obligatoria |
| `ENV-018` | Una prestación quedó sin datos (advertencia) |
| `ENV-020` | Un valor no es numérico (advertencia) |
| `ENV-025` | Falta el archivo de una serie que la matriz exige |
| `ENV-029` | Ese mismo archivo ya se subió |

Las **advertencias** no impiden enviar. Los **bloqueantes** sí.

### Enviar a revisión

Con todas las series exigidas subidas y sin bloqueantes, el botón **Enviar a
revisión** aparece en el detalle. Después de enviarlo, el envío queda bloqueado
para edición hasta que el revisor responda.

### Si lo observan

Llega un correo y el envío aparece «observado». Abrir el detalle muestra la
observación. Corregir el archivo y volver a subirlo: **la observación se
resuelve sola** si era de esa serie.

---

## Revisor del Servicio de Salud

### La bandeja

Menú **Revisión**. Aparecen los envíos de su Servicio de Salud, los más
antiguos primero.

Filtros: estado, establecimiento, año y antigüedad. El filtro de antigüedad
sirve para encontrar lo que lleva semanas esperando.

### Observar

Botón **Observar**:

1. Elegir la serie si el problema es de una en particular.
2. Escribir qué hay que corregir.

El envío vuelve al establecimiento. Si la observación era de una serie, se
resuelve sola cuando llegue la corrección.

### Aprobar

Botón **Aprobar**. Solo aparece cuando el envío se puede aprobar, y el servidor
vuelve a comprobarlo:

- que esté en revisión,
- que no tenga observaciones abiertas,
- que no tenga archivos con bloqueantes,
- que estén todas las series que la matriz exige.

Si algo falta, el mensaje dice qué.

### Confirmar la carga

Después de aprobar, **Confirmar carga** deja constancia de que los datos ya
están en el sistema institucional. Se puede anotar una referencia (número de
acta o folio): queda en el historial y es lo que permite rastrear después.

### La matriz de completitud

Menú **Completitud**: una tabla con los establecimientos en filas y las series
en columnas. Cada celda dice si esa serie llegó y en qué estado.

Un clic en una celda abre el envío correspondiente. Se puede exportar a CSV o
Excel.

---

## Administrador

Además de todo lo anterior:

| Dónde | Qué se hace |
|---|---|
| **Catálogos** | Importar un ZIP del catálogo y activarlo |
| **Servicios / Establecimientos / Series / Períodos** | Datos maestros |
| **Matriz de series** | Qué series exige cada establecimiento |
| **Plantillas** | Consulta de las hojas por serie y año |
| **Usuarios** | Altas, roles, desactivación |
| **Auditoría** | Todo lo que pasó, sin poder editarse |

### Reglas que el sistema impone

- No puede desactivarse a sí mismo.
- No puede quitarse el rol de administrador.
- No puede resetear su propia clave: se cambia desde su perfil.
- Un catálogo importado no se borra: se activa otro.
- Un envío no se borra: se anula, con motivo.

---

## Preguntas frecuentes

**Subí un archivo y el envío sigue «con errores».**
Quedan bloqueantes. Abrir el detalle y mirar la tabla de hallazgos.

**Dice «falta el archivo de la serie BS».**
La matriz exige esa serie para su establecimiento. Si no corresponde, el
administrador ajusta la matriz.

**«Ese mismo archivo ya se subió».**
El contenido es idéntico a una versión anterior. Si no cambió nada, no hace
falta subirlo.

**El correo no llegó.**
El aviso también queda en el portal. Y en desarrollo el correo se escribe en
`storage/logs/laravel-*.log`.

**Olvidé la clave.**
Usar «Recuperar contraseña» en el login. Si no llega el correo, el
administrador puede asignar una nueva.
