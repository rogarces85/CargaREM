# Informe Técnico Exhaustivo: ValidadorRem ("Sistema Cargas REM")
**Conclusión operativa para el equipo:** ValidadorRem es una herramienta de escritorio monousuario centrada en validar y consolidar archivos REM en bases Access por serie y año, con un fuerte acoplamiento a los servicios SOAP del MINSAL y a la versión de plantillas Excel. Para una reconstrucción web, los riesgos principales son la dependencia de Jet/ACE (32 bits), la falta de RBAC, la sobrescritura destructiva sin control de acceso, y los endpoints en HTTP de prueba. Los puntos P1 a P11 deben cerrarse antes de implementar.

**Alcance del análisis:** `ValidadorRem.exe` (PE32 x86, IL-only, .NET Framework 4.8), sus 24 recursos embebidos, sus metadatos (100 TypeDef, 1 975 MethodDef, 1 015 literales `#US`, 20 219 aristas de llamada), las bases Access (`.mdb`) en modo solo lectura y el `.config`. No se ejecutó la aplicación ni se capturó tráfico en runtime; las partes que requieren eso están marcadas como **[PENDIENTE DE VALIDACIÓN]**.

**Nota de contexto:** la carpeta `C:\Program Files (x86)\Minsal\Cargas Rem 2016\` también contiene un árbol de código fuente de **dnSpy** (`dnSpy\`). No forma parte de ValidadorRem.

---

**Nombre del sistema:** ValidadorRem (interfaz: "Sistema Cargas REM 3.12.000", según el literal del menú `Minsal`). Metadatos del ejecutable: `FileDescription`/`ProductName` = `VlidadorRem` (typo en el binario), `CompanyName` = Minsal, `Copyright` = 2009, `FileVersion` = 1.0.0.0. Despliegue ClickOnce en `ValidadorRem.application` (versión 1.0.0.1). Las versiones visibles en la UI son inconsistentes: "Carga Datos 4.0", "Cargar Datos 3.01.000", "Datos desde Servidor 3.01.000" y "Sistema Cargas REM 3.12.000".

**Objetivo principal:** Cargar, validar y consolidar localmente los archivos Excel mensuales del REM (Registro Estadístico Mensual, según la terminología del MINSAL chileno; el acrónimo no aparece literalmente en el binario) por establecimiento, mes, año y serie, dentro de bases Access por Servicio de Salud. También genera el archivo de envío MINSAL y mantiene catálogos y datos sincronizados con servicios web centrales. El perfil de usuario es un operador/analista de datos de un Servicio de Salud, que trabaja en una estación de escritorio sin login.

**Descripción general:** Es una aplicación de escritorio WinForms monousuario, sin autenticación ni roles. Su dominio es la estadística de salud pública (prestaciones por establecimiento, mes y serie). Persiste en archivos Access (Jet/ACE 32 bits) y depende de Microsoft Office/Excel y de SOAP ASMX alojados en `cargasrem.minsal.cl`. Su núcleo son 23 formularios y una capa de acceso a datos estática (`ValidadorRem.bases`).

---

## 1. ARQUITECTURA DE LA INFORMACIÓN Y MÓDULOS

### 1.1 Módulos principales

| # | Módulo | Clases (formularios/servicios) | Función observada |
|---|---|---|---|
| M1 | Arranque y configuración | `Principal` (shell con menú y 13 botones de barra), `primera`, `Cargando`, `Seteo`, `buscaActualizacion` | Verifica versión, selecciona el Servicio de Salud (SS) activo |
| M2 | Carga de datos | `CargaDatos` (carga individual), `CargaDatosMultiple` (carga de carpeta), `ActDBServ` ("Datos desde Servidor") | Lee Excel, valida, escribe en `datos` |
| M3 | Errores y control de carga | `LogErrorCargados`, `LogErrorMultiples`, `InformeControl` ("Hoja Control") | Registros de error y validación de hoja de control |
| M4 | Consulta e informes | `EstabCargados`, `Duplicidad`, `Prestacion`, `Decimales`, `Comparativos`, `ComparativoMes`, `Informe1` | Cobertura por mes, duplicados, comparativos, informes en Excel |
| M5 | Mantenimiento y exportación | `Eliminar`, `Optimiza`, `Export` (DBF), `Minsal` (archivo MINSAL) | Borrado, compactación Jet, generación de archivos |
| M6 | Sincronización | `Actualizador`, `ExtraeDatos.CargaSerieServ`, `ExtraeDatosC.CargaControlServ`, `LlenaSerie.WebService3`, `DatosSerie.CargaSerie`, `Datos.WebServiceDB`, `ContUp`/`TabUp.Optimiza_Up`, `ValidaVersionEjec.VersionEjecutable`, `version.WebService1` | Proxies SOAP hacia `cargasrem.minsal.cl` |
| — | Capa de datos (transversal) | `ValidadorRem.bases` (9 métodos) | Crea y copia bases, borra datos, consulta `Software` |
| — | Configuración | `ValidadorRem.My.MySettings` (15 métodos) | URLs de servicios web y `generacion` |

Estructura de bases de datos en la carpeta de instalación (ruta relativa al ejecutable):
- `Global.mdb` (catálogos, 422 MB), `Trans.mdb` (historial y `SetServicio`), `Temporal.mdb`, `TemporalIncorrectos.mdb`, `TemporalControl.mdb`, `TemporalInforme1.mdb` (generado).
- `\Datos\DatosSERIE{A|BS|BM|D|P|F}{año}.mdb` (datos) y `\Control\ControlSERIE{serie}{año}.mdb` (control).
- `MASTER.mdb`, `MasterC.mdb`, `MasterDB.mdb` (plantillas para el archivo MINSAL).

### 1.2 Mapa de vistas (formularios)

Como es WinForms, no hay rutas. El equivalente de "ruta" es la opción de menú o botón de barra en `Principal`. La tabla usa un identificador de vista propuesto para una eventual migración web.

| Vista propuesta | Formulario | Acceso en la aplicación | Tipo |
|---|---|---|---|
| `/` (shell) | `Principal` | Ventana principal con `MenuStrip1` y `ToolStrip` | Contenedor |
| `/setup/ss` | `Seteo` | Menú "Servicio de Salud → Seleccionar S.S." | Formulario |
| `/carga/individual` | `CargaDatos` | Menú "Ingresar" (`Ingreso_excel`) | Wizard de carga |
| `/carga/multiple` | `CargaDatosMultiple` | Menú "Ingresar Múltiple" | Carga por carpeta |
| `/datos/servidor` | `ActDBServ` | "Datos desde Servidor" (acceso no confirmado) [PENDIENTE] | Descarga desde WS |
| `/errores/carga` | `LogErrorCargados` | Menú "Errores / Log Error" | Tabla + exportar |
| `/errores/multiple` | `LogErrorMultiples` | Menú "Log Error Carga Múltiple" | Tabla + exportar |
| `/control/hoja` | `InformeControl` | Menú "Hoja Control" / "Informe Hoja Control" | Tabla + reporte |
| `/establecimientos/cargados` | `EstabCargados` | Menú "Establecimientos cargados" | Matriz mensual |
| `/duplicidad` | `Duplicidad` | Menú "Duplicidad de Registros" | Tabla de duplicados |
| `/prestaciones` | `Prestacion` | Menú "Prestaciones" (sub-opciones) | Búsqueda + exportar |
| `/glosa` | `Prestacion` (probable) [PENDIENTE] | Menú "Glosa de prestaciones" | Catálogo |
| `/decimales` | `Decimales` | Menú "Decimales" | Configuración [PENDIENTE] |
| `/comparativos/anio` | `Comparativos` | Menú "Comparación de prestaciones / Por año" | Comparación |
| `/comparativos/mes` | `ComparativoMes` | Menú "Comparación por Mes" | Comparación |
| `/informes/1` | `Informe1` | Menú de informes (sin confirmar) [PENDIENTE] | Reporte INF1 |
| `/mantenimiento/borrar` | `Eliminar` | Menú "Borrar datos" | Borrado |
| `/mantenimiento/optimizar` | `Optimiza` | Menú "Optimizar bases" | Acción batch |
| `/export/dbf` | `Export` | Menú "Exportar a DBF" (`generar_dbf`) | Exportación |
| `/export/minsal` | `Minsal` | Menú "Generar archivo MINSAL" (`Archivo_minsal`) | Exportación |
| `/actualizacion` | `Actualizador` | Menú "Actualiza sistema / series" (probable) [PENDIENTE] | Descarga |
| `/actualizacion/buscar` | `buscaActualizacion` | Arranque y "Buscando actualizaciones" | Splash |
| `/splash` | `Cargando`, `primera` | Arranque | Splash/primer uso |

---

## 2. ESPECIFICACIÓN DE FUNCIONALIDADES (POR MÓDULO)

### Reglas transversales

- **RN-G1 (SS obligatorio).** Casi todas las operaciones exigen un Servicio de Salud seleccionado. Mensaje literal: *"Por favor debe Elegir Servicio de Salud desde Menu 'Servicio de Salud --> Seleccionar S.S.', para poder trabajar"*. La validación exacta por operación está **[PENDIENTE DE VALIDACIÓN]**.
- **RN-G2 (un SS por instalación).** La tabla `Trans.SetServicio` guarda un único registro (`Idservicio`, `Nservicio`). Si ya existe: *"Disculpe, Ud. ya Selecciono un Servicio de Salud"*.
- **RN-G3 (partición por serie y año).** Los datos se separan en archivos `DatosSERIE{serie}{año}.mdb`; el control, en `ControlSERIE{serie}{año}.mdb`. Series observadas en el sistema actual: `A`, `BS`, `BM`, `D`, `P`, `F` (menú y `LlenaSerie`/`CargaSerie`). Significado de dominio de cada serie **[PENDIENTE DE VALIDACIÓN]**. **Serie local pendiente:** `SAX` (Anexo, Servicio de Salud Osorno); no existe en el sistema actual y su documentación se mantiene en un sistema aparte `[PENDIENTE]`.
- **RN-G4 (sin control de acceso).** No existe tipo de login/usuario/perfil. Las cadenas de autenticación encontradas corresponden a `AuthenticationMode` de ASP.NET y a nombres de controles. Ver matriz RBAC en 2.9.

### M1. Arranque y configuración

**Funciones**
- Verificación de versión al iniciar (`Principal.Form1_Load`): borra `Actualizado.txt` si procede, consulta la versión del servicio (`version.WebService1.version`), compara con la tabla `Software` (`select version from software`) y consulta `VersionEjecutable.VersionEjec`.
- Selección de SS (`Seteo`): carga `mpServicio` (`SELECT idservicio & ' - ' & nservicio ... FROM mpservicio ORDER BY orden`) y registra la selección en `SetServicio`.

**Reglas de negocio**
- **RN-M1-1.** Si la versión del servicio difiere de la local, `Principal.Form1_Load` invoca `Actualizacion.actualizar`. Mensaje: *"Existen Actualizaciones para la BD del programa, dirijase al modulo de 'Actualizar'"*. La condición exacta (bloqueante o no) **[PENDIENTE DE VALIDACIÓN]**.
- **RN-M1-2.** Un SS ya elegido no puede reemplazarse desde `Seteo` sin borrar `SetServicio` (mensaje de bloqueo). Comportamiento exacto **[PENDIENTE DE VALIDACIÓN]**.

### M2. Carga de datos

**Funciones**
- **Carga individual** (`CargaDatos`): elige `Serie`, `Año`, `Mes` y archivo/carpeta; previsualiza en `DataGridView`, valida y persiste (rutina principal `Button4_Click`, 6 717 bytes IL).
- **Carga múltiple** (`CargaDatosMultiple`): procesa todos los Excel de una carpeta con barra de progreso (`Archivo Actual`, `Archivos Procesados`, `Total Archivos`) y tabla de resultados (`Registros Correctos`, `Registros Incorrectos`, `Mostrar Errores`).
- **Datos desde servidor** (`ActDBServ`): descarga desde el WS y carga la base local.

**Reglas de negocio**
- **RN-M2-1 (extensiones).** Entradas admitidas: `.xls`, `.xlsx`, `.xlsm` (filtros `Archivos Excel|*.xls;*.xlsx;*.xlsm`). `.xls` se lee con `Microsoft.ACE.OLEDB.12.0` (`Excel 8.0;HDR=Yes;IMEX=1`); `.xlsx`/`.xlsm` con NPOI (`Carga_NombreNPOI`, `DevuelveValorNPOI`).
- **RN-M2-2 (validación de cabecera).** Cada archivo debe coincidir con la serie, año, mes, versión y código de establecimiento seleccionados. Mensajes exactos:
  - *"Serie no Corresponde: Por favor verifique la serie del Archivo"*
  - *"Año no Corresponde: Por favor verifique año del Archivo"*
  - *"Mes no coincide: Por Favor Verifique que el texto y el número del mes coincidan"*
  - *"Establecimiento no Corresponde con Servicio: Por favor verifique código de Establecimiento del Archivo"*
  - *"Versión No Corresponde a Mes: por Favor Verifique la Versión con el Mes de Ingreso de Archivo"*
  - *"No corresponde a archivo Excel esperado"*
- **RN-M2-3 (versión de plantilla).** La tabla `version` (`ano`, `mes`, `Serie`, `SerieExcel`, `version`; 939 filas en `Global.mdb`) define qué versión de plantilla Excel corresponde a cada combinación.
- **RN-M2-4 (estructura por hoja).** La tabla `Thojas` (`ano`, `mes`, `Serie`, `Hoja`, `Rango`; 1 129 filas) define el rango de cada hoja. La tabla `Diccionario` (185 526 filas) define, por prestación (`CodigoPrestacion`) y `Hoja`, la línea, el inicio, el fin, la posición y el `tipoDato` de cada celda. La tabla `Excep` (5 664 filas) y `colexcep` (2 977 filas) definen columnas no aplicables.
- **RN-M2-5 (sobrescritura).** Al cargar un establecimiento/mes/año ya presente se borran los datos previos: `DELETE FROM datos WHERE ...`, `DELETE FROM LogError ...`, `DELETE FROM HistCarga ...`. Confirmación previa: *"ATENCION: Se sobreescribirá la información relacionada a los archivos a cargar si es que ya se encuentran en la base de datos. ¿Desea continuar?"*.
- **RN-M2-6 (carga completa).** `ActDBServ` avisa: *"Se Borraran todos los Datos que actualmente tiene su Base de Datos y se cargaran los nuevos. Desea Continuar"*. Es destructivo sobre toda la base local.
- **RN-M2-7 (aceptación parcial).** Los registros válidos van a `datos`; los inválidos se registran en `INCORRECTO` (`TemporalIncorrectos.mdb`) y en `LogError` (`Trans.mdb`). Se inserta un registro en `HistCarga (IdEstablecimiento, Mes, Ano, Serie)`.
- **RN-M2-8 (sin datos).** *"No hay Datos a Ingresar"* cuando no hay filas válidas.
- **RN-M2-9 (limpieza de Excel).** La carga elimina procesos `EXCEL.exe` creados por la aplicación (`KillAllAddedExcelProcesses`, `GetAllExcelProcesses`).
- **RN-M2-10 (códigos con guion).** Los establecimientos con `IdEstablecimiento` que contiene `-` se listan aparte (`like '%-%'` vs `not like '%-%'`).

### M3. Errores y control

**Funciones**
- Listado de errores por establecimiento y año (`select * from LogError where ano= ...`) y exportación a Excel (`LogErrorMultiples.btnExportaExcel_Click`).
- Hoja de control (`InformeControl`): genera registros en `TemporalControl.mdb` (tabla `Error`) y calcula "Puntos" (`InformeControl.Puntos`, método observado; criterio **[PENDIENTE DE VALIDACIÓN]**).

**Reglas**
- **RN-M3-1.** `LogError` se purga por establecimiento, mes, año y serie antes de una recarga (ver RN-M2-5).
- **RN-M3-2.** Los errores de tipo de dato se registran en `LogError` con `dato`, `hoja`, `linea` y `mes` (`LogError` en `Trans.mdb`, 0 filas en la copia leída).

### M4. Consulta e informes

**Funciones**
- **Establecimientos cargados** (`EstabCargados`): matriz mensual (`Ene`…`Dic`) con `'X'` si existe dato; usa `Temporal.mdb` (tabla `cargados`, 602 filas en la copia).
- **Duplicidad** (`Duplicidad.buscaduplicado`): `SELECT count(mes) ... FROM datos GROUP BY mes, idestablecimiento, codigoprestacion HAVING count(mes)>1`.
- **Prestación** (`Prestacion`): búsqueda por prestación/año/serie, exportación a Excel (`Microsoft.Office.Interop.Excel`).
- **Comparativos**: por año (`Comparativos`, selección de `ListView` con `ItemCheck`) y por mes (`ComparativoMes`).
- **Informe 1** (`Informe1`): genera la tabla `INF1` en `TemporalInforme1.mdb` (`CREATE TABLE INF1 ([mes] TEXT(20),[codigo] TEXT(20),[glosa] TEXT(254)...)`) y lo exporta.

**Reglas**
- **RN-M4-1.** La matriz mensual marca un mes como cargado si existe al menos un registro en `datos` para ese establecimiento y mes.
- **RN-M4-2.** Si no hay duplicados: *"No se han encontrado datos duplicados"*.
- **RN-M4-3.** Sin base: *"La Base no ha sido creada o no ha sido descargada"*. Sin selección: *"Por Favor Seleccionar Año"* / *"Por Favor Seleccionar Serie"* / *"Debe seleccionar un año"* / *"Debe seleccionar una serie"*.

### M5. Mantenimiento y exportación

**Funciones**
- **Borrar datos** (`Eliminar`): selecciona año, mes, serie y establecimiento. Ejecuta `bases.Elimina_Historico` y `bases.borra_datos`.
- **Optimizar bases** (`Optimiza`): para cada `.mdb` de `Datos` y `Global.mdb`, ejecuta `JRO.IJetEngine.CompactDatabase` a un temporal `\~...`, borra el original y mueve el compactado.
- **Exportar a DBF** (`Export`): `SELECT * INTO [dBase III; DATABASE=...] FROM Datos WHERE mes IN (...) AND ano = ...`. Filtro `DBF|*.DBF`.
- **Archivo MINSAL** (`Minsal`): copia `MASTER.mdb` a un temporal, inserta `Datos` y `DatosC` desde los `.mdb` de cada serie para el año, escribe `registros` (`Ano`, `CantReg`, `Fecha`, `idservicio`, `Mes`, `Serie`), comprime con `GZipStream` y elimina el temporal.

**Reglas**
- **RN-M5-1 (borrado en cascada lógica).** Confirmación: *"Esta seguro de Eliminar el establecimiento: ..., Mes: ..., Año: ..., Serie: ..."*. Éxito: *"Se han eliminado los datos con exito"*.
- **RN-M5-2 (exportación).** Sin datos: *"No Existen datos para este año y serie."*. Sin ruta: *"Por Favor Ingrese Ruta de Archivo"*. Sin mes: *"Por Favor, Debe elegir Mes"*. Éxito: *"Se ha generado el archivo Exitosamente"*.
- **RN-M5-3 (compactación).** Requiere que los `.mdb` no estén bloqueados. Durante el análisis, varios `.mdb` estaban bloqueados por otro proceso (*"No se pudo bloquear el archivo"*).
- **RN-M5-4 (MINSAL).** Config `generacion` = `False`. Significado y efecto en la generación **[PENDIENTE DE VALIDACIÓN]**. Formato final del archivo (contenedor, extensión, cabecera) **[PENDIENTE DE VALIDACIÓN]**.

### M6. Sincronización y actualización

**Funciones**
- **Actualizar sistema y catálogos** (`Actualizador.Button1_Click` → `Actualizacion.actualizar`): descarga y actualiza `Global.mdb` en este orden (mensajes): Establecimientos, Servicios, Diccionarios, Excepciones, Columnas Excepciones, Hojas, Versiones; al final `Update Software set version=... where nombre='CargaRem'`. Mensajes: *"Descargando Actualizaciones..."*, *"Finalizado Con Exito !!"*, *"Ud.Ya Tiene la Última versión"*.
- **Datos desde servidor** (`ActDBServ`): consume `DatosSerie`/`DatosControl` (firma `(string, int, int)`) y `crea_base`.
- **Series** (`LlenaSerie.WebService3`, `DatosSerie.CargaSerie`): operaciones `serieA`, `serieBS`, `serieBM`, `serieD`, `serieP`, `serieF` (firma `(string)`).
- **Optimización remota** (`ContUp`/`TabUp.Optimiza_Up`): `Datos_Up` y `Contador_Up`, con `CancelAsync`.

**Reglas**
- **RN-M6-1.** La versión local se guarda en `Software.Version` (1 fila, `Nombre='CargaRem'`). Actualización completa → la versión se reescribe al final.
- **RN-M6-2.** La descarga de catálogos reemplaza el contenido de tablas de `Global.mdb` (`Delete * From mpestablecimiento`, `Delete * From diccionario`, `Delete * From Excep`, `Delete * From ColExcep`, `Delete * From thojas`, `Delete * From version`, `Delete * From mpservicio`). Es un reemplazo total, no incremental.
- **RN-M6-3.** Versión en servicio local: `"Versión Actual : 2.0 / Última Versión:"` (etiqueta en `Actualizador`). Coherencia con `Software.Version` [PENDIENTE DE VALIDACIÓN].

### 2.9 Matriz RBAC

**No existe RBAC.** Cualquier usuario del equipo puede ejecutar todas las operaciones, incluidas las destructivas. El único control es el ACL del sistema de archivos sobre `C:\Program Files (x86)\Minsal\Cargas Rem 2016\` y los bloqueos de Jet/ACE.

| Operación | Destructiva | Confirmación en UI | Protección efectiva |
|---|---|---|---|
| Seleccionar SS (`Seteo`) | No | No | Ninguna |
| Carga individual / múltiple | Sí (sobrescribe) | Sí (ATENCION...) | Solo confirmación |
| Datos desde servidor (`ActDBServ`) | Sí (borra toda la base) | Sí (Se Borraran todos los Datos...) | Solo confirmación |
| Eliminar datos (`Eliminar`) | Sí | Sí (Esta seguro de Eliminar...) | Solo confirmación |
| Optimizar bases (`Optimiza`) | Reescribe archivos | No observada | Ninguna |
| Actualizar catálogos (`Actualizador`) | Sí (reemplaza tablas) | No observada | Ninguna |
| Exportar DBF / MINSAL | No | No | Ninguna |
| Consultas e informes | No | No | Ninguna |

**Requisito para la migración:** si se rehace como web, debe definirse con negocio qué perfiles existen (ej.: Operador, Revisor, Administrador) y cuáles operaciones quedan limitadas. **[PENDIENTE DE VALIDACIÓN de negocio]**.

---

### 2.10 Módulo de envío de estadísticas por establecimiento (NUEVO)

**Propósito.** Reemplazar el envío por correo electrónico de los archivos REM por series. Cada encargado de estadística de un establecimiento sube sus archivos al portal, recibe validación inmediata y sigue el estado de su envío. La Unidad de Estadísticas revisa, observa, aprueba y carga los datos al sistema de datos.

**Proceso actual (referencia).** El establecimiento envía por correo uno o más archivos por serie (ej.: `123100A09.xlsm`, `123100BS09.xlsx`, `123100SAX09.xlsm`) más un reporte de validación (`Validacion_….xlsx`). La Unidad de Estadísticas revisa manualmente, devuelve observaciones por correo y luego carga cada archivo a CARGAS REM.

**Convención de nombre observada.** `{código de establecimiento}{serie}{mes}` (ej.: `123100A09` = código 123100, serie A, mes 09). El año no está en el nombre; se informa en el formulario de envío.

**Funciones:**
- F-ENV-1: Subir uno o más archivos (`.xls`, `.xlsx`, `.xlsm`) indicando año, mes y serie.
- F-ENV-2: Validación inmediata con las reglas de RN-M2-1 a RN-M2-4 (extensión, serie, año, mes, versión de plantilla, código de establecimiento, estructura de hojas).
- F-ENV-3: Descarga del reporte de validación generado por el sistema.
- F-ENV-4: Lista de completitud por mes y establecimiento (series entregadas y faltantes).
- F-ENV-5: Reenvío o corrección de un archivo, con historial de versiones.
- F-ENV-6: Bandeja de revisión para la Unidad de Estadísticas por SS y establecimiento.
- F-ENV-7: Observaciones por serie visibles para el establecimiento.
- F-ENV-8: Aprobación de envíos y carga al sistema de datos.
- F-ENV-9: Auditoría de quién subió, aprobó u observó, con fecha y versión.

**Estados del envío:** `Recibido → Validado | Con errores → En revisión → Observado → Aprobado → Cargado`. Un envío `Observado` vuelve al establecimiento para corrección; un `Aprobado` solo puede reemplazarse según la regla de corrección que se defina `[PENDIENTE]`.

**Roles (propuestos, sujetos a definición):**

| Rol | Puede |
|---|---|
| Encargado de establecimiento | Subir, reenviar, ver sus observaciones y su completitud |
| Revisor (Unidad de Estadísticas SS) | Ver la bandeja, observar, aprobar, cargar |
| Administrador | Gestionar usuarios, establecimientos, series y auditoría |

**Reglas de negocio:**
- RN-ENV-1: Un encargado solo ve y envía archivos de su establecimiento.
- RN-ENV-2: Un archivo con errores bloqueantes no puede pasar a `En revisión`; los errores no bloqueantes se registran como advertencia.
- RN-ENV-3: Todo cambio de estado queda registrado en auditoría.
- RN-ENV-4: Las reglas de corrección después de `Aprobado` y los plazos de cierre mensual quedan `[PENDIENTE DE VALIDACIÓN de negocio]`.
- RN-ENV-5: Series obligatorias por mes y establecimiento: `[PENDIENTE DE VALIDACIÓN]`. Incluye la serie local SAX, documentada en un sistema aparte.

**Requisitos no funcionales:**
- Autenticación de encargados y revisores (fuente de identidad `[PENDIENTE]`).
- Antivirus y límite de tamaño antes de procesar archivos; las macros de `.xlsm` no se ejecutan en el servidor.
- Cifrado en tránsito y control de acceso por establecimiento.
- Retención de los archivos originales.
- Notificaciones de cambio de estado (canal `[PENDIENTE]`: correo o portal).

**Dependencia técnica:** el motor de validación actual está en código de escritorio (ver P3). Para validar en el portal hay que reutilizar sus reglas o reimplementarlas; es el principal riesgo técnico del módulo.

**Pendientes del módulo:** stack tecnológico, fuente de identidad, flujo de aprobación, reglas de corrección, plazos de cierre, infraestructura (intranet/internet), y la documentación de la serie SAX (sistema aparte).

---

## 3. FLUJOS DE USUARIO (USER FLOWS) Y PROCESOS

### F1. Arranque
1. El ejecutable (`STAThreadAttribute` en el método de entrada `0x06000001`) muestra `Cargando` (splash) y `buscaActualizacion` ("Buscando actualizaciones, espere por favor.....").
2. `Principal.Form1_Load`: `File.Exists` del archivo de actualización (`\Actualizado.txt`) → si existe, `Actualizacion.actualizar` y `File.Delete`.
3. Consulta `version.WebService1.version()` (string) y `VersionEjecutable.VersionEjec()`.
4. Lee `select version from software` en `Global.mdb`.
5. Compara versiones con `Operators.CompareString`. Si difieren: `MsgBox` *"Existen Actualizaciones para la BD del programa, dirijase al modulo de 'Actualizar'"*.
6. Muestra `Principal`. Si no hay SS seleccionado, las operaciones quedan bloqueadas con el mensaje de RN-G1.

**Errores:**
- Sin conexión al WS: `MsgBox` "Error de conexión" / "Error de Conexion" (literales observados; disparador exacto **[PENDIENTE DE VALIDACIÓN]**).
- Excepción no controlada: `StreamWriter` escribe en `\DetalleExcepcion.txt` y muestra *"Ha ocurrido una excepción no controlada"*.
- Códigos HTTP (401/403/404/500): el código no los maneja explícitamente. Se observan los literales de error de UI; el comportamiento real ante cada código requiere **[PENDIENTE DE VALIDACIÓN con runtime/proxy HTTP]**.
- Reintentos: no se observó bucle de reintento en las llamadas a WS **[PENDIENTE DE VALIDACIÓN]**.

### F2. Selección de Servicio de Salud
1. Menú *Servicio de Salud → Seleccionar S.S.* abre `Seteo`.
2. Consulta `SELECT count(Nservicio) ... FROM SetServicio`.
3. Si ya hay uno: *"Disculpe, Ud. ya Selecciono un Servicio de Salud"*.
4. Si no: carga la lista `mpServicio`, el usuario elige y se inserta en `SetServicio(Idservicio, Nservicio)`.
5. Mensaje de éxito: *"Los Datos Fueron Ingresados Satisfactoriamente"*.

### F3. Carga individual (`CargaDatos`)
1. Selección de `Serie`, `Año` y `Mes`. Sin selección: *"Debe Seleccionar Opciones"* o *"Seleccione Serie de los archivos a cargar."* (según el paso).
2. Selección de archivo/carpeta (`Busqueda de Archivo`). Ruta inválida: *"Carpeta Inválida"* o *"Ingrese la ruta de los archivos a cargar."*.
3. Validación de extensión (RN-M2-1) y de cabecera (RN-M2-2). Error: *"Revisar archivo, no se pudo leer correctamente."* o *"No corresponde a archivo Excel esperado."*
4. Lectura por hoja según `Thojas` y `Diccionario` (RN-M2-4). Excepciones por `Excep` y `colexcep`.
5. Confirmación de sobrescritura (*"ATENCION: ... ¿Desea continuar?"*). Si se acepta: `DELETE` previos (RN-M2-5).
6. Escritura en `datos` y registro en `HistCarga`. Errores en `LogError` / `INCORRECTO`.
7. Cierre: *"Proceso Finalizado con Éxito"* o *"Proceso Finalizado con Exito !!"* (literales distintos; **[PENDIENTE DE VALIDACIÓN]** cuál se usa en cada caso). Si hay fallos: *"Ejecutado con error"*.
8. Cancelación: *"Operacion Cancelada"*.

### F4. Carga múltiple (`CargaDatosMultiple`)
1. Carpeta → lista de `.xls/.xlsx/.xlsm`. Sin carpeta: *"Debe Ingresar Ruta de Archivo"*.
2. Por archivo: `ProcesaArchivo` → `CargaExcel` → `verifica_version`, `comprueba_estab`, `Busca_Hoja`, `Busca_Dato`, `CargaABase`.
3. Progreso: `ProgressBar1` y etiquetas `Archivo Actual` / `Archivos Procesados` / `Total Archivos`.
4. Resultado: `Registros Correctos` / `Registros Incorrectos`. Opción *"Mostrar Errores"* → `dgvRegistros`.
5. Final: *"Se han terminado de procesar los archivos."* y *"Carga Visual Completada"*.

**Errores:** *"Carpeta Inválida"*, *"CRITICAL ERROR : Exception caught while converting dataGridView to DataSet"*, *"Excepción"*. El flujo continúa con el siguiente archivo (comportamiento **[PENDIENTE DE VALIDACIÓN]**).

### F5. Revisión de errores (`LogErrorCargados` / `LogErrorMultiples`)
1. Filtro por año, serie, establecimiento. Consulta `select * from LogError where ano=...`.
2. Exportación: `Exportar Excel` / `Exportar a Excel` (Interop). Sin datos: *"No hay Datos"*.

### F6. Eliminación (`Eliminar`)
1. Año → mes → serie → establecimiento (combos `Cb_ano`, `Cb_mes`, `Cb_serie`, `Cb_estable`).
2. *"Esta seguro de Eliminar el establecimiento: ..."* (confirmación).
3. Ejecuta `Elimina_Historico` (`HistCarga`) y `borra_datos` (`datos`), más `LogError`.
4. *"Se han eliminado los datos con exito"* o *"Error de Seleccion"*.

### F7. Exportación DBF y archivo MINSAL
1. `Export`: elige año y meses (`Mes 1`…`Mes 12`), ruta del `.dbf` (`DBF|*.DBF`), ejecuta `SELECT ... INTO [dBase III...]`. Éxito: *"Generación de Archivo Exitoso"*.
2. `Minsal`: elige año/mes, copia `MASTER.mdb` a temporal, inserta desde los `.mdb` de cada serie (`INSERT INTO [Datos] SELECT * FROM [MS Access;DATABASE=...]`), escribe `registros`, comprime con GZip (`comprimirarchivo2`) y elimina el temporal. Éxito: *"Se ha generado el archivo Exitosamente"*.
3. Errores: *"No Existen datos para este año y serie."*, *"Por Favor, Ingrese Año"*, *"Por Favor, Ingrese Serie"*.

### F8. Optimización (`Optimiza`)
1. Lista `*.mdb` de `Datos` y `Global.mdb`. Mensajes: *"Optimizando DB : ..."*, *"Actualizando......"*, *".....Ok"*, *"Optimización satisfactoria"*.
2. Compactación con `JRO`. Si el archivo está bloqueado: fallo de `CompactDatabase` (manejo **[PENDIENTE DE VALIDACIÓN]**).

### F9. Actualizaciones
1. `Actualizador`: *"Buscando..."* → *"Descargando Actualizaciones..."* → reescritura de catálogos (orden en M6) → *"Finalizado Con Exito !!"*.
2. Si la versión coincide: *"Ud.Ya Tiene la Última versión"*.
3. Progreso en línea: `"Progreso en Linea"`, `"Proceso : "`.
4. Cancelación: `CancelAsync` en los proxies.

---

## 4. REQUERIMIENTOS TÉCNICOS (STACK Y ESTRATEGIA)

### 4.1 Stack actual (evidencia)
- **Runtime:** .NET Framework 4.8, IL-only, `TargetFramework` `.NETFramework,Version=v4.8`, **x86** (`32BITREQUIRED`).
- **UI:** WinForms (`System.Windows.Forms`), `MessageBox`/`MsgBox` (VB.NET runtime), `DataGridView`, `ListView`, `ProgressBar`.
- **Datos:** ADO.NET OleDb (`System.Data.OleDb`), proveedor `Microsoft.ACE.OLEDB.12.0` (Access y Excel) y `Microsoft.Jet.OLEDB.4.0` (en `.config`, obsoleto).
- **Excel:** `Microsoft.Office.Interop.Excel` 14.0 (exportes) + `NPOI` 2.3 (lectura `.xlsx`).
- **Compactación:** `Interop.JRO.dll` (`JRO.IJetEngine.CompactDatabase`).
- **Compresión:** `System.IO.Compression.GZipStream` (archivo MINSAL). `ICSharpCode.SharpZipLib.dll` está en la carpeta pero **no** figura en las referencias del ensamblado.
- **Servicios:** SOAP ASMX (`System.Web.Services`, `SoapHttpClientProtocol`).
- **Despliegue:** ClickOnce (`ValidadorRem.application`), `supportedRuntime` v4.0 / SKU `.NETFramework,Version=v4.8`.
- **Advertencia:** `ValidadorRem.exe.config` apunta a `|DataDirectory|\Rem.mdb`, archivo que no existe en la instalación. Las rutas reales se construyen en código (`\Datos`, `\Control`, `\Global.mdb`, `\Trans.mdb`).

### 4.2 Estrategia para el equipo frontend/backend (si se reconstruye como web)

El sistema actual es escritorio. Esta sección describe el destino recomendado, no el estado actual.

**Gestión de estado — qué usar y por qué**

| Tipo de estado | Herramienta | Justificación en este sistema |
|---|---|---|
| Campos de formulario, selección de grilla, estado de un diálogo | `useState` / RHF local | Son efímeros y no se comparten entre vistas. Ej.: serie/año/mes en `CargaDatos`. |
| Servicio de Salud activo, configuración de sesión | Context API (`ServicioSaludProvider`) | Cambia poco (una vez por instalación, RN-G2) y lo leen muchas vistas. Basta con Context. |
| Trabajo en curso (carga múltiple: archivo actual, procesados, errores) | Zustand (store de jobs) | Estado compartido entre la vista de carga y la de errores, que se actualiza en cada archivo. Zustand evita el ruido de Redux. |
| Datos del servidor (catálogos, datos, versiones, duplicados) | TanStack Query | Es estado de servidor: caché, invalidación tras cargar/eliminar, reintentos controlados. |
| Redux Toolkit | **No recomendado** | La complejidad observada no justifica reducers/acciones. No hay transacciones de estado complejas en cliente. |

**Enrutamiento (React Router v6/v7 recomendado)**

Estructura propuesta (no hay roles hoy; la protección es por prerrequisito de negocio):

```text
/                       → redirect a /setup/ss si no hay SS
/setup/ss               → pública (sin SS)         ← Seteo
/app                    → protegida: requiere SS   ← Guard: servicioSeleccionado != null
  /carga/individual     ← CargaDatos
  /carga/multiple       ← CargaDatosMultiple
  /errores/carga        ← LogErrorCargados
  /errores/multiple     ← LogErrorMultiples
  /control/hoja         ← InformeControl
  /establecimientos     ← EstabCargados
  /duplicidad           ← Duplicidad
  /prestaciones         ← Prestacion
  /comparativos/anio    ← Comparativos
  /comparativos/mes     ← ComparativoMes
  /informes/1           ← Informe1
  /export/dbf           ← Export
  /export/minsal        ← Minsal
  /admin                ← Guard: rol admin (si se define, RN de 2.9)
    /mantenimiento/borrar    ← Eliminar (confirmación fuerte)
    /mantenimiento/optimizar ← Optimiza
    /actualizacion           ← Actualizador
```

**Consumo de API (Axios + TanStack Query)**
- El WS es SOAP/ASMX; el navegador no puede consumirlo directamente de forma práctica. Se requiere un **backend** (BFF) que exponga REST/JSON y encapsule los SOAP.
- Lecturas (`GET`: versión, catálogos, `mpServicio`, `Diccionario`, duplicados, matriz mensual): `staleTime` alto para catálogos (horas), reintentos 2 con backoff exponencial solo para lecturas idempotentes.
- Escrituras (carga, eliminación, optimización, actualización): **sin reintento automático**. Se reintenta solo manualmente tras confirmar el estado.
- Operaciones largas (carga múltiple, descargas): job asíncrono con `jobId` y progreso por polling o SSE. Cancelación con `AbortController` (equivalente a `CancelAsync`).
- Invalidación: tras `cargar`/`eliminar`, invalidar `['establecimientos-cargados', ...]`, `['duplicados', ...]`, `['registros', ...]`.
- Manejo de errores por código HTTP: 401/403 → redirigir a login (futuro); 409 → conflicto de sobrescritura (mostrar confirmación); 5xx/timeout → mensaje de conexión (equivalente a "Error de conexión").

**Componentes clave a construir**
1. `ServerDataTable`: paginación, orden y filtro del lado del servidor (`Datos` y `Diccionario` superan 15 000 y 185 000 filas, respectivamente).
2. `ConfirmDestructiveModal`: para `Eliminar`, sobrescritura de carga, `ActDBServ` y actualización de catálogos. Requiere escribir el nombre del establecimiento o el año para confirmar en acciones de borrado masivo.
3. `ExcelDropzone`: admite `.xls`, `.xlsx`, `.xlsm`, valida extensión y tamaño en cliente.
4. `ProcessProgress` (multi-archivo): archivo actual, procesados, total, correctos, incorrectos.
5. Formularios controlados con **React Hook Form + Zod**: `ServicioSaludSelect`, `PeriodoForm` (año, mes, serie), `EstablecimientoSelect` (con filtro de códigos con `-`).
6. `MonthStatusMatrix`: matriz Ene–Dic por establecimiento (`'X'` = cargado).
7. `ExportButton` (Excel/CSV/DBF/MINSAL según endpoint).
8. `StatusBanner`: aviso de versión desactualizada (RN-M1-1).

---

## 5. REQUISITOS DE INTEGRACIÓN Y DATOS

### 5.1 Estructura conceptual de datos (interfaces TypeScript propuestas)

Tipos derivados del esquema real (tipos OLE DB: `2`=SmallInt, `3`=Long, `5`=Double, `7`=DateTime, `11`=Bool, `130`=WChar).

```ts
// Entidad 1: Establecimiento (mpEstablecimiento en Global.mdb; 3 207 filas)
export interface Establecimiento {
  idEstablecimiento: string;     // WChar. Puede contener '-' (RN-M2-10)
  nEstablecimiento: string;
  idServicio: number;            // Long (FK a Servicio)
  idComuna: number;              // Long
  idDependencia: number;         // Long
  idTipo: string;                // WChar
  direccion?: string | null;
  telefono?: string | null;
  fax?: string | null;
  propiedad?: string | null;
  elimId: boolean;               // ElimID (SmallInt/Bool); soft-delete
}

// Entidad 2: RegistroDato (Datos en DatosSERIE{serie}{año}.mdb; 15 060 filas en 2026 P)
export interface RegistroDato {
  ano: number;                   // Long
  mes: number;                   // Long (1-12)
  idServicio: number;            // Long en DatosSERIE; WChar en MASTER.mdb (inconsistencia, ver 5.3)
  idEstablecimiento: string;     // WChar
  codigoPrestacion: string;      // WChar
  col01: number | null;          // Double
  // ... col02 … col50 (Double). Semántica en Diccionario (Hoja, linea, Inicio, Fin, Posicion)
  fechaIngreso: string;          // DateTime (ISO 8601)
}

// Entidad 3: Prestacion (Diccionario en Global.mdb; 185 526 filas)
export interface Prestacion {
  codigoPrestacion: string;
  textoPrestacion: string;
  year: number;
  serie: string;                 // 'A' | 'BS' | 'BM' | 'D' | 'P' | 'F' (valores a confirmar)
  hoja: string;
  linea: number;
  inicio: number;
  fin: number;
  posicion: number;
  tipoDato: string;
  mes: number;
}

// Entidades secundarias
export interface Servicio { idServicio: number; nServicio: string; idRegion?: number; orden?: number; elimId?: boolean }
export interface HistCarga { idEstablecimiento: string; mes: number; ano: number; serie: string }
export interface ErrorCarga { archivo?: string; idEstablecimiento: string; mes: number; ano: number; serie: string; linea: number; hoja: string; dato: string }
export interface VersionPlantilla { ano: number; mes: number; serie: string; serieExcel: string; version: string }
export interface Software { nombre: 'CargaRem'; version: string }
```

**Ejemplo de respuesta JSON esperada (`GET /api/registros?ano=2026&mes=3&serie=P&establecimiento=XXXX`):**

```json
{
  "items": [
    {
      "ano": 2026, "mes": 3, "idServicio": 1,
      "idEstablecimiento": "123456", "codigoPrestacion": "0101",
      "col01": 12.0, "col02": null, "col03": 4.5,
      "fechaIngreso": "2026-03-31T10:12:00"
    }
  ],
  "page": 1, "pageSize": 50, "total": 1
}
```

### 5.2 Integraciones de terceros

**Servicios web (SOAP ASMX) — base `https://cargasrem.minsal.cl/webservice/`:**

| Servicio | Operaciones (firmas observadas) | Uso |
|---|---|---|
| `WebService1.asmx` (`version`) | `version() : string` | Versión del servicio para comparar en arranque |
| `versionejecutable.asmx` | `VersionEjec() : DataSet` (tipo de retorno `class:237`, confirmar) | Validación de versión ejecutable |
| `webservicedb.asmx` (`Datos.WebServiceDB`) | `datos()`, `servicio()`, `diccionario()`, `Thoja()`, `Version()`, `Excep()`, `ColExcep()` — retorno `DataSet` [PENDIENTE] | Catálogos para `Actualizador` y `ActDBServ` |
| `CargaSerie.asmx` (`DatosSerie.CargaSerie`) | `serieA(string)`, `serieBS(string)`, `serieBM(string)`, `serieD(string)`, `serieP(string)`, `serieF(string)` | Descarga de series |
| `WebService3.asmx` (`LlenaSerie.WebService3`) | Mismas 6 operaciones `serie*(string)` | Llenado de series |
| `CargaSerieServ.asmx` (`ExtraeDatos.CargaSerieServ`) | `DatosSerie(string, int, int)` | Datos de serie por parámetros (¿serie, año, mes? [PENDIENTE]) |
| `CargaControlServ.asmx` (`ExtraeDatosC.CargaControlServ`) | `DatosControl(string, int, int)` | Datos de control |
| `Optimiza_Up.asmx` (`TabUp`/`ContUp`) | `Datos_Up()`, `Contador_Up() : string` | Optimización/actualización remota |

**Hosts en configuración (`ValidadorRem.exe.config`):**
- `https://cargasrem.minsal.cl/webservice/…` (producción).
- `http://intradeis.minsal.cl/webservicePrueba/Optimiza_Up.asmx` (`ContUp`, entorno de **prueba**, en **HTTP sin TLS**). Riesgo a validar.
- `https://cargasrem.minsal.cl/webservice/Optimiza_Up.asmx` (`TabUp`). Literal también presente en metadatos.

**Autenticación hacia los WS:** no se observaron credenciales, cabeceras de seguridad ni tokens en metadatos. **[PENDIENTE DE VALIDACIÓN]** con captura de tráfico (ver 5.4).

**Otras dependencias externas:**
- Microsoft Access Database Engine (ACE OLEDB 12.0 de 32 bits).
- Microsoft Office Excel (PIA `Microsoft.Office.Interop.Excel` 14.0, requiere Office instalado para exportes).
- NPOI 2.3 (lectura `.xlsx`).
- Jet 4.0 y JRO (compactación).

**No se detectaron:** pasarelas de pago, mapas, proveedores de identidad (Auth0/Firebase), telemetría ni APIs de terceros.

### 5.3 Hallazgos de datos e integridad (para el equipo)

- **Inconsistencia de tipo en `IdServicio`:** `DatosSERIEP2026.mdb.Datos.IdServicio` es `Long`, mientras que `MASTER.mdb.Datos.IdServicio` y `MASTER.mdb.DatosC.IdServicio` son `WChar`. Hay que normalizar al migrar.
- **Ausencia de llaves primarias en la lectura de esquema:** el esquema devuelto por OLE DB no informa índices ni PK. La unicidad se aplica en código (la consulta de duplicados existe justamente por esto). **[PENDIENTE DE VALIDACIÓN]** con `Indexes`.
- **Columnas `Col01…Col50`:** `Double`. En `DatosSERIEP2026` el nombre varía (`Col01`… y luego `col42`… en minúscula). Migración: normalizar a `col01…col50`.
- **Plantillas por mes/año:** la tabla `version` y `Thojas` cambian por año y mes. La lógica de validación depende de ellas, no de código fijo.
- **Volúmenes observados:** `Diccionario` 185 526; `mpEstablecimiento` 3 207; `version` 939; `Thojas` 1 129; `Excep` 5 664; `colexcep` 2 977; `mpServicio` 30; `HistCarga` 4 000 (Trans.mdb); `Datos` 15 060 (DatosSERIEP2026); `Cargados` 602 (Temporal.mdb).
- **Bloqueo de archivos:** durante el análisis, los `.mdb` estaban bloqueados (`No se pudo bloquear el archivo`). Hay procesos concurrentes o el archivo se mantiene abierto; la migración debe prever bloqueos.
- **Datos personales en la ruta:** literal `C:\Documents and Settings\mcastillo\Mis documentos` (ruta de ejemplo con un nombre de usuario). Eliminar del código.
- **Typos y nomenclatura:** `VlidadorRem` (en metadatos y configuración), `VlidadorRem_*` en nombres de settings, y `ValidadorRem_ExtraeDatosC_CargaControlServ` (con prefijo distinto) en la configuración.

### 5.4 [PENDIENTE DE VALIDACIÓN] — qué falta y cómo obtenerlo

| # | Elemento pendiente | Por qué no se pudo extraer | Cómo obtenerlo |
|---|---|---|---|
| P1 | Payload y respuesta SOAP exactos (XML) de cada operación | Son runtime; el análisis estático no los contiene | Captura en runtime con un proxy HTTP (Fiddler/mitmproxy) con la app ejecutada en entorno de prueba. Verificar TLS y autenticación. |
| P2 | Estructura exacta de los `DataSet` devueltos por `datos()`, `diccionario()`, `Thoja()`, etc. | El tipo de retorno es `class:237` (DataSet), sin columnas | Capturar una respuesta real (igual que P1) o inspeccionar con REA los anchors de `Actualizacion.actualizar`. |
| P3 | Lógica interna exacta de validación (`CargaDatos.Button4_Click`, `Busca_Dato`, `VeriExcep`, `Cuantas`, `bases.compara_mes`) | Se extrajeron llamadas y literales, no el pseudocódigo | Descompilar con dnSpy (incluido en la carpeta) o ILSpy. Después, `import_managed_reconstruction` de REA contra la evidencia de `inspect_managed_members`. |
| P4 | Mapeo exacto menú → formulario para "Glosa de prestaciones", "Decimales", "Informe1", "Actualiza series", "Actualiza sistema", "Datos desde Servidor" | Los nombres de manejadores no se pudieron asociar sin ver la UI | Ejecutar la app y capturar la UI, o revisar `Principal.InitializeComponent` (6 322 bytes) con anchors de `ToolStripMenuItem`. |
| P5 | Significado de dominio de las series (`A`, `BS`, `BM`, `D`, `P`, `F`) y del acrónimo REM | No está en el binario como texto explicativo | Validar con el área de negocio / documentación del MINSAL. |
| P6 | Semántica de `Col01…Col50` | Requiere cruzar `Diccionario` con las hojas Excel | Consulta a `Global.mdb.Diccionario` por `Hoja`, `Serie` y `Year` (no se realizó: la base de 422 MB estaba bloqueada). |
| P7 | Formato del archivo MINSAL (contenedor, extensión, cabecera, nombre) | El bloque GZip no revela el esquema de salida | Generar una muestra en entorno de prueba y analizar el archivo resultante. |
| P8 | Efecto de `generacion = False` y de `Puntos` (`InformeControl`) | Solo se observó la configuración y el nombre del método | Revisar IL o ejecutar en entorno de prueba. |
| P9 | Comportamiento ante 401/403/404/500 y reintentos de los WS | El análisis estático solo muestra las llamadas | Pruebas de integración contra un entorno de staging que devuelva esos códigos. |
| P10 | Si el host `http://intradeis.minsal.cl/webservicePrueba/…` sigue en uso en producción | Es una configuración, no prueba de uso | Confirmar con la unidad de TI del MINSAL y revisar tráfico real. |
| P11 | Roles y permisos reales requeridos | El sistema no los implementa | Definir con negocio antes de diseñar el RBAC de la versión web. |
| P12 | Serie local SAX (Anexo, Servicio de Salud Osorno): estructura, hojas, reglas de validación y carga | No existe en el sistema actual | Documentación en sistema aparte (gestionada por el usuario); integrar al informe cuando esté disponible. |
| P13 | Stack tecnológico del módulo de envío (sección 2.10) | Decisión pendiente | Definir antes de diseñar la arquitectura del portal. |

---

**Conclusión operativa para el equipo:** ValidadorRem es una herramienta de escritorio monousuario centrada en validar y consolidar archivos REM en bases Access por serie y año, con un fuerte acoplamiento a los servicios SOAP del MINSAL y a la versión de plantillas Excel. Para una reconstrucción web, los riesgos principales son la dependencia de Jet/ACE (32 bits), la falta de RBAC, la sobrescritura destructiva sin control de acceso, y los endpoints en HTTP de prueba. Los puntos P1 a P11 deben cerrarse antes de implementar.
