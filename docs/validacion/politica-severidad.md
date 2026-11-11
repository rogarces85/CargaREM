---
type: politica
title: Política de severidad de validación REM — V0–V11
description: Definición formal de severidades (Bloqueante/Advertencia/Informativa), reglas de parada de cadena, criterios de escalación y matriz de decisión por código ENV-###.
tags: [validacion, severidad, politica, env, rem, paridad]
timestamp: 2026-11-10T15:45:00Z
topic: validacion
source: backup_documentacion/MODULO_M7_PROFUNDIZADO.md §7.5, §20.1; SPECS.md §9.1; Constitución v3.1.0 Principio 6
status: borrador_para_firma
---

# Política de severidad de validación REM

> **Estado:** Borrador para firma por Unidad de Estadísticas (T-F0-05).
> **Para firmar:** use la [hoja de firma de una página](hoja-firma-severidad.md). Este documento es el detalle técnico; la hoja es lo que se firma.
> **Principio 6 (Constitución v3.1.0):** Toda regla V0–V11 tiene código `ENV-###`, severidad asignada y test obligatorio.
> **Principio 10:** Paridad con *golden files*, tolerancia 0 en numéricos.
> **Fuente canónica:** `docs/validacion/catalogo-errores-env.md` (catálogo completo ENV-001 a ENV-032).

---

## 1. Definición de severidades

| Severidad | Código | Efecto en pipeline | Acción requerida | Visibilidad |
|---|---|---|---|---|
| **Bloqueante** | `B` | **Detiene la cadena** de validación (salvo V11 que siempre corre). Impide estado `validado` y transición a `en_revision`. | Corrección obligatoria antes de continuar. El encargado **debe** subir versión corregida. | Resumen + detalle en reporte; botón "Enviar a revisión" deshabilitado. |
| **Advertencia** | `W` | **No detiene** la cadena. Permite estado `validado` y transición a `en_revision`. | El revisor **debe** marcar "revisada" cada advertencia antes de aprobar. No se ignora en silencio. | Resumen + checklist obligatorio en modal "Aprobar". |
| **Informativa** | `I` | Solo para reporte y auditoría. No afecta estados ni transiciones. | Ninguna. Contexto para análisis. | Solo en hoja "Información" del reporte Excel. |

### 1.1 Regla de oro

> **Nunca se degrada una bloqueante a advertencia para "avanzar".** Si una regla es bloqueante, lo es por diseño. Cambiar severidad requiere decisión registrada en `02-DOCS/wiki/sdd/decisions.md` con firma de Unidad de Estadísticas.

---

## 2. Matriz de severidad por código ENV-###

> **Fuente:** `docs/validacion/catalogo-errores-env.md` (T-F0-09 completada).

| Código | Etapa | Severidad | Descripción breve |
|---|---|---|---|
| **ENV-001** | V0 | **Bloqueante** | Archivo > 2 MB |
| **ENV-002** | V0 | **Bloqueante** | Extensión no permitida |
| **ENV-003** | V0 | **Bloqueante** | Contenido no es Excel válido |
| **ENV-004** | V0 | **Bloqueante** | Archivo dañado / con contraseña |
| **ENV-027** | V0 | **Bloqueante** | Estructuras no permitidas (XML externo) |
| **ENV-028** | V0 | **Advertencia** | Contiene macros (no se ejecutan) |
| **ENV-005** | V1 | **Bloqueante** | Nombre no sigue formato código-serie-mes |
| **ENV-006** | V1 | **Bloqueante** | Serie del nombre no existe en catálogo |
| **ENV-007** | V1 | **Bloqueante** | Mes del nombre ≠ período envío |
| **ENV-008** | V1 | **Bloqueante** | Código nombre ≠ establecimiento envío |
| **ENV-009** | V2 | **Bloqueante** | Cabecera no detectable |
| **ENV-010** | V2 | **Bloqueante** | Serie cabecera ≠ serie seleccionada |
| **ENV-011** | V2 | **Bloqueante** | Año cabecera ≠ período |
| **ENV-012** | V2 | **Bloqueante** | Mes cabecera ≠ período |
| **ENV-013** | V2 | **Bloqueante** | Establecimiento cabecera ≠ envío |
| **ENV-014** | V3 | **Bloqueante** | Versión plantilla no vigente |
| **ENV-015** | V4 | **Bloqueante** | Establecimiento no pertenece al SS/usuario |
| **ENV-016** | V5 | **Bloqueante** | Hoja obligatoria faltante |
| **ENV-017** | V5 | **Advertencia** | Hoja extra no prevista |
| **ENV-018** | V6 | **Advertencia** | Prestación sin datos en hoja |
| **ENV-019** | V6 | **Bloqueante** | Rango ilegible (celda vacía/merged) |
| **ENV-020** | V7 | **Advertencia** | Valor no numérico donde se espera número |
| **ENV-021** | V7 | **Advertencia** | Decimales exceden configuración |
| **ENV-031** | V7 | **Advertencia** | Fórmula sin valor calculado |
| **ENV-022** | V8 | **Advertencia** | Columna excepción con valor |
| **ENV-023** | V9 | **Advertencia** | Prestación duplicada en archivo |
| **ENV-024** | V10 | **Bloqueante** | Archivo sin registros válidos |
| **ENV-025** | V11 | **Bloqueante** | Faltan series obligatorias (nivel envío) |
| **ENV-026** | Proceso | **Bloqueante** | Ya existe carga aprobada para período |
| **ENV-029** | Proceso | **Bloqueante** | Mismo SHA-256 ya subido en este envío |
| **ENV-030** | Proceso | **Bloqueante** | Timeout validación (>10s lock / >90s proceso) |
| **ENV-032** | Proceso | **Bloqueante** | Antivirus no verifica (si habilitado) |

### 2.2 Resumen por severidad

| Severidad | Cantidad | Códigos |
|---|---:|---|
| **Bloqueante** | 18 | ENV-001, 002, 003, 004, 005, 006, 007, 008, 009, 010, 011, 012, 013, 014, 015, 016, 019, 024, 025, 026, 029, 030, 032 |
| **Advertencia** | 11 | ENV-017, 018, 020, 021, 022, 023, 028, 031 |
| **Informativa** | 0 | (Se definen en reporte: filas leídas, duración, plantilla, snapshot) |

> **Total: 32 códigos ENV-###** (23 bloqueantes, 9 advertencias, 0 informativas en catálogo base).

---

## 3. Reglas de parada de cadena (Pipeline V0–V11)

```php
function validar(ContextoValidacion $ctx): ResultadoValidacion {
    $resultado = new ResultadoValidacion();
    $reglas = obtenerReglasOrdenadas(); // V0, V1, V2, V3, V4, V5, V6, V7, V8, V9, V10, V11
    
    foreach ($reglas as $regla) {
        $hallazgos = $regla->aplicar($ctx);
        $resultado->agregar($hallazgos);
        
        // PARADA: si hay bloqueantes Y no es V11 → detener
        if ($resultado->tieneBloqueantes() && $regla->etapa() !== 'V11') {
            $resultado->cadenaDetenida = true;
            $resultado->etapaDetencion = $regla->etapa();
            break;
        }
    }
    
    // V11 SIEMPRE se ejecuta (nivel envío, no archivo)
    if (!isset($reglas['V11'])) {
        $hallazgosV11 = $reglas['V11']->aplicar($ctx);
        $resultado->agregar($hallazgosV11);
    }
    
    return $resultado;
}
```

### 3.1 Comportamiento por etapa

| Etapa | Si tiene bloqueante | Si solo advertencias | Notas |
|---|---|---|---|
| **V0–V10** | Detiene cadena → archivo `con_errores` | Continúa → archivo `validado` | V11 no se evalúa aquí (es nivel envío) |
| **V11** | Bloquea "Enviar a revisión" | Permite "Enviar a revisión" | Se evalúa tras cada subida, a nivel envío |

### 3.2 Estados de archivo resultantes

| Hallazgos | Estado archivo | Permite "Enviar a revisión" |
|---|---|---|
| 0 bloqueantes, 0 advertencias | `valido` | ✅ (si V11 OK) |
| 0 bloqueantes, ≥1 advertencia | `valido` | ✅ (si V11 OK + revisor marca checks) |
| ≥1 bloqueante | `con_errores` | ❌ |

---

## 4. Flujo de revisión de advertencias (Modal "Aprobar")

### 4.1 Requisito (D2 resuelto: Mixto — envío con veto por serie)

> **ENV-025 (V11)** es bloqueante a nivel envío: si faltan series obligatorias, **no se puede aprobar** el envío completo.

### 4.2 Checklist obligatorio en modal "Aprobar"

| Elemento | Obligatorio | Descripción |
|---|---|---|
| **Bloqueantes = 0** | Sí | Validación automática: si >0, botón deshabilitado |
| **Advertencias "revisadas"** | Sí | Checkbox por cada advertencia presente en archivos vigentes |
| **Comentario revisor** | Sí | Texto libre obligatorio (mínimo 10 caracteres) |
| **Confirmación explícita** | Sí | Botón "Confirmar aprobación" separado |

### 4.3 Regla de veto por serie (D2)

- Si **una serie** tiene bloqueantes → **todo el envío** no se puede aprobar
- El revisor ve resumen por serie: 🟢 OK / 🔴 Bloqueantes / 🟡 Advertencias
- Debe resolver serie a serie (observar → encargado corrige → reenvía)

---

## 5. Escalación de severidad (Cambio de política)

### 5.1 Criterios para reclasificar

| Situación | Acción | Autoridad |
|---|---|---|
| Advertencia que **siempre** se ignora y causa errores en CARGAS REM | Subir a **Bloqueante** | Unidad de Estadísticas + Registro en `decisions.md` |
| Bloqueante que **nunca** ocurre en archivos reales (falso positivo) | Bajar a **Advertencia** | Unidad de Estadísticas + Registro en `decisions.md` |
| Nuevo error detectado en paridad (golden files) | Asignar severidad según impacto | Equipo técnico + Unidad de Estadísticas |

### 5.2 Proceso de cambio

1. **Detección:** En F0 (paridad) o F2 (implementación) o producción
2. **Propuesta:** Documentar código ENV, severidad actual, propuesta, justificación
3. **Revisión:** Unidad de Estadísticas valida impacto operativo
4. **Registro:** Nueva fila en `02-DOCS/wiki/sdd/decisions.md` (append-only)
5. **Implementación:** Actualizar catálogo, tests, reporte UI
6. **Verificación:** Paridad con golden files (tolerancia 0)

---

## 6. Mensajes al usuario (UI)

### 6.1 Principios (Constitución §11, 12, 23)

- **Español chileno**, sin jerga técnica
- **Código ENV-###** visible siempre
- **Acción sugerida** concreta
- **Accesibilidad:** foco visible, contraste, navegación teclado

### 6.2 Ejemplos por severidad

| Severidad | Formato mensaje | Ejemplo |
|---|---|---|
| **Bloqueante** | `❌ [ENV-###] Mensaje. Acción: {acción}.` | `❌ [ENV-016] Falta la hoja obligatoria "CAMAS". Acción: Agregue la hoja y vuelva a subir.` |
| **Advertencia** | `⚠️ [ENV-###] Mensaje. Acción sugerida: {acción}.` | `⚠️ [ENV-017] Hoja no prevista "OBSERVACIONES". Acción sugerida: Revise si es necesaria; se ignorará.` |
| **Informativa** | `ℹ️ [ENV-###] Mensaje.` | `ℹ️ [ENV-INF] Archivo validado en 2.3s con plantilla v2.1.` |

---

## 7. Reporte de validación (Excel/CSV)

### 7.1 Hojas del archivo

| Hoja | Contenido | Filtro por severidad |
|---|---|---|
| **Resumen** | Totales por severidad, estado final, serie, versión, filas | — |
| **Bloqueantes** | Detalle de cada `ENV-###` bloqueante (hoja, celda, valor, mensaje) | Solo bloqueantes |
| **Advertencias** | Detalle de cada `ENV-###` advertencia + columna "Revisada ✓" | Solo advertencias |
| **Información** | Métricas: filas leídas, duración, plantilla, snapshot, hash | Solo informativas |

### 7.2 Columna "Revisada" (solo hoja Advertencias)

| Campo | Tipo | Descripción |
|---|---|---|
| `revisada` | Boolean | Checkbox para que revisor marque ✓ (requerido para aprobar) |
| `revisada_por` | String | Usuario que marcó |
| `revisada_en` | DateTime | Timestamp de marcado |

---

## 8. Tests obligatorios (Principio 6)

| Test | Cobertura |
|---|---|
| **Unitario por ENV-###** | Cada código tiene test que lo dispara con `ContextoValidacion` mockeado |
| **Pipeline parada** | Bloqueante en V3 detiene V4–V10; V11 siempre corre |
| **Estados archivo** | 0 bloqueantes → `valido`; ≥1 bloqueante → `con_errores` |
| **Modal aprobar** | Botón deshabilitado si bloqueantes > 0 o checks incompletos |
| **Paridad** | Cambio en V6–V8 → comparación golden files (tolerancia 0) |

---

## 9. Firma de la política

> **Este documento debe ser firmado por la Unidad de Estadísticas antes de iniciar F2 (implementación validación real).**

| Rol | Nombre | Fecha | Firma |
|---|---|---|---|
| **Referente Unidad de Estadísticas** | | | |
| **Líder técnico** | | | |
| **Admin proyecto** | | | |

---

## 10. Historial de cambios

| Fecha | Versión | Cambio | Autoridad |
|---|---|---|---|
| 2026-11-10 | 1.0 | Creación inicial desde catálogo ENV-### y M7 §7.5 | Equipo técnico |

---

**Archivo guardado en:** `C:\xampp\htdocs\www\CargaREM\docs\validacion\politica-severidad.md`

> **Próximo paso:** Reunión con Unidad de Estadísticas para revisión y firma. Sin firma, **no se inicia F2** (implementación validación real).