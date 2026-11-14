# Resultado del UAT técnico — 2026-11-14

> **Alcance:** recorrido encargado → revisor → administrador sobre el portal
> local, con el catálogo real sembrado. **No reemplaza** la UAT con personas:
> cubre el flujo y las reglas, no la percepción visual ni la accesibilidad.
>
> **Especificación:** [`specs/MVP_V2.md`](../../specs/MVP_V2.md) (v2.0, 14-11).
> **Guion:** [`guion-uat.md`](guion-uat.md).
> **Ejecutor:** agente, por HTTP con sesiones reales (sin navegador gráfico).
> **Entorno:** `http://localhost/Cargas/portal/public` · MySQL 8 local ·
> `cargas_rem` con 86 establecimientos, 6 series, 939 plantillas y 185.526 filas
> de diccionario.

## 1. Cómo se ejecutó

Cada rol se autenticó con su cuenta real y recorrió el flujo con peticiones HTTP
(CSRF incluido). Las comprobaciones se validaron contra la base de datos y el
contenido devuelto por las pantallas.

Casos con datos reales:

- Envío **#2** · establecimiento 123100 · período **08/2026** · fecha límite
  2026-09-10.
- Archivos: `123100A08.xlsm` (versión válida) y `123100A08-corregido.xlsx`
  (corrección, misma data re-guardada).
- Archivo ajeno para el caso E6: `123012A09.xlsm` (otro establecimiento y otro
  mes).

## 2. Resultados

### Encargado

| # | Escenario | Resultado | Evidencia |
|---|---|---|---|
| E1 | Entrar y ver solo sus establecimientos | ✅ | Panel con «Encargado Hospital Base»; lista con 123100–123103 |
| E2 | Crear el envío del mes | ✅ | Envío #2 en `recibido`, `fecha_limite` 2026-09-10 |
| E3 | Subir los Excel de las series exigidas | ✅ | `subido y validado`; envío pasa a `validado` |
| E4 | Ver el reporte con hallazgos | ✅ | Tabla de hallazgos por etapa y código en el detalle |
| E5 | Descargar el reporte en Excel | ✅ | `Content-Type` `spreadsheetml` |
| E6 | Subir un archivo de otro establecimiento | ✅ (con observación) | Mensaje «guardado, pero la validación encontró bloqueante(s)»; envío queda `con_errores` |
| E7 | Corregir una serie observada | ✅ | Observación resuelta sola; versión 3 vigente |
| E8 | Enviar a revisión y reenviar | ✅ | `en_revision` dos veces; fuera de plazo marcado una sola vez |

### Revisor

| # | Escenario | Resultado | Evidencia |
|---|---|---|---|
| R1 | Abrir la bandeja | ✅ | Envío 123100 visible |
| R2 | Filtros de la bandeja | ✅ | Estado, establecimiento, año y antigüedad (probrado por tests) |
| R3 | Observar apuntando a una serie | ✅ | Estado `observado`; observación con `resuelta=1` tras la corrección |
| R4 | Aprobar con observaciones abiertas | ✅ | Rechazo probado en la suite (`AprobacionGuardasTest`) |
| R5 | Aprobar el envío completo | ✅ | `aprobado`; archivo vigente `aprobado`; notificación al encargado |
| R6 | Reabrir / anular | ✅ | Las rutas responden 404: fuera de v2.0 |
| R7 | Ver la matriz de completitud | ✅ | Semáforo con el envío aprobado |
| R8 | Exportar / drill-down | ✅ | Sin botones de exportación y sin enlace por celda |

### Administrador

| # | Escenario | Resultado | Evidencia |
|---|---|---|---|
| A1 | Catálogo sembrado | ✅ | 185.526 filas; validación deja de pedir plantilla |
| A2 | Editar establecimientos | ✅ | Formulario con selección de Servicio |
| A3 | Matriz de series | ✅ | Pantalla y edición disponibles |
| A4 | Crear usuario con rol | ✅ | CRUD probado en la suite (`UsuarioCrudTest`) |
| A5 | Desactivarse a sí mismo | ✅ | Rechazo probado en la suite |
| A6 | Gestionar plantillas y hojas | ✅ | Formulario con hojas `HOJA|RANGO` |
| A7 | Envío aprobado sin reabrir ni anular | ✅ | Sin botón ni ruta; estado final `aprobado` |
| A8 | Bitácora | ✅ | Tabla `auditoria` append-only; sin pantalla de consulta |

## 3. Incidencias encontradas y corregidas

1. **Mensaje engañoso al subir un archivo con bloqueantes.** El portal decía
   «subido y validado» aunque la cadena V0–V11 hubiera dejado bloqueantes. Ahora
   guarda el archivo (la validación corre después) y avisa cuántos bloqueantes
   encontró. Cubierto por `EnvioWebTest::test_un_archivo_con_bloqueantes_avisa_del_problema`.
2. **Ruido de auditoría en la siembra.** El observer de catálogos registraba
   1.044 eventos `catalogo.*` producidos por el seeder, sin usuario. Ahora solo
   audita acciones con sesión abierta.
3. **Dato residual del comando de paridad.** El envío #1 (creado por
   `rem:validar`) quedó con el archivo de otro establecimiento tras la prueba
   E6. Se descartó esa versión en la base de desarrollo y se dejó la válida.

## 4. Observaciones para decidir

- **E6:** el archivo con bloqueantes **se guarda** y se informa; no se rechaza
  antes de almacenar. Es coherente con §5 (la validación corre después de la
  subida) y con el versionado, pero si se prefiere rechazar V1/V2 antes de
  guardar, es un cambio de diseño a decidir.
- **Plazo:** el período 08 tenía fecha límite 2026-09-10, así que el envío quedó
  «fuera de plazo» y se aceptó igual (D-017). Sin fecha en el período no hay
  marca (D4 abierta).

## 5. Lo que falta para cerrar la UAT formal

| Tema | Quién |
|---|---|
| Recorrido visual con 2 encargados, 1 revisor y 1 admin | Unidad de Estadísticas |
| Accesibilidad (teclado, contraste, foco) y textos percibidos | Unidad de Estadísticas + TI |
| Paridad con *golden files* del ValidadorRem | Referente de Estadísticas |

Suite al cierre de este recorrido: **235 tests, 587 aserciones, 0 fallos**.
