# `expected/` — salida de referencia del ValidadorRem

Para cada archivo de `../input/` hay un archivo `.json` con el resultado exacto que produjo el ValidadorRem. Es la verdad contra la que se compara el portal.

## Formato

Un objeto JSON con tres bloques:

| Bloque | Contenido |
|---|---|
| `fixture` | Identificador, serie y período |
| `procedencia` | Versión del ValidadorRem, `Global.mdb` usado y hash del input |
| `datos` | Filas de la tabla `datos` que el validador aceptó |
| `log_error` | Filas de la tabla `LogError` que el validador generó |

### `datos`

Columnas de la tabla `datos` (`MODULO_M2_PROFUNDIZADO.md` §4):

```text
ano · mes · idservicio · idestablecimiento · codigoprestacion · col01 … col50 · fechacarga
```

### `log_error`

Columnas de la tabla `LogError`:

```text
archivo · idestablecimiento · mes · ano · serie · linea · hoja · dato · mensaje_error · fecharegistro
```

## Reglas de comparación

1. **Tolerancia 0** en toda columna numérica. `1.0` y `1.00` son iguales; `1.0` y `1.0000001` no.
2. El **orden** de las filas no importa, pero el **contenido** sí. Se compara como conjunto ordenado por clave.
3. Los campos de fecha se comparan como instante, no como cadena.
4. Un error de más o de menos es una diferencia, aunque el resto coincida.

## Nombre

El del input, con extensión `.json`: `123100-A-2026-09.xlsx` → `123100-A-2026-09.json`.

## Archivos que empiezan con `_`

Se ignoran en la comparación. Sirven para documentar el formato.

- [`_ejemplo-estructura.json`](_ejemplo-estructura.json) — estructura con datos inventados. **No es un golden file** y no se compara.

## Estado

| Archivos | Estado |
|---|---|
| 0 reales | ❌ pendiente (T-F0-03, requiere ejecutar el ValidadorRem) |
| 1 de ejemplo | ✅ `_ejemplo-estructura.json` (sintético, excluido) |
