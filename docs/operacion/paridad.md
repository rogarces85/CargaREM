# Paridad con el ValidadorRem

El portal aplica las reglas documentadas. **Eso no prueba que se comporte igual
que el sistema anterior.** La paridad se mide comparando, archivo por archivo, el
resultado del motor con el que produce el ValidadorRem.

## Qué hace falta

Por cada archivo de entrada, un archivo de resultado esperado con el mismo
nombre y extensión `.esperado.json`:

```
portal/archivos/
├── 123100A09.xlsm
├── 123100A09.esperado.json
├── 123101A07.xlsm
├── 123101A07.esperado.json
└── ...
```

## Formato del esperado

```json
{
  "bloqueantes": 0,
  "advertencias": 333,
  "codigos": {
    "ENV-018": 41,
    "ENV-020": 291,
    "ENV-028": 1
  }
}
```

Todos los campos son opcionales: se compara solo lo que esté presente. Lo
mínimo útil es `bloqueantes` y `advertencias`. Con `codigos` la comparación es
más fina y dice exactamente qué regla difiere.

## Cómo obtener el esperado

1. Correr el **ValidadorRem** sobre cada archivo en un entorno con `Global.mdb`
   copiado y la base vacía.
2. Exportar su resultado: cuántos errores bloqueantes, cuántas advertencias y
   los códigos con su cantidad.
3. Guardar ese resultado como `X.esperado.json` junto al archivo.

Si el ValidadorRem no exporta JSON, alcanza con escribirlo a mano a partir de su
reporte en pantalla.

## Cómo se ejecuta la comparación

```bat
php artisan rem:paridad portal\archivos
```

Devuelve una tabla con el esperado, el obtenido y el veredicto de cada archivo.
Termina con código de error si algo no coincide, para que sirva en un pipeline.

```
+----------------+----------------------------+----------------------------+--------+
| Archivo        | Esperado                   | Obtenido                   | Veredicto |
+----------------+----------------------------+----------------------------+--------+
| 123100A09.xlsm | 0 bloqueantes / 333 advert | 0 bloqueantes / 333 advert | OK     |
| 123101A07.xlsm | 0 bloqueantes / 192 advert | 0 bloqueantes / 333 advert | DIFERENTE: advertencias esperadas 192, obtenidas 333 |
+----------------+----------------------------+----------------------------+--------+
```

## Qué significa cada veredicto

| Veredicto | Qué hacer |
|---|---|
| `OK` | La regla reproduce al sistema anterior en ese archivo |
| `DIFERENTE` | Hay que leer cuál de los dos tiene razón. No siempre es el portal |
| `sin esperado` | Falta el archivo de resultado: no se compara, y cuenta como no verificado |
| `error` | El motor no pudo procesar el archivo. Es un fallo del portal |

## Dónde viven los archivos

`portal/archivos/` está en `.gitignore`: son datos reales y la Constitución
(principio 22) los mantiene fuera del repositorio. Hay que copiarlos a cada
máquina donde se quiera correr la comparación.
