# CargaREM

Documentación técnica del sistema **ValidadorRem** ("Sistema Cargas REM") y del diseño de un módulo nuevo de envío de estadísticas por establecimiento.

> **Estado:** fase de documentación y definición. Todavía no hay código de aplicación.

---

## Contexto

ValidadorRem es una aplicación de escritorio (WinForms, .NET Framework 4.8, x86) que carga, valida y consolida archivos Excel mensuales del REM en bases Access por Servicio de Salud, genera el archivo de envío MINSAL y sincroniza catálogos con los servicios web del MINSAL.

Hoy los establecimientos envían sus archivos por correo electrónico y la Unidad de Estadísticas los revisa y los carga a mano. La propuesta es un portal donde cada encargado de establecimiento sube sus archivos, recibe validación inmediata y sigue el estado de su envío, con revisión y aprobación por la Unidad de Estadísticas.

---

## Estructura del repositorio

```text
Cargas/
├── informe.md                  Informe técnico completo del sistema actual
├── stack_modulo_envio.md       Restricciones del servidor y stack del portal
├── backup_documentacion/       Documentación profundizada por módulo (M1–M7)
├── docs/                       Diagramas SVG y flujo de datos integral
└── Flujos/                     Diagramas PNG de los flujos principales
```

| Ruta | Contenido |
|---|---|
| `informe.md` | Informe técnico exhaustivo de ValidadorRem: arquitectura, módulos, reglas de negocio, flujos, requisitos técnicos y datos. Incluye el módulo de envío (§2.10) y los pendientes de validación (P1–P13). |
| `stack_modulo_envio.md` | Stack propuesto para el portal: restricciones confirmadas del servidor, estructura de despliegue, riesgos y pendientes. |
| `backup_documentacion/` | Documentación profundizada por módulo, con índice en [`backup_documentacion/README.md`](backup_documentacion/README.md). |
| `docs/` | Diagramas SVG por módulo y [`FLUJO_DATOS_INTEGRAL_SISTEMA.md`](docs/FLUJO_DATOS_INTEGRAL_SISTEMA.md). |
| `Flujos/` | Diagramas PNG de los flujos principales. |

---

## Módulos documentados

Los módulos **M1–M6** describen el sistema de escritorio que existe hoy. El módulo **M7** es el diseño del portal nuevo, que todavía no se construye.

| Módulo | Documento | Alcance | Diagrama |
|---|---|---|---|
| **M1** Arranque y Configuración | [`MODULO_M1_PROFUNDIZADO.md`](backup_documentacion/MODULO_M1_PROFUNDIZADO.md) | Shell principal, splash, selección de Servicio de Salud, actualización inicial | [SVG](docs/M1_DIAGRAMA_ARQUITECTURA.svg) |
| **M2** Carga de Datos | [`MODULO_M2_PROFUNDIZADO.md`](backup_documentacion/MODULO_M2_PROFUNDIZADO.md) | Motor de validación `CargaExcel`, carga individual y por carpeta, consumo de web services | [SVG](docs/M2_DIAGRAMA_CARGA.svg) |
| **M3** Errores y Control de Carga | [`MODULO_M3_PROFUNDIZADO.md`](backup_documentacion/MODULO_M3_PROFUNDIZADO.md) | Registro y consulta de errores, hoja de control con cálculo de puntos | [SVG](docs/M3_DIAGRAMA_ERRORES.svg) |
| **M4** Consulta e Informes | [`MODULO_M4_PROFUNDIZADO.md`](backup_documentacion/MODULO_M4_PROFUNDIZADO.md) | Matriz mensual, duplicidad, búsqueda por prestación, comparativos e informe INF1 | [SVG](docs/M4_DIAGRAMA_CONSULTAS.svg) |
| **M5** Mantenimiento y Exportación | [`MODULO_M5_PROFUNDIZADO.md`](backup_documentacion/MODULO_M5_PROFUNDIZADO.md) | Borrado selectivo, compactación de Access, exportación DBF y archivo MINSAL | [SVG](docs/M5_DIAGRAMA_MANTENIMIENTO.svg) |
| **M6** Sincronización y Actualización | [`MODULO_M6_PROFUNDIZADO.md`](backup_documentacion/MODULO_M6_PROFUNDIZADO.md) | Actualización de catálogos y versiones contra los servicios web del MINSAL | [SVG](docs/M6_DIAGRAMA_SYNCHRONIZATION.svg) |
| **M7** Envío por Establecimiento | [`MODULO_M7_PROFUNDIZADO.md`](backup_documentacion/MODULO_M7_PROFUNDIZADO.md) | Portal nuevo: carga, validación, revisión, aprobación y paquete de carga | [SVG](docs/M7_DIAGRAMA_ENVIO.svg) |

---

## Módulo nuevo: M7 (propuesto)

Diseño funcional y técnico del portal de envío mensual por establecimiento. Cubre:

- **Modelo de datos MySQL 8** con envíos por `(establecimiento, año, mes)` y archivos versionados por serie, sin sobrescritura.
- **Máquina de estados** explícita (`recibido`, `validado`, `con_errores`, `en_revision`, `observado`, `aprobado`, `cargado`, `anulado`) con guardas y auditoría de cada transición.
- **Pipeline de validación V0–V11**, separando errores bloqueantes de advertencias, y con snapshot de catálogo por archivo para que el resultado sea reproducible.
- **Pantallas, rutas y permisos** para encargado, revisor de la Unidad de Estadísticas y administrador.
- **Arquitectura Laravel 8 / PHP 7.4** compatible con el servidor real: sin SSH, sin colas, sin cron, `upload_max_filesize=2M`.
- **Puente con CARGAS REM**: descarga manual de archivos aprobados en el MVP, paquete con manifiesto e importador como siguiente fase.

El portal **no reemplaza a ValidadorRem ni genera el archivo MINSAL** en su primera versión: valida, ordena la revisión y entrega un paquete aprobado.

---

## Estado del proyecto

Esta es una **fase de documentación y definición**. No hay código de aplicación todavía.

El diseño del módulo M7 está completo a nivel funcional y técnico. Lo que falta es cerrar las decisiones de negocio y validar el motor de lectura contra archivos reales antes de construir.

### Pendientes principales

Detalle en `informe.md` §5.4 y `stack_modulo_envio.md` §6.

- Confirmar la versión de PHP del servidor (hoy 7.4.3, fuera de soporte) y el framework definitivo.
- Definir la fuente de identidad de los encargados.
- Decidir el puente con CARGAS REM (descarga manual, paquete o reemplazo de la carga).
- Confirmar la semántica de `Diccionario`, `Excep` y `colexcep`, y las coordenadas de cabecera de cada plantilla.
- Documentar la serie local SAX (Anexo, Servicio de Salud Osorno).
- Validar tiempos, memoria y tamaños con archivos reales.

---

## Entorno de referencia

- Servidor de destino: Ubuntu 20.04, Apache con `mod_php`, PHP 7.4.3, MySQL 8.0.42 (`utf8mb4`).
- Acceso al servidor: solo FTP, con TLS.
- Publicación prevista: `https://estadisticas.ssosorno.cl/`.

---

## Convenciones de la documentación

| Símbolo | Significado |
|---|---|
| ✅ | Confirmado en el sistema o en la documentación |
| 🟡 | Decisión propuesta, pendiente de validación |
| ❓ | Pendiente de definición por negocio o TI |

---

## Datos sensibles

Este repositorio no debe contener datos reales de establecimientos, bases de datos (`.mdb`, `.sql`), credenciales ni archivos `.env`. Revisa cada commit antes de subirlo.
