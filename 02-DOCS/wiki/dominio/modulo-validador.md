---
type: dominio
title: Módulo Validador
description: Qué revisa el módulo de calidad, cómo se definen sus reglas en JSON, qué tipos admite y por qué no interviene en el flujo de envío.
tags: [dominio, validador, calidad, reglas, json]
timestamp: 2026-11-26T12:00:00Z
topic: dominio
---

# Módulo Validador

> **Decisión:** D-047. **Requisitos:** RF-040, RF-040.1, RF-040.2.
> **Implementación:** `App\Validador\*`,
> `App\Services\ValidadorEstadisticaService`, tabla `validador_ejecuciones`.

## 1. Qué es y qué no es

Es una **ayuda a la calidad de la estadística**: cualquier usuario sube un Excel
REM y lo contrasta contra las reglas que define la Unidad en un archivo JSON.

**No es** una etapa del flujo:

- no crea envíos;
- no cambia el estado de un envío;
- no escribe en `validaciones` ni en `envio_archivos`;
- no reemplaza la validación V0–V11.

Hay un test que lo demuestra contando las cuatro tablas antes y después de una
ejecución (`ModuloValidadorTest::test_el_validador_no_toca_ninguna_tabla_del_flujo`).

## 2. El archivo de reglas

Vive en `portal/config/validador/reglas.json` y lo versiona la Unidad. Su forma:

```json
{
  "version": "1.0",
  "nombre": "Calidad de la estadística REM",
  "reglas": [
    { "id": "CAL-001", "tipo": "hoja_presente", "severidad": "bloqueante",
      "hoja": "NOMBRE", "mensaje": "El archivo debe traer la hoja NOMBRE." }
  ]
}
```

| Campo | Obligatorio | Qué hace |
|---|---|---|
| `id` | sí | Identifica el hallazgo en pantalla |
| `tipo` | sí | Uno de los tipos admitidos |
| `severidad` | no | `bloqueante`, `advertencia` (por defecto) o `info` |
| `mensaje` | sí | Lo que lee el usuario |
| `hoja`, `columna`, `desde_fila`, `hasta_fila`, `min`, `max`, `esperado`, `valores` | según tipo | Parámetros |

## 3. Tipos admitidos

| Tipo | Qué comprueba |
|---|---|
| `hoja_presente` | La hoja existe en el libro |
| `requerido` | La columna tiene datos en todas sus filas |
| `tipo_dato` | La columna es numérica, entera, texto o fecha |
| `rango` | Los valores caen dentro de un mínimo y un máximo |
| `dominio` | Los valores pertenecen a una lista admitida |
| `unicidad` | La columna no repite valores |

**Un tipo desconocido aborta la ejecución** con el identificador de la regla
(R-VAL-02). Una regla ignorada en silencio es peor que una ejecución fallida:
el resultado parecería limpio y nadie revisaría lo que faltó.

## 4. Qué queda registrado

`validador_ejecuciones` guarda, por cada revisión:

- autor, archivo original y hash SHA-256;
- versión de reglas con la que se ejecutó;
- cantidad de hallazgos, bloqueantes y advertencias;
- duración y el detalle completo de los hallazgos (JSON).

**El archivo se guarda 90 días** en disco privado con nombre generado. Después
lo borra `rem:validador:limpiar`; la fila de uso se conserva.

## 5. Registro de uso

`/validador/estadisticas` (solo administrador) muestra, por año:

- revisiones por mes;
- revisiones por usuario, con hallazgos y bloqueantes acumulados y la fecha de
  la última.

Responde a la pregunta de la Unidad: **¿la herramienta se usa y con qué
resultado?** No hay cuota ni bloqueo: un módulo de ayuda que castiga por usarlo
se deja de usar.

## 6. Trazabilidad

| Elemento | Dónde |
|---|---|
| Catálogo | `portal/config/validador/reglas.json` |
| Carga y validación del JSON | `portal/app/Validador/CatalogoCalidad.php`, `ReglaCalidad.php` |
| Motor | `portal/app/Validador/MotorValidador.php` |
| Lectura del Excel | `portal/app/Validador/LectorLibro.php` |
| Servicio | `portal/app/Services/ValidadorEstadisticaService.php` |
| Modelo | `portal/app/Models/ValidadorEjecucion.php` |
| Pantallas | `portal/resources/views/validador/` |
| Tests | `portal/tests/Feature/ModuloValidadorTest.php` |
