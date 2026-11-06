# Documentación por módulo

Documentación profundizada del sistema **ValidadorRem** y del módulo nuevo de envío. Los documentos de contexto general están un nivel arriba, en la raíz del repositorio.

## Índice de módulos

| Módulo | Documento | Estado | Diagrama |
|---|---|---|---|
| **M1** Arranque y Configuración | [`MODULO_M1_PROFUNDIZADO.md`](MODULO_M1_PROFUNDIZADO.md) | ✅ Implementado | `../docs/M1_DIAGRAMA_ARQUITECTURA.svg` |
| **M2** Carga de Datos | [`MODULO_M2_PROFUNDIZADO.md`](MODULO_M2_PROFUNDIZADO.md) | ✅ Implementado | `../docs/M2_DIAGRAMA_CARGA.svg` |
| **M3** Errores y Control de Carga | [`MODULO_M3_PROFUNDIZADO.md`](MODULO_M3_PROFUNDIZADO.md) | ✅ Implementado | `../docs/M3_DIAGRAMA_ERRORES.svg` |
| **M4** Consulta e Informes | [`MODULO_M4_PROFUNDIZADO.md`](MODULO_M4_PROFUNDIZADO.md) | ✅ Implementado | `../docs/M4_DIAGRAMA_CONSULTAS.svg` |
| **M5** Mantenimiento y Exportación | [`MODULO_M5_PROFUNDIZADO.md`](MODULO_M5_PROFUNDIZADO.md) | ✅ Implementado | `../docs/M5_DIAGRAMA_MANTENIMIENTO.svg` |
| **M6** Sincronización y Actualización | [`MODULO_M6_PROFUNDIZADO.md`](MODULO_M6_PROFUNDIZADO.md) | ✅ Implementado | `../docs/M6_DIAGRAMA_SYNCHRONIZATION.svg` |
| **M7** Envío de Estadísticas por Establecimiento | [`MODULO_M7_PROFUNDIZADO.md`](MODULO_M7_PROFUNDIZADO.md) | 🟡 Propuesto (sin código) | `../docs/M7_DIAGRAMA_ENVIO.svg` |

## Cómo leer esta carpeta

- **M1–M6** describen el sistema de escritorio que existe hoy: formularios, clases, reglas de negocio, estructuras de Access y métricas.
- **M7** describe el portal nuevo que todavía no se construye: modelo MySQL, máquina de estados, pipeline de validación, pantallas, arquitectura Laravel 8, seguridad, plan y criterios de aceptación.
- **`FLUJO_DATOS_MODULO_CARGA.md`** es el flujo detallado del módulo de carga, conservado como respaldo.

## Documentos de contexto (raíz del repositorio)

| Documento | Contenido |
|---|---|
| `../informe.md` | Informe técnico completo de ValidadorRem; incluye el módulo de envío (§2.10) y los pendientes P1–P13. |
| `../stack_modulo_envio.md` | Restricciones del servidor y stack propuesto para el portal. |
| `../README.md` | Descripción general del repositorio y estado del proyecto. |
| `../docs/FLUJO_DATOS_INTEGRAL_SISTEMA.md` | Flujo de datos de extremo a extremo del sistema actual. |

## Convenciones

| Símbolo | Significado |
|---|---|
| ✅ | Confirmado en el sistema o en la documentación |
| 🟡 | Decisión propuesta, pendiente de validación |
| ❓ | Pendiente de definición por negocio o TI |
