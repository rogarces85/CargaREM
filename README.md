# CargaREM

Documentación técnica del sistema **ValidadorRem ("Sistema Cargas REM")** y de la propuesta de modernización con un módulo de envío de estadísticas por establecimiento.

## Contenido del repositorio

| Archivo | Descripción |
|---|---|
| `informe.md` | Informe técnico exhaustivo de ValidadorRem: arquitectura, módulos, reglas de negocio, flujos, requisitos técnicos y datos. Incluye el módulo de envío (sección 2.10) y los pendientes de validación. |
| `stack_modulo_envio.md` | Propuesta de stack para el módulo de envío: restricciones confirmadas del servidor, stack propuesto, estructura de despliegue, riesgos y pendientes. |
| `Informe_Tecnico_Cargas_REM.rar` | Versión comprimida de la documentación técnica previa. |
| `Flujo general del sistema Cargas REM.png` | Diagrama del flujo general del sistema actual. |

## Contexto

ValidadorRem es una aplicación de escritorio (WinForms, .NET Framework 4.8, x86) que carga, valida y consolida archivos Excel mensuales del REM en bases Access por Servicio de Salud, genera el archivo de envío MINSAL y sincroniza catálogos con servicios web del MINSAL.

Hoy, los establecimientos envían sus archivos por correo electrónico y la Unidad de Estadísticas los revisa y los carga a mano. La propuesta es un portal donde cada encargado de establecimiento sube sus archivos, recibe validación inmediata y sigue el estado de su envío, con revisión y aprobación por la Unidad de Estadísticas.

## Estado del proyecto

Esta es una **fase de documentación y definición**. No hay código de aplicación todavía.

Pendientes principales (detalle en `informe.md`, sección 5.4, y en `stack_modulo_envio.md`, sección 6):

- Confirmar la versión de PHP del servidor (hoy 7.4.3, fuera de soporte) y el framework definitivo.
- Definir la fuente de identidad de los encargados.
- Decidir el puente con CARGAS REM (exportación o reemplazo de la carga).
- Documentar la serie local SAX (Anexo, Servicio de Salud Osorno), en un sistema aparte.
- Validar tiempos y tamaños con archivos reales.

## Entorno de referencia

- Servidor de destino: Ubuntu 20.04, Apache con `mod_php`, PHP 7.4.3, MySQL 8.0.42 (`utf8mb4`).
- Acceso al servidor: solo FTP, con TLS.
- Publicación prevista: `https://estadisticas.ssosorno.cl/`.

## Datos sensibles

Este repositorio no debe contener datos reales de establecimientos, bases de datos (`.mdb`, `.sql`), credenciales ni archivos `.env`. Revisa cada commit antes de subirlo.
