---
type: dominio
title: Módulo Validador
description: Qué revisa el módulo de calidad, el formato real del catálogo de 144 reglas de la Unidad, cómo se evalúan y por qué no interviene en el flujo de envío.
tags: [dominio, validador, calidad, reglas, json, paridad]
timestamp: 2026-11-26T18:00:00Z
topic: dominio
---

# Módulo Validador

> **Decisión:** D-047. **Requisitos:** RF-040, RF-040.1, RF-040.2.
> **Implementación:** `App\Validador\*`,
> `App\Services\ValidadorEstadisticaService`, tabla `validador_ejecuciones`.

## 1. Qué es y qué no es

Es una **ayuda a la calidad de la estadística**: cualquier usuario sube un Excel
REM y lo contrasta contra el catálogo de reglas de la Unidad.

**No es** una etapa del flujo:

- no crea envíos;
- no cambia el estado de un envío;
- no escribe en `validaciones` ni en `envio_archivos`;
- no reemplaza la validación V0–V11 del portal.

Hay un test que lo demuestra contando las cuatro tablas del flujo antes y
después de una ejecución
(`ModuloValidadorTest::test_el_validador_no_toca_ninguna_tabla_del_flujo`).

## 2. El catálogo real

El archivo es el que usa el **Validador de escritorio** de la Unidad
(`C:\xampp\htdocs\www\Validador2026`), copiado a
`portal/config/validador/reglas.json`:

| Dato | Valor |
|---|---|
| Reglas | **144** |
| Hojas REM | **23** (A01…A30AR, P1…P12) |
| Serie A | 103 reglas |
| Serie P | 41 reglas |
| Tipos | `SIMPLE` (63), `DOBLE` (65), `COMPUESTA` (16) |
| Severidades | `ERROR` (94), `REVISAR` (50) |

Está **agrupado por hoja REM**, no como una lista plana:

```json
{ "A01": [ { "id": "A01-VAL001", "tipo": "SIMPLE", … } ], "P2": [ … ] }
```

Cada regla declara:

| Campo | Qué es |
|---|---|
| `id` | Identificador estable (`A01-VAL001`) |
| `tipo` | SIMPLE, DOBLE o COMPUESTA |
| `severidad` | ERROR o REVISAR |
| `rem_sheet` | Hoja donde vive `expresion_1` |
| `expresion_1`, `operador`, `expresion_2` | La comparación |
| `rem_sheet_2` | Hoja de `expresion_2` cuando cruza hojas |
| `mensaje`, `seccion`, `detalle` | Cómo se le explica al usuario |
| `aplicar_a`, `aplicar_a_tipo`, `excluir_a`, `excluir_tipo` | Alcance por establecimiento |
| `condicion_previa`, `omitir_si_*` | Cuándo la regla no aplica |
| `correccion` | Apartamiento del Excel maestro acordado con Estadística |

> **El catálogo no es definitivo.** La Unidad lo está depurando; el módulo lo
> lee tal como está y se actualiza copiando el archivo. La versión viaja con
> cada ejecución para poder repetir una revisión con el mismo criterio.

## 3. Cómo se evalúan las expresiones

Las reglas no dicen «la celda X debe ser mayor que cero»: escriben una
comparación entre dos expresiones. Ejemplos reales:

```text
F11
C19+C20+C21+C22+C23+C24+C25+C26+F36+F37+F38
SUM(C25:C58)
SUM(H17:U17)+SUM(V22:AG22)-C38
B60*4
C12+(F12-G12)
```

`App\Validador\Expresion` es un parser de descenso recursivo con la misma
gramática que el escritorio, para que una regla escrita para aquel se lea igual
aquí. Se prueba aparte porque **un error de precedencia produce resultados
plausibles y equivocados**.

Criterios de lectura, alineados con cómo se leen los REM:

- Celda ausente, vacía o no numérica → **0**.
- Número escrito como texto («12») → **12**.
- Formato chileno «1.234,5» → **1234.5**.
- División por cero → **0** (no propaga `NaN`).

## 4. Los tres tipos de regla

| Tipo | Forma | Ejemplo |
|---|---|---|
| SIMPLE | Un lado del libro contra un literal | `F11 == 0` |
| DOBLE | Dos celdas que deben cuadrar | `B11 == B21` |
| COMPUESTA | Sumas, a veces cruzando hojas | `C19+…+F38 == A05!C89` |

El tipo no cambia cómo se evalúa —los tres son dos expresiones y un operador—
pero sí cómo se lee la regla y qué se informa.

## 5. Las cuatro formas de no ser un hallazgo

Una regla puede terminar en cuatro estados, y la distinción es la que hace
honesto el porcentaje de calidad:

| Estado | Qué significa |
|---|---|
| **CUMPLE** | La comparación se cumplió |
| **HALLAZGO** | La comparación falló |
| **OMITIDA** | No había nada que comparar, o la regla no aplicaba |
| **ERROR_TECNICO** | La regla no se pudo interpretar o el libro falló |

Una regla **omitida no es una regla cumplida**. Contarlas como aprobadas infla
el resultado sin que nadie haya revisado nada.

Los motivos de omisión, cada uno por un falso positivo real:

| Motivo | Cuándo |
|---|---|
| `AMBOS_CERO` | Los dos lados están en 0 **y ambos se leen del libro**. Un literal en el lado derecho es el valor esperado, no un dato ausente: `F11 == 0` con F11 vacío es un archivo correcto |
| `CONDICION_PREVIA_NO_CUMPLE` | La regla declara una condición que no se da |
| `SECCION_SIN_ACTIVIDAD` | Le corresponde al titular de la sección, pero el mes no trae registro |
| `HOJA_AUSENTE` | El archivo no trae la hoja que la regla necesita |

## 6. Alcance por establecimiento

Una regla puede limitarse a códigos DEIS, a tipos de recinto, o excluirlos. Las
reglas dirigidas a un tipo **no se evalúan** en otro establecimiento: no
cuentan como cumplidas ni como hallazgos, simplemente no aparecen.

El tipo es obligatorio para decidir. Por eso el pipeline rechaza el archivo si
el código no está en la nómina
(`config/validador/establecimientos.json`, 77 establecimientos): sin tipo, esas
reglas quedarían fuera de alcance **en silencio**.

## 7. La portada (hoja NOMBRE)

Antes de las reglas de negocio se revisa la hoja NOMBRE, porque si está mal el
archivo completo se carga a nombre del establecimiento o del período
equivocado.

| Revisa | Dónde |
|---|---|
| Versión de plantilla | `A9` |
| Comuna (nombre y código dígito a dígito) | `B2`, `C2:G2` |
| Establecimiento (nombre y código dígito a dígito) | `B3`, `C3:H3` |
| Que el código de la hoja coincida con el del nombre del archivo | `C3:H3` vs nombre |
| Que el nombre de `B3` sea el de **ese** código, no el de otro | comparación tolerante |
| Mes (nombre y código) y que corresponda a la serie | `B6`, `C6:D6` |
| Responsable y jefe de estadística | `B11`, `B12` |

La comparación del nombre no es carácter por carácter: lo teclea una persona y
llega con abreviaturas, sin tildes o con erratas. Se separa el **tipo de
recinto** (lo repiten 31) de la **parte distintiva** (lo que identifica), y se
mide la distancia sobre la parte que identifica.

## 8. Qué queda registrado

`validador_ejecuciones` guarda, por cada revisión:

- autor, archivo original y hash SHA-256;
- versión del catálogo con la que se ejecutó;
- hallazgos, errores y advertencias;
- duración, veredicto y el detalle completo (JSON).

**El archivo se guarda 90 días** en disco privado con nombre derivado del hash.
Después lo borra `rem:validador:limpiar`; la fila de uso se conserva.

## 9. Paridad con el escritorio

El Validador de escritorio genera archivos REM de prueba con un índice que
declara el estado esperado de cada regla. `ParidadValidadorTest` corre el motor
del portal sobre esos archivos y compara.

**Resultado: 287 de 288 comparaciones coinciden.** La diferencia restante está
documentada y tiene causa conocida:

> `A04-VAL006` — el catálogo se regeneró el **2026-09-09** y cambió el operador
> a `>=`; las pruebas se generaron el **2026-08-12** y siguen esperando el
> operador anterior. El motor del portal aplica el catálogo vigente, que es lo
> correcto. Se resuelve regenerando las pruebas en el proyecto de escritorio.

El test falla si aparece una diferencia **nueva**, y también si una diferencia
conocida deja de ocurrir (para no dejar una excusa que ya no hace falta).

## 10. Registro de uso

`/validador/estadisticas` (solo administrador) muestra, por año, revisiones por
mes y por usuario con sus hallazgos. Responde a la pregunta de la Unidad:
**¿la herramienta se usa y con qué resultado?** No hay cuota ni bloqueo: un
módulo de ayuda que castiga por usarlo se deja de usar.

## 11. Trazabilidad

| Elemento | Dónde |
|---|---|
| Catálogo | `portal/config/validador/reglas.json` |
| Nómina | `portal/config/validador/establecimientos.json` |
| Parser | `portal/app/Validador/Expresion.php` |
| Motor | `portal/app/Validador/MotorReglas.php` |
| Catálogo cargado | `portal/app/Validador/CatalogoCalidad.php`, `ReglaCalidad.php` |
| Portada | `portal/app/Validador/HojaNombre.php`, `NombreArchivo.php` |
| Comparación de nombres | `portal/app/Validador/NombreEstablecimiento.php` |
| Pipeline | `portal/app/Validador/ValidadorEstadistico.php` |
| Servicio | `portal/app/Services/ValidadorEstadisticaService.php` |
| Pantallas | `portal/resources/views/validador/` |
| Tests | `ValidadorMotorTest` (21), `ModuloValidadorTest` (12), `ParidadValidadorTest` (4) |
