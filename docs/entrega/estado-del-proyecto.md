# Estado del proyecto para la entrega

Resumen para quien toma el proyecto. Fecha: 12 de noviembre de 2026.

## Qué está hecho

Un portal web que reemplaza el circuito de entrega, revisión y carga de los
archivos REM mensuales. El sistema de escritorio sigue siendo el que arma los
Excel; el portal se ocupa de que lleguen, se validen y queden registrados.

| Fase | Qué entrega | Estado |
|---|---|---|
| **F1** Base | Laravel 12, login, roles y permisos, catálogos, auditoría | **31 de 31** |
| **F2** Carga y validación | Subida con versiones, motor V0–V11 con 32 códigos, reportes | **30 de 31** |
| **F3** Revisión | Observaciones, bandeja, aprobación con guardas, avisos por correo | **14 de 14** |
| **F4** Completitud | Matriz con semáforo por establecimiento y serie, exportable | **5 de 5** |
| **F5** Puente | Paquete ZIP con manifiesto firmado para la carga institucional | **5 de 5** |
| **F6** Endurecimiento | Seguridad, límites, respaldo probado, manuales, UAT | **15 de 16** |

**107 de 112 tareas.**

## Lo que se puede usar hoy

```bat
iniciar.bat           Levanta todo y abre el navegador
probar-archivos.bat   Valida los Excel de portal\archivos
```

El ciclo completo funciona: un encargado sube sus series, el sistema las valida,
el revisor las observa o las aprueba, y el portal produce un ZIP verificable con
los archivos, sus reportes y un manifiesto que dice con qué catálogo se validó.

**Probado con archivos reales del Minsal**: cinco archivos de 30 hojas, serie A,
meses 07 a 09 de 2026. Se leen correctamente y el motor no encuentra
bloqueantes.

## Lo que falta, y por qué

Cinco tareas. **Ninguna es código pendiente.**

| Tarea | Qué necesita | Quién |
|---|---|---|
| `T-F2-25` Paridad | El reporte del **ValidadorRem** sobre los cinco archivos | Referente de Estadísticas |
| `T-F0-03` | Correr el ValidadorRem en entorno de prueba | Referente de Estadísticas |
| `T-F0-05` a `08` | Firmar las cuatro decisiones (hay recomendación escrita) | Jefatura + TI |
| `T-F6-13` UAT | 2 encargados, 1 revisor y 1 admin usando el portal | Unidad de Estadísticas |

Las decisiones están en [`docs/decisiones/pendientes-de-firma.md`](../decisiones/pendientes-de-firma.md)
con recomendación, justificación y consecuencia de rechazarlas. El guion de UAT
está en [`docs/uat/guion-uat.md`](../uat/guion-uat.md) con 24 escenarios.

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
| UAT sin hacer | Mensajes o pantallas confusas que ningún test detecta | El guion está listo; falta ejecutarlo |
| Validación lenta (35 s por archivo de 1,5 MB) | Un archivo del doble de tamaño se acerca al límite de 90 s | Medido y documentado; optimizable |
| CSP permite estilos y scripts en línea | Menor protección ante XSS | Mover el JavaScript a archivos propios |

## Deuda técnica anotada

| Tema | Dónde |
|---|---|
| `colexcep` sin semántica confirmada | `ReglaV8Excepciones` documenta el supuesto |
| Las filas leídas y válidas no se calculan todavía | El manifiesto las deja en `null` |
| El reporte se regenera al revalidar, no se versiona | `ValidadorArchivo` |

## Cómo se levanta en otra máquina

Dos cosas hay que copiar aparte, porque no van en el repositorio (son datos
reales):

1. `C:\xampp\_snapshot_m7\` — el catálogo (27 MB). Se regenera con
   `01-TOOLS\rem-export\export-mdb.ps1` si hay acceso al Access del Minsal.
2. `portal\archivos\` — los `.xlsm` de prueba (1,5 MB cada uno).

Después: [`docs/operacion/como-probar.md`](../operacion/como-probar.md).

## Números

- **246 tests, 644 aserciones, 0 fallos.**
- **33 tablas** en la base; 18 permisos y 4 roles.
- **185.526 prestaciones** en el catálogo del Minsal.
- Respaldo de 80 MB que se restaura en **21 segundos**.
- Bandeja con 100 envíos: **0,72 s**. Matriz 30×7: **0,31 s**.
