# `input/` — archivos Excel de entrada

Aquí van los archivos REM anonimizados que se usarán como entrada de la prueba de paridad.

## Qué va aquí

- Archivos `.xls`, `.xlsx` o `.xlsm` reales, generados por los establecimientos y **anonimizados**.
- Uno por serie y período, con el nombre original del establecimiento.

## Qué no va aquí

- Archivos fabricados a mano. Si se necesita un caso artificial, va en una carpeta aparte y no cuenta como paridad.
- Archivos con datos de personas.
- Copias del mismo archivo con otro nombre.

## Nombre

Se conserva el nombre original: `{código de establecimiento}{serie}{mes}`.

```text
123100A09.xlsx
123101BS09.xls
123102D08.xlsm
```

## Cómo se agrega uno

1. Copiar el archivo con su nombre original.
2. Anonimizar si hiciera falta.
3. Calcular su `sha256`.
4. Agregar la entrada en [`../manifest.json`](../manifest.json) con hash, procedencia y quién lo aportó.
5. Generar el `expected/` correspondiente (T-F0-03).

Sin el paso 4, el archivo no existe para la suite: no se puede probar de dónde salió.

## Cobertura vigente

| Archivos | Estado |
|---|---|
| 0 de 12–20 | ❌ pendiente |

Ver la matriz de cobertura en [`../README.md`](../README.md).
