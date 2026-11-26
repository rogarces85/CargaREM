# Estado del proyecto para la entrega

Resumen para quien toma el proyecto. Fecha: 26 de noviembre de 2026.
Especificación vigente: [`specs/MVP_V3.md`](../../specs/MVP_V3.md).
Canon: Constitución [v6.0.0](../../02-DOCS/wiki/sdd/constitution.md) (Laravel 12.72 + PHP 8.2 + Tailwind CSS v4).

## Qué está hecho

Un portal web que reemplaza el circuito de entrega y revisión de los archivos
REM mensuales. El sistema de escritorio sigue siendo el que arma los Excel; el
portal se ocupa de que lleguen, se validen, se revisen y queden registrados.

| Módulo | Qué entrega | Estado |
|---|---|---|
| **MOD-01** | Login, logout, cuenta propia, 3 roles, **20 permisos**, alta de usuarios, **reparto de establecimientos** | ✅ |
| **MOD-02** | Catálogos: servicios, establecimientos, series (con meses exigidos), períodos, matriz, plantillas, diccionario sembrado y **calendario automático verificado** | ✅ |
| **MOD-03** | Envío mensual, versiones, hash, revalidación, descargas y **filtros por año, mes, comuna, establecimiento y estado** | ✅ |
| **MOD-04** | Validación V0–V11 (sin V8) y reporte de hallazgos | ✅ |
| **MOD-05** | Máquina de estados con **reapertura con motivo** | ✅ |
| **MOD-06** | Bandeja del **Equipo REM** acotada a sus establecimientos, observaciones por serie, aprobación, **solicitud de nueva subida** y **aperturas de plazo** | ✅ |
| **MOD-07** | Matriz de completitud con semáforo, totales, **Serie P semestral** y **panel con indicadores** | ✅ |
| **MOD-08** | Auditoría append-only (tabla, servicios y observers), con aperturas y uso del Validador | ✅ |
| **MOD-09** | Notificación en base de datos, campana **con distintivo de subida** y **página de historial** | ✅ |
| **MOD-11** | Cabeceras, CSRF, rate limit, health check, logs y validación real | ✅ |
| **MOD-12** | **Módulo Validador**: reglas de calidad desde JSON, aislamiento del flujo y registro de uso | ✅ (reglas a la espera del JSON de la Unidad) |

## Lo que se puede usar hoy

```bat
iniciar.bat           Levanta todo y abre el navegador
probar-archivos.bat   Valida los Excel de portal\archivos
```

El ciclo completo funciona: un encargado crea el envío y sube sus series, el
sistema las valida, el revisor las observa o aprueba la estadística completa. Los
archivos quedan guardados en el servidor; no hay paquete ni carga automática.

**Probado con archivos reales del Minsal**: cinco archivos de 30 hojas, serie A,
meses 07 a 09 de 2026. Se leen correctamente y el motor no encuentra bloqueantes.

## Lo que falta, y por qué

Ninguna es código pendiente del corte:

| Tema | Qué necesita | Quién |
|---|---|---|
| Paridad con *golden files* | El reporte del **ValidadorRem** sobre los archivos de prueba | Referente de Estadísticas |
| Decisiones §8 | D5 series obligatorias, D6 SAX | Jefatura + TI |
| UAT | 2 encargados, 1 revisor y 1 admin usando el portal | Unidad de Estadísticas |

Las decisiones están en [`docs/decisiones/pendientes-de-firma.md`](../decisiones/pendientes-de-firma.md)
y el guion de UAT en [`docs/uat/guion-uat.md`](../uat/guion-uat.md).

## Lo que el sistema NO prueba

**No está verificado que se comporte igual que el ValidadorRem.** Aplica las
reglas documentadas y produce un reporte coherente sobre archivos reales, pero
la comparación código por código contra el sistema anterior no se hizo porque
falta su salida.

Es la diferencia entre «cumple la especificación» y «equivale al sistema
anterior». La primera está probada; la segunda, no.

## Riesgos vivos

| Riesgo | Impacto | Mitigación |
|---|---|---|
| Paridad sin verificar | Un archivo podría aprobarse aquí y rechazarse en el sistema anterior | Correr `rem:paridad` en cuanto lleguen los esperados |
| Sin snapshot de catálogo por archivo | Revalidar usa el catálogo vigente, no el de la fecha de carga | Decisión D-025: se conservan hallazgos, `validado_en` y hash |
| Feriados sin cargar para un año | El plazo se calcula solo con fines de semana y puede quedar 1 o 2 días más largo | La pantalla de calendario y `rem:calendario` avisan; se cargan en Feriados |
| UAT sin hacer | Mensajes o pantallas confusas que ningún test detecta | El guion está listo; falta ejecutarlo |
| Validación lenta (35 s por archivo de 1,5 MB) | Un archivo del doble de tamaño se acerca al límite de 90 s | Medido y documentado; optimizable |

## Deuda técnica anotada

| Tema | Dónde |
|---|---|
| Las filas leídas y válidas no se calculan todavía | El reporte las deja sin total |
| El reporte se regenera al revalidar, no se versiona | `ValidadorArchivo` |
| `plantilla_cabecera` se siembra y no se edita | `Admin\PlantillaController` |

## Cómo se levanta en otra máquina

Dos cosas hay que copiar aparte, porque no van en el repositorio (son datos
reales):

1. `C:\xampp\_snapshot_m7\` — el catálogo (23 MB). Se regenera con
   `01-TOOLS\rem-export\export-mdb.ps1` si hay acceso al Access del Minsal.
2. `portal\archivos\` — los `.xlsm` de prueba (1,5 MB cada uno).

Después: [`docs/operacion/como-probar.md`](../operacion/como-probar.md).

## Números

- **383 tests, 972 aserciones, 0 fallos** (medido el 2026-11-26).
- **3 roles y 20 permisos.**
- **185.526 prestaciones** en el catálogo del Minsal sembrado.
- Bandeja con 100 envíos: **0,72 s**. Matriz 30×7: **0,31 s**.
- UI: **51 vistas** y **21 componentes Blade** sobre Tailwind v4; `public/build/` pesa 12 archivos.

## Cómo se publica la interfaz

El servidor no tiene Node. La UI se compila en local (`npm run build`) y
`portal/public/build/` se versiona como artefacto: es lo que se sube por FTP.
Si se cambia una vista, un icono o una clase de Tailwind sin recompilar, el
despliegue sirve la interfaz anterior.
