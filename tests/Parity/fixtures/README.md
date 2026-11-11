# Paridad con ValidadorRem — fixtures

Esta carpeta guarda los **golden files**: archivos Excel REM reales (anonimizados) junto con la salida exacta que produce el ValidadorRem sobre ellos.

La regla es la de la Constitución, principio 10: **tolerancia 0 en numéricos**. Si el portal produce una cifra distinta, es un bug o una regla no documentada. No hay margen.

> ⚠️ **Esta carpeta está incompleta a propósito.** Hoy solo contiene el *fixture* de permisos. Los archivos de paridad los aporta una persona (tarea T-F0-02). Nada de lo que haya aquí se inventa: un golden file que no salió del ValidadorRem no es un golden file.

---

## Estructura

```text
tests/Parity/fixtures/
├── manifest.json          Procedencia de cada fixture (hash, origen, quién lo generó)
├── rbac-matrix.json       Fixture de permisos (18 permisos × 4 roles) — no es Excel
├── input/                 Los archivos Excel anonimizados
└── expected/              La salida de referencia del ValidadorRem para cada input
```

---

## Cobertura mínima

**12 a 20 archivos.** Menos no cubre el espacio de casos; más no aporta proporcionalmente.

| Dimensión | Valores a cubrir |
|---|---|
| Serie | `A`, `BS`, `BM`, `D`, `P`, `F` (una de cada una como mínimo) |
| Formato | `.xls`, `.xlsx`, `.xlsm` (los tres) |
| Resultado | al menos 4 sin errores y 4 con errores bloqueantes |
| Error de cabecera | 1 caso de plantilla o cabecera incompatible |
| Error de contenido | 1 caso de celda no numérica y 1 de prestación inexistente |
| Límite | 1 archivo cercano a 2 MB (el tope de subida) |
| Macro | 1 `.xlsm` con macros: debe validarse igual y registrar la advertencia |
| Período | al menos 2 meses distintos |

`SAX` queda fuera del MVP (**D-006**), así que no entra en la cobertura. Se agregará con su propia fase.

---

## Reglas de los archivos

1. **Anonimizados.** Sin nombres de pacientes, RUT, ni datos que identifiquen personas. El código de establecimiento sí puede ir: es un dato institucional.
2. **Nunca se edita un input.** Si un archivo se reemplaza, se agrega con otro nombre y se anota en `manifest.json`. Un golden file que cambia en silencio deja de probar lo que probaba.
3. **Un input, un expected.** El nombre del expected es el del input con extensión `.json`.
4. **Sin archivos reales en el repositorio.** Verificar antes de cada commit (Constitución, principio 22).

---

## Cómo se genera un expected

Se ejecuta el ValidadorRem en un entorno de prueba con una **copia** de `Global.mdb` y una base vacía, se carga el input y se exportan las dos tablas que definen el resultado:

- `datos` — las filas que el validador consideró correctas.
- `LogError` — los errores, con hoja, línea y mensaje.

El script de exportación y el procedimiento están en `specs/TASK.md` (T-F0-03). El formato del archivo resultante está descrito en [`expected/README.md`](expected/README.md).

---

## Nomenclatura

```text
{establecimiento}-{serie}-{año}-{mes}
```

Ejemplo: `123100-A-2026-09`. Corresponde a `123100A09.xlsx`.

La convención viene del nombre real de los archivos (`{código de establecimiento}{serie}{mes}`, `informe.md` §2). El año no está en el nombre original y por eso se agrega en el identificador del fixture, no en el del archivo.

---

## Estado

| Elemento | Estado |
|---|---|
| Estructura y convención | ✅ definidas |
| `manifest.json` | ✅ creado, vacío |
| Inputs reales | ❌ pendiente (T-F0-02, requiere persona) |
| Expected reales | ❌ pendiente (T-F0-03, requiere ValidadorRem) |
